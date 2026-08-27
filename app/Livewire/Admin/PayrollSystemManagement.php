<?php

namespace App\Livewire\Admin;

use App\Enums\PayrollOperatingMode;
use App\Models\Payroll\PayrollSourceBatch;
use App\Services\Payroll\CanonicalWorkbookService;
use App\Services\Payroll\ConnectedCanonicalSyncService;
use App\Services\Payroll\PayrollOperatingModeService;
use App\Services\Payroll\PayrollReadinessService;
use App\Services\Payroll\PayrollReconciliationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithFileUploads;

class PayrollSystemManagement extends Component
{
    use WithFileUploads;

    public $file;

    public string $period = '';

    public string $adminPassword = '';

    public string $selectedMode = '';

    public string $templateSheet = '';

    public ?int $selectedBatchId = null;

    public ?array $reconciliation = null;

    public string $accountEmpId = '';

    public string $accountPassword = '';

    public function mount(PayrollOperatingModeService $modes): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin') || auth()->user()?->can('payroll.system.import'), 403);
        $this->selectedMode = $modes->current()->value;
    }

    public function switchMode(PayrollOperatingModeService $modes): void
    {
        $user = auth()->user();
        abort_unless($user?->hasRole('super-admin'), 403);
        $this->validate(['selectedMode' => ['required', 'in:connected,standalone'], 'adminPassword' => ['required', 'string']]);
        if (! Hash::check($this->adminPassword, $user->getAuthPassword())) {
            $this->addError('adminPassword', 'The super administrator password is incorrect.');

            return;
        }
        if ($modes->current() === PayrollOperatingMode::Standalone && $this->selectedMode === PayrollOperatingMode::Connected->value && $this->reconciliation === null) {
            $this->addError('selectedMode', 'Preview reconnect conflicts before returning to connected mode.');

            return;
        }
        $modes->change(PayrollOperatingMode::from($this->selectedMode), $user->emp_id);
        $this->adminPassword = '';
        session()->flash('status', 'Payroll operating mode changed.');
    }

    public function stage(CanonicalWorkbookService $service): void
    {
        abort_unless(auth()->user()?->can('payroll.system.import'), 403);
        $data = $this->validate(['file' => ['required', 'file', 'mimes:xlsx,xlsm,xls', 'max:30720'], 'period' => ['nullable', 'date_format:Y-m']]);
        $batch = $service->stage($this->file->getRealPath(), $this->file->getClientOriginalName(), $data['period'] ?: null, auth()->user()?->emp_id);
        $this->selectedBatchId = $batch->id;
        $this->file = null;
        session()->flash('status', "Workbook staged as batch #{$batch->id}.");
    }

    public function activate(CanonicalWorkbookService $service): void
    {
        abort_unless(auth()->user()?->can('payroll.system.import'), 403);
        $batch = PayrollSourceBatch::query()->findOrFail($this->selectedBatchId);
        $service->activate($batch, auth()->user()?->emp_id);
        session()->flash('status', "Batch #{$batch->id} activated atomically.");
    }

    public function rollback(int $id, CanonicalWorkbookService $service): void
    {
        abort_unless(auth()->user()?->can('payroll.system.rollback'), 403);
        $service->rollback(PayrollSourceBatch::query()->findOrFail($id));
        session()->flash('status', "Batch #{$id} rolled back.");
    }

    public function sync(ConnectedCanonicalSyncService $service): void
    {
        abort_unless(auth()->user()?->can('payroll.system.sync'), 403);
        abort_if(app(PayrollOperatingModeService::class)->current() !== PayrollOperatingMode::Connected, 422, 'Connected synchronization is disabled in stand-alone mode.');
        $batch = $service->sync(auth()->user()?->emp_id);
        session()->flash('status', "Connected data synchronized in batch #{$batch->id}.");
    }

    public function previewReconciliation(PayrollReconciliationService $service): void
    {
        $this->reconciliation = $service->preview();
    }

    public function resetLocalPassword(): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin'), 403);
        $data = $this->validate(['accountEmpId' => ['required', 'exists:payroll.payroll_user_accounts,emp_id'], 'accountPassword' => ['required', 'string', 'min:12']]);
        \App\Models\Payroll\PayrollUserAccount::query()->where('emp_id', $data['accountEmpId'])->update(['password' => Hash::make($data['accountPassword']), 'login_attempt' => 1]);
        $this->reset('accountEmpId', 'accountPassword');
        session()->flash('status', 'Local payroll password reset.');
    }

    public function downloadTemplate(CanonicalWorkbookService $service)
    {
        $sheet = $this->templateSheet !== '' ? $this->templateSheet : null;
        $filename = $sheet ? 'payroll-'.str($sheet)->slug().'-template.xlsx' : 'payroll-standalone-template.xlsx';

        return response()->download($service->template($sheet), $filename)->deleteFileAfterSend(true);
    }

    public function render(PayrollOperatingModeService $modes, PayrollReadinessService $readiness)
    {
        $connections = ['hris' => null, 'payroll_scheduler' => null];
        if ($modes->current() === PayrollOperatingMode::Connected) {
            foreach (array_keys($connections) as $name) {
                try {
                    DB::connection($name)->getPdo();
                    $connections[$name] = true;
                } catch (\Throwable) {
                    $connections[$name] = false;
                }
            }
        }

        $batches = PayrollSourceBatch::query()
            ->select(['id', 'kind', 'source', 'status', 'original_filename', 'activated_at', 'created_at'])
            ->latest()
            ->limit(20)
            ->get();

        return view('livewire.admin.payroll-system-management', ['currentMode' => $modes->current(), 'allowedModes' => $modes->allowed(), 'forced' => $modes->forced(), 'readiness' => $readiness->check($this->period ?: null), 'connections' => $connections, 'batches' => $batches, 'selectedBatch' => $this->selectedBatchId ? PayrollSourceBatch::query()->find($this->selectedBatchId) : null]);
    }
}
