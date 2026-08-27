<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollSourceBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConnectedCanonicalSyncService
{
    public function sync(?string $by = null): PayrollSourceBatch
    {
        $payload = [];
        $payload['Divisions'] = DB::connection('hris')->table('tbl_division')->get()->map(fn ($r) => ['division_id' => $r->division_id, 'name' => $r->division, 'is_active' => true, '_row' => $r->division_id])->all();
        $payload['Departments'] = DB::connection('hris')->table('tbl_department')->get()->map(fn ($r) => ['department_id' => $r->department_id, 'division_id' => $r->division_id, 'name' => $r->department, 'is_active' => true, '_row' => $r->department_id])->all();
        $payload['Positions'] = DB::connection('hris')->table('tbl_position')->get()->map(fn ($r) => ['position_id' => $r->position_id, 'title' => $r->position_title, 'salary_grade' => $r->salary_grade, 'remarks' => $r->remarks ?? null, 'is_active' => true, '_row' => $r->position_id])->all();
        $payload['Employees'] = DB::connection('hris')->table('tbl_employee')->get()->map(fn ($r) => ['employee_id' => $r->emp_id, 'first_name' => $r->firstname, 'middle_name' => $r->middlename, 'last_name' => $r->lastname, 'extension' => $r->extension ?? null, 'suffix' => $r->suffix ?? null, 'position_id' => $r->position_id, 'department_id' => $r->department_id, 'step' => $r->step, 'employment_status_id' => $r->empstat_id, 'date_hired' => $r->date_hired, 'tin' => $r->tin_no, 'gsis' => $r->gsis_no, 'philhealth' => $r->phic_no, 'pagibig' => $r->pagibig_no, 'vl_balance' => $r->vacation_leave_credits ?? 0, 'sl_balance' => $r->sick_leave_credits ?? 0, 'is_external' => $r->is_external ?? false, 'is_active' => ($r->is_active ?? 'N') === 'Y', 'responsibility_center' => null, 'bank_account' => null, 'fund_type' => null, '_row' => $r->emp_id])->all();
        $payload['Salary Rates'] = DB::connection('hris')->table('tbl_salary_grade')->get()->map(fn ($r) => ['salary_grade' => $r->salary_grade, 'step' => $r->step_increment, 'monthly_salary' => $r->salary, 'effective_from' => $r->effectivity_date, 'effective_to' => null, '_row' => $r->salary_grade.'-'.$r->step_increment])->all();
        $payload['Leave Types'] = DB::connection('hris')->table('tbl_leave_type')->get()->map(fn ($r) => ['leave_type_id' => $r->leave_type_id, 'name' => $r->leave_name, 'is_active' => (bool) ($r->to_display ?? true), '_row' => $r->leave_type_id])->all();
        $cancelled = [];
        if (Schema::connection('hris')->hasTable('tbl_employee_leave_log')) {
            $cancelled = DB::connection('hris')->table('tbl_employee_leave_log')->whereIn('action', [2, 3])->pluck('leave_id')->flip()->all();
        }
        $payload['Leaves'] = DB::connection('hris')->table('tbl_employee_leave')
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('start_date', '>=', '1000-01-01')
            ->where('end_date', '>=', '1000-01-01')
            ->get()
            ->filter(fn ($r) => $this->isValidDate($r->start_date) && $this->isValidDate($r->end_date))
            ->map(fn ($r) => ['leave_id' => (string) $r->leave_id, 'employee_id' => $r->emp_id, 'leave_type_id' => $r->leave_type, 'start_date' => $r->start_date, 'end_date' => $r->end_date, 'days_with_pay' => $r->days_wpay ?? 0, 'days_without_pay' => $r->days_wopay ?? 0, 'cancelled' => isset($cancelled[$r->leave_id]), '_row' => $r->leave_id])
            ->all();
        $sourceAccounts = DB::connection('hris')->table('tbl_useraccount')->get();
        $payload['Accounts'] = $sourceAccounts->map(fn ($r) => ['employee_id' => $r->emp_id, 'username' => $r->username, 'password_hash' => $r->password, 'is_active' => true, '_row' => $r->userid])->all();
        $batch = PayrollSourceBatch::query()->create(['kind' => 'connected_full', 'source' => 'connected', 'status' => 'validated', 'schema_version' => '1.0', 'checksum' => hash('sha256', json_encode($payload)), 'statistics' => collect($payload)->map(fn (array $rows): int => count($rows))->all(), 'errors' => [], 'payload' => $payload, 'created_by' => $by]);
        app(CanonicalWorkbookService::class)->activate($batch, $by);
        $this->syncAccessControl($sourceAccounts);

        return $batch->fresh();
    }

    private function syncAccessControl($sourceAccounts): void
    {
        if (! Schema::connection('hris')->hasTable('roles')) {
            return;
        }

        DB::connection('payroll')->transaction(function () use ($sourceAccounts) {
            foreach (['permissions', 'roles', 'role_has_permissions'] as $table) {
                DB::connection('payroll')->table($table)->delete();
                DB::connection('payroll')->table($table)->insert(DB::connection('hris')->table($table)->get()->map(fn ($row) => (array) $row)->all());
            }
            DB::connection('payroll')->table('model_has_roles')->delete();
            DB::connection('payroll')->table('model_has_permissions')->delete();
            $localIds = DB::connection('payroll')->table('payroll_user_accounts')->pluck('userid', 'emp_id');
            $sourceEmpIds = $sourceAccounts->pluck('emp_id', 'userid');
            foreach (['model_has_roles', 'model_has_permissions'] as $table) {
                $key = $table === 'model_has_roles' ? 'role_id' : 'permission_id';
                $rows = DB::connection('hris')->table($table)->get()->map(function ($row) use ($key, $localIds, $sourceEmpIds) {
                    $empId = $sourceEmpIds[$row->model_id] ?? null;
                    $localId = $empId ? ($localIds[$empId] ?? null) : null;

                    return $localId ? [$key => $row->{$key}, 'model_type' => \App\Models\Payroll\PayrollUserAccount::class, 'model_id' => $localId] : null;
                })
                    ->filter()
                    ->unique(fn (array $row) => $row[$key].'|'.$row['model_type'].'|'.$row['model_id'])
                    ->values();
                foreach ($rows->chunk(500) as $chunk) {
                    DB::connection('payroll')->table($table)->insert($chunk->all());
                }
            }
        });
    }

    private function isValidDate(mixed $value): bool
    {
        $value = trim((string) $value);
        if (! preg_match('/^(\d{4})-\d{2}-\d{2}/', $value, $matches) || (int) $matches[1] < 1000) {
            return false;
        }

        return strtotime($value) !== false;
    }
}
