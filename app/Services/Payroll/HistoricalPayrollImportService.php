<?php

namespace App\Services\Payroll;

use App\Models\Hris\Employee;
use App\Models\Hris\Department;
use App\Models\Hris\Division;
use App\Models\Hris\LeaveType;
use App\Models\Payroll\HistoricalPayrollImport;
use App\Models\Payroll\HistoricalPayrollImportRow;
use App\Models\Payroll\HistoricalPayrollImportSheet;
use App\Models\Payroll\PayrollBatch;
use App\Models\Payroll\PayrollBatchRecord;
use App\Models\Payroll\PayrollDeduction;
use App\Models\Payroll\PayrollGenerationDraft;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HistoricalPayrollImportService
{
    private const WORKBOOK_COLUMNS = [
        'gross_compensation' => 'AZ',
        'total_mandatory_deductions' => 'BT',
        'total_other_deductions' => 'FO',
        'total_non_taxable_income' => 'GB',
        'total_taxable_income' => 'GC',
        'withholding_tax_gross' => 'GD',
        'withholding_tax_adjustment' => 'GE',
        'net_pay' => 'GF',
        'fifteenth' => 'GG',
        'thirtieth' => 'GH',
        'gross_hazard_pay' => 'GO',
        'hazard_adjustment' => 'GP',
        'net_hazard_pay' => 'GU',
    ];

    public function stage(string $temporaryPath, string $originalName, string $period, string $payrollType, ?string $userId, ?callable $progress = null): HistoricalPayrollImport
    {
        $hash = hash_file('sha256', $temporaryPath);
        if (HistoricalPayrollImport::query()
            ->where('file_hash', $hash)
            ->where('payroll_period', $period)
            ->where('payroll_type_code', $payrollType)
            ->where('status', 'applied')
            ->exists()) {
            throw ValidationException::withMessages([
                'file' => 'This workbook has already been imported for the selected payroll period and type.',
            ]);
        }
        $progress && $progress(2, 'Preparing workbook');
        $storedPath = 'historical-payroll-imports/'.Str::uuid().'.'.strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        Storage::disk('local')->put($storedPath, file_get_contents($temporaryPath));

        try {
            $reader = IOFactory::createReaderForFile($temporaryPath);
            $reader->setReadDataOnly(false);
            $progress && $progress(5, 'Loading workbook formulas');
            $workbook = $reader->load($temporaryPath);
            $progress && $progress(10, 'Matching employee numbers');
            $employeeIds = Employee::query()->pluck('emp_id')
                ->mapWithKeys(fn ($employeeId) => [$this->employeeNumber($employeeId) => (string) $employeeId]);
            $worksheetCount = max(1, $workbook->getSheetCount());

            return DB::connection('payroll')->transaction(function () use ($workbook, $worksheetCount, $employeeIds, $originalName, $storedPath, $hash, $period, $payrollType, $userId, $progress) {
                $import = HistoricalPayrollImport::create([
                    'original_filename' => $originalName,
                    'stored_path' => $storedPath,
                    'file_hash' => $hash,
                    'payroll_period' => $period,
                    'payroll_type_code' => $payrollType,
                    'created_by' => $userId,
                ]);

                $worksheetIndex = 0;
                foreach ($workbook->getWorksheetIterator() as $worksheet) {
                    $sheetStartProgress = 10 + (int) floor(82 * ($worksheetIndex / $worksheetCount));
                    $progress && $progress($sheetStartProgress, 'Scanning '.$worksheet->getTitle());
                    $headerRow = $this->headerRow($worksheet);
                    if (! $headerRow || ! $this->hasPayrollOutputs($worksheet, $headerRow)) {
                        $worksheetIndex++;

                        continue;
                    }

                    $headers = $this->headers($worksheet, $headerRow);
                    $metricColumns = $this->metricColumns($headers);
                    $configurationHints = $this->configurationHints($worksheet, $period);
                    $sheet = $import->sheets()->create([
                        'sheet_name' => $worksheet->getTitle(),
                        'header_row' => $headerRow,
                        'column_map' => ['metrics' => $metricColumns, 'headers' => $headers, 'configuration' => $configurationHints],
                    ]);
                    $rows = [];
                    $organizationMappings = [];
                    $highestDataRow = max($headerRow + 1, $worksheet->getHighestDataRow());
                    for ($rowNumber = $headerRow + 1; $rowNumber <= $highestDataRow; $rowNumber++) {
                        if ($rowNumber === $headerRow + 1 || $rowNumber % 50 === 0 || $rowNumber === $highestDataRow) {
                            $sheetFraction = ($rowNumber - $headerRow) / max(1, $highestDataRow - $headerRow);
                            $percent = 10 + (int) floor(82 * (($worksheetIndex + $sheetFraction) / $worksheetCount));
                            $progress && $progress(min(92, $percent), 'Reading '.$worksheet->getTitle().' rows');
                        }
                        $employeeNo = $this->employeeNumber($this->value($worksheet->getCell('B'.$rowNumber)));
                        $employeeName = trim((string) $this->value($worksheet->getCell('P'.$rowNumber)));
                        if ($employeeNo === '' || $employeeName === '') {
                            continue;
                        }
                        $sourceDivision = trim((string) $this->value($worksheet->getCell('C'.$rowNumber)));
                        $sourceDepartment = trim((string) $this->value($worksheet->getCell('D'.$rowNumber)));
                        $organizationKey = $this->organizationKey($sourceDivision, $sourceDepartment);
                        $matchedEmployeeId = $employeeIds->get($employeeNo);
                        $organizationMappings[$organizationKey] ??= [
                            'source_division' => $sourceDivision,
                            'source_department' => $sourceDepartment,
                            'division_id' => null,
                            'department_id' => null,
                            'included' => true,
                        ];
                        if (($organizationMappings[$organizationKey]['match_status'] ?? null) === null) {
                            $organizationMappings[$organizationKey] = [
                                ...$organizationMappings[$organizationKey],
                                ...$this->matchOrganization($sourceDivision, $sourceDepartment),
                            ];
                        }

                        $workbookValues = collect($metricColumns)
                            ->mapWithKeys(fn (string $column, string $key) => [$key => $this->number($this->value($worksheet->getCell($column.$rowNumber)))])
                            ->all();
                        $sourceValues = [];
                        foreach ($headers as $column => $label) {
                            $value = $this->value($worksheet->getCell($column.$rowNumber));
                            if ($value !== null && $value !== '') {
                                $sourceValues[$column] = ['label' => $label, 'value' => $value];
                            }
                        }
                        $rows[] = [
                            'historical_payroll_import_sheet_id' => $sheet->id,
                            'source_row' => $rowNumber,
                            'source_employee_no' => $employeeNo,
                            'source_employee_name' => $employeeName,
                            'source_division' => $sourceDivision,
                            'source_department' => $sourceDepartment,
                            'organization_key' => $organizationKey,
                            'matched_emp_id' => $matchedEmployeeId,
                            'match_status' => $matchedEmployeeId ? 'exact_id' : 'unmatched',
                            'comparison_status' => 'unavailable',
                            'workbook_values' => json_encode($workbookValues),
                            'source_values' => json_encode($sourceValues),
                            'created_at' => now(), 'updated_at' => now(),
                        ];
                        if (count($rows) >= 200) {
                            HistoricalPayrollImportRow::insert($rows);
                            $rows = [];
                        }
                    }
                    if ($rows !== []) {
                        HistoricalPayrollImportRow::insert($rows);
                    }
                    $rowCount = $sheet->rows()->count();
                    if ($rowCount === 0) {
                        $sheet->delete();

                        continue;
                    }
                    $sheet->update([
                        'row_count' => $rowCount,
                        'matched_count' => $sheet->rows()->whereNotNull('matched_emp_id')->count(),
                        'organization_mappings' => $organizationMappings,
                    ]);
                    $worksheetIndex++;
                }

                $progress && $progress(95, 'Saving staged payroll rows');
                $import->update([
                    'sheet_count' => $import->sheets()->count(),
                    'row_count' => $import->sheets()->withCount('rows')->get()->sum('rows_count'),
                    'matched_count' => $import->sheets()->sum('matched_count'),
                ]);

                $progress && $progress(98, 'Preparing mapping workflow');

                return $import->fresh('sheets');
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPath);
            throw $exception;
        }
    }

    public function setSheetIncluded(HistoricalPayrollImportSheet $sheet, bool $included): void
    {
        $sheet->update(['included' => $included]);
        $this->refreshCounts($sheet->import);
    }

    public function mapOrganization(HistoricalPayrollImportSheet $sheet, string $organizationKey, ?int $divisionId, ?int $departmentId, bool $included): void
    {
        $mappings = $sheet->organization_mappings ?? [];
        abort_unless(isset($mappings[$organizationKey]), 404);
        $mappings[$organizationKey] = [
            ...$mappings[$organizationKey],
            'division_id' => $divisionId,
            'department_id' => $departmentId,
            'included' => $included,
        ];
        $sheet->update(['organization_mappings' => $mappings]);
        if ($included && $divisionId) {
            $sheet->rows()->where('organization_key', $organizationKey)->where('match_status', 'exact_id')
                ->update(['matched_emp_id' => null, 'match_status' => 'unmatched', 'comparison_status' => 'unavailable', 'system_values' => null, 'differences' => null]);
            $this->matchEmployees($sheet, $organizationKey);
            $this->reconcileSheet($sheet);
        }
        $this->refreshCounts($sheet->import);
    }

    /** @param array<string,array<string,mixed>> $updates */
    public function mapOrganizations(HistoricalPayrollImportSheet $sheet, array $updates): void
    {
        $mappings = $sheet->organization_mappings ?? [];
        foreach ($updates as $organizationKey => $update) {
            if (! isset($mappings[$organizationKey])) {
                continue;
            }
            $mappings[$organizationKey] = [...$mappings[$organizationKey], ...$update];
        }
        $sheet->update(['organization_mappings' => $mappings]);
        foreach ($updates as $organizationKey => $update) {
            if (($update['included'] ?? false) && ($update['division_id'] ?? null)) {
                $sheet->rows()->where('organization_key', $organizationKey)->where('match_status', 'exact_id')
                    ->update(['matched_emp_id' => null, 'match_status' => 'unmatched', 'comparison_status' => 'unavailable', 'system_values' => null, 'differences' => null]);
                $this->matchEmployees($sheet->fresh(), $organizationKey);
            }
        }
        $this->reconcileSheet($sheet->fresh());
        $this->refreshCounts($sheet->import);
    }

    public function mapEmployee(HistoricalPayrollImportRow $row, string $employeeId): void
    {
        $sheet = $row->sheet;
        $employee = $this->scopedEmployees($sheet, $row->organization_key)->where('emp_id', $employeeId)->firstOrFail();
        $row->update(['matched_emp_id' => $employee->emp_id, 'match_status' => 'manual']);
        $this->reconcileSheet($sheet);
        $this->refreshCounts($sheet->import);
    }

    public function createComparisonDrafts(HistoricalPayrollImport $import, ?string $userId): array
    {
        $sheets = $import->sheets()->where('included', true)->get();
        $draftConfigurations = [];
        foreach ($sheets as $sheet) {
            $draftConfigurations[] = $this->createComparisonDraftForSheet($import, $sheet, $userId);
        }
        $import->update([
            'comparison_draft_id' => data_get($draftConfigurations, '0.draft_id'),
            'comparison_configuration' => data_get($draftConfigurations, '0.configuration'),
            'comparison_drafts' => $draftConfigurations,
        ]);

        return $draftConfigurations;
    }

    private function createComparisonDraftForSheet(HistoricalPayrollImport $import, HistoricalPayrollImportSheet $sheet, ?string $userId): array
    {
        $mappings = collect($sheet->organization_mappings ?? [])->where('included', true);
        $divisionIds = $mappings->pluck('division_id')->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
        $departmentIds = $mappings->pluck('department_id')->filter()->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
        abort_if($divisionIds === [], 422, "Map at least one included organization in {$sheet->sheet_name} before generating its comparison draft.");

        $matchedRows = $sheet->rows()->whereIn('organization_key', $mappings->keys())
            ->whereNotNull('matched_emp_id')->get(['matched_emp_id', 'source_values']);
        $employeeIds = $matchedRows->pluck('matched_emp_id')
            ->map(fn ($id) => (string) $id)->unique()->sort()->values()->all();
        abort_if($employeeIds === [], 422, "No matched employees are available in {$sheet->sheet_name} for its comparison draft.");

        $period = CarbonImmutable::createFromFormat('!Y-m', $import->payroll_period);
        $hints = data_get($sheet->column_map, 'configuration', []);
        $workingDays = (int) ($hints['working_days'] ?? 22);
        $gsisDays = (int) ($hints['gsis_days'] ?? $period->daysInMonth);
        $leaveTypeIds = LeaveType::query()->pluck('leave_type_id')->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => in_array($id, [4, 14, 15, 16, 20], true))->values()->all();
        $leaveMonth = $period->subMonthNoOverflow();
        $employeeType = Employee::EMPLOYEE_TYPE_ALL;
        $scopeConfigurationKey = PayrollGenerationDraft::configurationKeyForScope(
            $divisionIds, $departmentIds, $import->payroll_type_code, $import->payroll_period,
            $workingDays, $employeeType, $gsisDays, $leaveTypeIds,
            $leaveMonth->startOfMonth()->toDateString(), $leaveMonth->endOfMonth()->toDateString(),
        );
        $configurationKey = hash('sha256', $scopeConfigurationKey.'|historical-import|'.$import->id.'|sheet|'.$sheet->id);
        $state = [
            'wizard_step_count' => PayrollGenerationDraft::currentWizardStepCount(),
            'wizard_layout' => PayrollGenerationDraft::WIZARD_LAYOUT,
            'selected_division_ids' => $divisionIds,
            'selected_department_ids' => $departmentIds,
            'employee_filter_ids' => $employeeIds,
            'applied_employee_filter_ids' => $employeeIds,
            'comparison_employee_scope_ids' => $employeeIds,
            'leave_period_start' => $leaveMonth->startOfMonth()->toDateString(),
            'leave_period_end' => $leaveMonth->endOfMonth()->toDateString(),
            ...$this->comparisonDraftInputs($matchedRows),
            'comparison_source' => [
                'type' => 'historical_workbook_comparison',
                'historical_payroll_import_id' => $import->id,
                'historical_payroll_import_sheet_id' => $sheet->id,
                'sheet_name' => $sheet->sheet_name,
                'filename' => $import->original_filename,
                'remarks' => "Generated comparison payroll for worksheet {$sheet->sheet_name}. If finalized, this payroll may be retained as historical payroll for the imported period.",
            ],
        ];

        $draft = PayrollGenerationDraft::query()->updateOrCreate(['configuration_key' => $configurationKey], [
            'division_id' => $divisionIds[0] ?? null,
            'department_id' => count($departmentIds) === 1 ? $departmentIds[0] : null,
            'payroll_type_code' => $import->payroll_type_code,
            'payroll_period' => $import->payroll_period,
            'working_days' => max(1, min(31, $workingDays)),
            'gsis_days' => max(0, min(31, $gsisDays)),
            'included_leave_type_ids' => $leaveTypeIds,
            'employee_type' => $employeeType,
            'current_step' => 7,
            'state_json' => $state,
            'saved_by' => $userId ?? 'historical-import',
            'saved_at' => now(),
        ]);
        return [
            'draft_id' => $draft->id,
            'sheet_id' => $sheet->id,
            'sheet_name' => $sheet->sheet_name,
            'configuration' => [
                'division_ids' => $divisionIds, 'department_ids' => $departmentIds,
                'employee_ids' => $employeeIds, 'working_days' => $workingDays, 'gsis_days' => $gsisDays,
                'leave_type_ids' => $leaveTypeIds, 'employee_type' => $employeeType,
            ],
        ];
    }

    /** Convert workbook inputs into the same state restored by Payroll Generation. */
    private function comparisonDraftInputs($rows): array
    {
        $state = [
            'deduction_day_overrides' => [], 'logbook_lwop_day_overrides' => [],
            'leave_deduction_overrides' => [], 'pay_basis_overrides' => [],
            'compensation_adjustments' => [], 'mandatory_deduction_adjustments' => [],
            'tax_annualization_overrides' => [], 'deduction_program_selections' => [],
            'comparison_loan_overrides' => [], 'other_deduction_remarks' => [],
        ];

        $programs = PayrollDeduction::query()->where('is_active', true)->get()
            ->keyBy(fn (PayrollDeduction $program) => $this->normalizedProgramName($program->name));
        $programColumns = [
            'mmmh mc cooperative' => 'FH', 'death aid' => 'FJ', 'penalty bac' => 'FK',
            'longevity 2009 2010' => 'FL', 'mmsu' => 'FM', 'hdmf ps 2 ms' => 'BH',
            'ea deduction' => 'BJ',
        ];
        $loanColumns = [
            'gsis_emergency' => ['BY'], 'gsis_computer' => ['CC'],
            'gsis_conso' => ['CG', 'DI', 'DM'], 'gsis_policy' => ['CK'],
            'gsis_uoli' => ['FG'], 'gsis_optional' => ['CO', 'CS', 'CW', 'DA', 'DE'],
            'pagibig_mpl' => ['DR'], 'pagibig_calamity' => ['DV'],
            'pagibig_mp2' => ['DZ', 'ED', 'EH', 'EL'], 'dbp' => ['EQ'], 'lbp' => ['EU'],
            'ucpb' => ['EY', 'FC'], 'other_loans' => ['FR', 'FT', 'FV', 'FX', 'FZ'],
        ];

        foreach ($rows as $row) {
            $empId = (string) $row->matched_emp_id;
            $cells = (array) $row->source_values;
            $state['pay_basis_overrides'][$empId] = [
                'salary_grade' => $this->cellNumber($cells, 'R'), 'step' => $this->cellNumber($cells, 'S'),
            ];
            $state['logbook_lwop_day_overrides'][$empId] = $this->cellNumber($cells, 'U');
            $state['deduction_day_overrides'][$empId] = $this->cellNumber($cells, 'V');
            $state['leave_deduction_overrides'][$empId] = [
                'subsistence_days' => $this->cellNumber($cells, 'Z'),
                'laundry_days' => $this->cellNumber($cells, 'Y'), 'pera_days' => 0,
                'tev_days' => $this->cellNumber($cells, 'W'),
            ];
            $state['compensation_adjustments'][$empId] = [
                'basic_salary' => $this->cellNumber($cells, 'AU'),
                'subsistence' => $this->cellNumber($cells, 'AV'),
                'laundry' => $this->cellNumber($cells, 'AW'), 'pera' => $this->cellNumber($cells, 'AX'),
                'remarks' => $this->cellText($cells, 'AY'), 'extra_items' => [],
            ];
            $state['mandatory_deduction_adjustments'][$empId] = [
                'life_retirement' => $this->cellNumber($cells, 'BL'),
                'government_life_retirement' => $this->cellNumber($cells, 'BM'),
                'ec' => $this->cellNumber($cells, 'BN'), 'phic' => $this->cellNumber($cells, 'BO'),
                'government_phic' => $this->cellNumber($cells, 'BP'),
                'mandatory_pagibig' => $this->cellNumber($cells, 'BQ'),
                'government_pagibig' => $this->cellNumber($cells, 'BR'),
            ];
            $state['tax_annualization_overrides'][$empId] = [
                'previous_basic' => $this->cellNumber($cells, 'JG'),
                'previous_hazard' => $this->cellNumber($cells, 'JL'),
                'previous_subsistence' => $this->cellNumber($cells, 'JP'),
                'previous_mandatory_deductions' => $this->cellNumber($cells, 'JT'),
                'previous_tax_withheld' => $this->cellNumber($cells, 'KA'),
                'withholding_tax_adjustment' => $this->cellNumber($cells, 'IM') ?: $this->cellNumber($cells, 'GE'),
            ];
            $state['other_deduction_remarks'][$empId] = $this->cellText($cells, 'FN');

            $columns = collect($loanColumns)->mapWithKeys(fn (array $sourceColumns, string $key) => [
                $key => round(collect($sourceColumns)->sum(fn (string $column) => $this->cellNumber($cells, $column)), 2),
            ])->all();
            $state['comparison_loan_overrides'][$empId] = ['columns' => $columns, 'total' => round(array_sum($columns), 2)];

            foreach ($programColumns as $programName => $column) {
                $program = $programs->get($programName);
                $amount = $this->cellNumber($cells, $column)
                    + ($programName === 'ea deduction' ? $this->cellNumber($cells, 'BS') : 0);
                if (! $program || $amount <= 0) {
                    continue;
                }
                $id = (string) $program->id;
                $state['deduction_program_selections'][$id] ??= [
                    'enabled' => true, 'mode' => 'include', 'employee_ids' => [],
                    'amount_mode' => 'program', 'employee_amounts' => [], 'employee_overrides' => [],
                ];
                $state['deduction_program_selections'][$id]['employee_ids'][] = $empId;
                $state['deduction_program_selections'][$id]['employee_overrides'][$empId] = $amount;
            }
        }

        foreach ($state['deduction_program_selections'] as &$selection) {
            $selection['employee_ids'] = array_values(array_unique($selection['employee_ids']));
        }

        return $state;
    }

    private function cellNumber(array $cells, string $column): float
    {
        $value = data_get($cells, $column.'.value');
        if (is_string($value)) {
            $value = str_replace([',', '₱', ' '], '', $value);
        }

        return is_numeric($value) ? round((float) $value, 4) : 0.0;
    }

    private function cellText(array $cells, string $column): string
    {
        return trim((string) data_get($cells, $column.'.value', ''));
    }

    private function normalizedProgramName(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($name)) ?? '');
    }

    public function reconcileSheet(HistoricalPayrollImportSheet $sheet): void
    {
        $rows = $sheet->rows()->whereNotNull('matched_emp_id')->get();
        if ($rows->isEmpty()) {
            return;
        }
        $records = collect();
        foreach ($rows->groupBy('organization_key') as $organizationKey => $organizationRows) {
            $mapping = ($sheet->organization_mappings ?? [])[$organizationKey] ?? [];
            if (! ($mapping['included'] ?? false) || ! ($mapping['division_id'] ?? null)) {
                continue;
            }
            $batch = PayrollBatch::query()
                ->where('payroll_period', $sheet->import->payroll_period)
                ->where('payroll_type_code', $sheet->import->payroll_type_code)
                ->when($mapping['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
                ->when(! ($mapping['department_id'] ?? null), fn ($query) => $query->where('division_id', $mapping['division_id']))
                ->where('remarks', 'not like', 'Historical Excel import%')
                ->latest('snapshot_created_at')->first();
            if ($batch) {
                $batch->records()->whereIn('emp_id', $organizationRows->pluck('matched_emp_id'))->get()
                    ->each(fn ($record) => $records->put($record->emp_id, $record));
            }
        }

        foreach ($rows as $row) {
            $record = $records->get($row->matched_emp_id);
            if (! $record) {
                $row->update(['comparison_status' => 'unavailable', 'system_values' => null, 'differences' => null]);

                continue;
            }
            $system = $this->systemValues($record);
            $differences = [];
            foreach ($row->workbook_values as $key => $workbookValue) {
                if (! array_key_exists($key, $system) || $system[$key] === null) {
                    continue;
                }
                $delta = round((float) $system[$key] - (float) $workbookValue, 2);
                if (abs($delta) >= 0.01) {
                    $differences[$key] = ['workbook' => (float) $workbookValue, 'system' => (float) $system[$key], 'difference' => $delta];
                }
            }
            $largest = collect($differences)->max(fn ($difference) => abs($difference['difference'])) ?? 0;
            $status = $differences === [] ? 'exact' : ($largest <= 0.05 ? 'rounding' : 'different');
            $row->update(['comparison_status' => $status, 'system_values' => $system, 'differences' => $differences]);
        }
    }

    public function apply(HistoricalPayrollImport $import, ?string $userId): HistoricalPayrollImport
    {
        abort_if($import->status === 'applied', 422, 'This workbook has already been imported.');
        $sheets = $import->sheets()->where('included', true)->get();
        abort_if($sheets->isEmpty(), 422, 'Select at least one payroll sheet.');
        foreach ($sheets as $sheet) {
            $includedMappings = collect($sheet->organization_mappings ?? [])->where('included', true);
            abort_if($includedMappings->isEmpty(), 422, "Include at least one organization from {$sheet->sheet_name}.");
            abort_if($includedMappings->contains(fn ($mapping) => empty($mapping['division_id'])), 422, "Map every included organization in {$sheet->sheet_name} to a division first.");
            abort_if($sheet->rows()->whereIn('organization_key', $includedMappings->keys())->whereNull('matched_emp_id')->exists(), 422, "Resolve every included employee in {$sheet->sheet_name} first.");
        }

        DB::connection('payroll')->transaction(function () use ($import, $sheets, $userId) {
            foreach ($sheets as $sheet) {
                $mappings = collect($sheet->organization_mappings ?? [])->where('included', true);
                $scopeGroups = $mappings->groupBy(fn ($mapping) => $mapping['division_id'].'|'.($mapping['department_id'] ?? ''), true);
                foreach ($scopeGroups as $scopeMappings) {
                    $divisionId = (int) $scopeMappings->first()['division_id'];
                    $departmentId = filled($scopeMappings->first()['department_id'] ?? null) ? (int) $scopeMappings->first()['department_id'] : null;
                    $scopeRows = $sheet->rows()->whereIn('organization_key', $scopeMappings->keys())->get();
                    $batch = PayrollBatch::create([
                        'division_id' => $divisionId,
                        'department_id' => $departmentId,
                        'payroll_period' => $import->payroll_period,
                        'payroll_type' => Str::headline($import->payroll_type_code),
                        'payroll_type_code' => $import->payroll_type_code,
                        'employee_type' => Employee::EMPLOYEE_TYPE_ALL,
                        'generated_by' => $userId ?? 'historical-import',
                        'snapshot_created_at' => now(),
                        'remarks' => "Historical Excel import #{$import->id}; sheet {$sheet->sheet_name}; workbook {$import->original_filename}.",
                    ]);
                    $employees = Employee::query()->with(['department', 'position'])
                        ->whereIn('emp_id', $scopeRows->pluck('matched_emp_id'))->get()->keyBy('emp_id');
                    foreach ($scopeRows as $row) {
                        $values = $row->workbook_values;
                        $employee = $employees->get($row->matched_emp_id);
                        PayrollBatchRecord::create([
                            'payroll_batch_id' => $batch->id,
                            'emp_id' => $row->matched_emp_id,
                            'department_id' => $employee?->department_id ?? $departmentId,
                            'gross' => $values['gross_compensation'] ?? 0,
                            'net' => $values['net_pay'] ?? 0,
                            'fifteenth' => $values['fifteenth'] ?? 0,
                            'thirtieth' => $values['thirtieth'] ?? 0,
                            'snapshot_json' => $this->historicalSnapshot($import, $sheet, $row, $employee),
                        ]);
                    }
                }
            }
            $import->update(['status' => 'applied', 'applied_at' => now()]);
        });

        return $import->fresh();
    }

    private function matchEmployees(HistoricalPayrollImportSheet $sheet, string $organizationKey): void
    {
        $employees = $this->scopedEmployees($sheet, $organizationKey)->get(['emp_id'])->keyBy(fn ($employee) => $this->employeeNumber($employee->emp_id));
        foreach ($sheet->rows()->where('organization_key', $organizationKey)->where('match_status', 'unmatched')->cursor() as $row) {
            $employee = $employees->get($this->employeeNumber($row->source_employee_no));
            if ($employee) {
                $row->update(['matched_emp_id' => $employee->emp_id, 'match_status' => 'exact_id']);
            }
        }
    }

    private function scopedEmployees(HistoricalPayrollImportSheet $sheet, ?string $organizationKey)
    {
        $mapping = ($sheet->organization_mappings ?? [])[$organizationKey] ?? [];

        return Employee::query()->whereHas('department', function ($query) use ($mapping) {
            $query->where('division_id', $mapping['division_id'] ?? 0)
                ->when($mapping['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId));
        });
    }

    private function systemValues(PayrollBatchRecord $record): array
    {
        $snapshot = $record->snapshot_json ?? [];

        return [
            'gross_compensation' => (float) $record->gross,
            'total_mandatory_deductions' => data_get($snapshot, 'totals.total_mandatory_deductions'),
            'total_other_deductions' => data_get($snapshot, 'totals.total_other_deductions'),
            'total_taxable_income' => data_get($snapshot, 'tax.monthly_net_income', data_get($snapshot, 'tax.taxable_income')),
            'withholding_tax_gross' => data_get($snapshot, 'tax.withholding_tax_gross'),
            'withholding_tax_adjustment' => data_get($snapshot, 'tax.withholding_tax_adjustment'),
            'net_pay' => (float) $record->net,
            'fifteenth' => (float) $record->fifteenth,
            'thirtieth' => (float) $record->thirtieth,
        ];
    }

    private function historicalSnapshot(HistoricalPayrollImport $import, HistoricalPayrollImportSheet $sheet, HistoricalPayrollImportRow $row, ?Employee $employee): array
    {
        $values = $row->workbook_values;

        return [
            'source' => ['type' => 'historical_excel_import', 'import_id' => $import->id, 'filename' => $import->original_filename, 'file_hash' => $import->file_hash, 'sheet' => $sheet->sheet_name, 'row' => $row->source_row, 'division' => $row->source_division, 'department' => $row->source_department],
            'employee' => ['emp_id' => $row->matched_emp_id, 'employee_name' => $row->source_employee_name, 'department' => $employee?->department?->department, 'position' => $employee?->position?->position_title],
            'earnings' => ['gross' => $values['gross_compensation'] ?? 0, 'net_compensation' => $values['gross_compensation'] ?? 0],
            'tax' => ['taxable_income' => $values['total_taxable_income'] ?? 0, 'withholding_tax_gross' => $values['withholding_tax_gross'] ?? 0, 'withholding_tax_adjustment' => $values['withholding_tax_adjustment'] ?? 0],
            'totals' => ['gross' => $values['gross_compensation'] ?? 0, 'net_compensation' => $values['gross_compensation'] ?? 0, 'total_mandatory_deductions' => $values['total_mandatory_deductions'] ?? 0, 'total_other_deductions' => $values['total_other_deductions'] ?? 0, 'net_after_loan_deductions' => $values['net_pay'] ?? 0, 'fifteenth' => $values['fifteenth'] ?? 0, 'thirtieth' => $values['thirtieth'] ?? 0],
            'historical_workbook_values' => $values,
            'historical_source_columns' => $row->source_values,
            'reconciliation' => ['status' => $row->comparison_status, 'system_values' => $row->system_values, 'differences' => $row->differences],
        ];
    }

    private function refreshCounts(HistoricalPayrollImport $import): void
    {
        foreach ($import->sheets as $sheet) {
            $sheet->update(['matched_count' => $sheet->rows()->whereNotNull('matched_emp_id')->count(), 'difference_count' => $sheet->rows()->where('comparison_status', 'different')->count()]);
        }
        $import->update(['matched_count' => $import->sheets()->sum('matched_count'), 'difference_count' => $import->sheets()->sum('difference_count')]);
    }

    private function matchOrganization(string $divisionName, string $departmentName): array
    {
        $division = Division::query()->whereRaw('LOWER(TRIM(division)) = ?', [mb_strtolower(trim($divisionName))])->first();
        if (! $division) {
            return ['division_id' => null, 'department_id' => null, 'match_status' => 'unmatched'];
        }
        $department = filled($departmentName)
            ? Department::query()->where('division_id', $division->division_id)
                ->whereRaw('LOWER(TRIM(department)) = ?', [mb_strtolower(trim($departmentName))])->first()
            : null;

        return [
            'division_id' => (int) $division->division_id,
            'department_id' => $department ? (int) $department->department_id : null,
            'match_status' => filled($departmentName) && ! $department ? 'division_only' : 'exact',
        ];
    }

    private function configurationHints(Worksheet $sheet, string $period): array
    {
        $divisors = [];
        $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $lastSampleRow = min($sheet->getHighestDataRow(), 275);
        for ($row = 1; $row <= $lastSampleRow; $row++) {
            for ($column = 1; $column <= $highestColumn; $column++) {
                $formula = $sheet->getCell([$column, $row])->getValue();
                if (! is_string($formula) || ! str_starts_with($formula, '=')) {
                    continue;
                }
                preg_match_all('/\/([12]\d|3[01])(?=[^0-9]|$)/i', $formula, $matches);
                foreach ($matches[1] ?? [] as $divisor) {
                    $divisors[(int) $divisor] = ($divisors[(int) $divisor] ?? 0) + 1;
                }
            }
        }
        $working = collect($divisors)->filter(fn ($count, $days) => $days >= 20 && $days <= 23)->sortDesc()->keys()->first();
        $gsis = collect($divisors)->filter(fn ($count, $days) => $days >= 28 && $days <= 31)->sortDesc()->keys()->first();

        return [
            'period' => $period,
            'working_days' => (int) ($working ?: 22),
            'gsis_days' => (int) ($gsis ?: CarbonImmutable::createFromFormat('!Y-m', $period)->daysInMonth),
            'source' => $working || $gsis ? 'workbook_formulas' : 'payroll_period_defaults',
        ];
    }

    private function headerRow(Worksheet $sheet): ?int
    {
        for ($row = 1; $row <= min(20, $sheet->getHighestDataRow()); $row++) {
            $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            for ($column = 1; $column <= min(30, $highestColumn); $column++) {
                $value = strtoupper(trim((string) $this->value($sheet->getCell([$column, $row]))));
                if (in_array($value, ['EMP NO.', 'EMP NO', 'EMPLOYEE NO.', 'EMPLOYEE ID'], true)) {
                    return $row;
                }
            }
        }

        return null;
    }

    private function hasPayrollOutputs(Worksheet $sheet, int $headerRow): bool
    {
        return collect($this->headers($sheet, $headerRow))->contains(fn ($label) => strtoupper($label) === 'NET PAY');
    }

    /** @param array<string,string> $headers */
    private function metricColumns(array $headers): array
    {
        $labels = [
            'gross_compensation' => ['GROSS COMPENSATION'],
            'total_mandatory_deductions' => ['TOTAL MANDATORY DEDCUTIONS', 'TOTAL MANDATORY DEDUCTIONS'],
            'total_other_deductions' => ['TOTAL OTHER DEDUCTIONS'],
            'total_non_taxable_income' => ['TOTAL NON TAXBLE INCOME', 'TOTAL NON TAXABLE INCOME'],
            'total_taxable_income' => ['TOTAL TAXABLE INCOME'],
            'withholding_tax_gross' => ['WITHHOLDING TAX (GROSS)'],
            'withholding_tax_adjustment' => ['WITHHOLDING TAX (ADJUSTMENT)'],
            'net_pay' => ['NET PAY'],
            'fifteenth' => ['15TH'],
            'thirtieth' => ['30TH'],
            'gross_hazard_pay' => ['GROSS HAZARD PAY'],
            'hazard_adjustment' => ['ADJUSTMENT HAZARD PAY'],
            'net_hazard_pay' => ['NET HAZARD PAY'],
        ];
        $normalized = collect($headers)->map(fn ($label) => strtoupper(trim(preg_replace('/\s+/', ' ', $label))));

        return collect($labels)->mapWithKeys(function (array $accepted, string $key) use ($normalized) {
            $column = $normalized->search(fn ($label) => in_array($label, $accepted, true));

            return $column === false ? [] : [$key => $column];
        })->all();
    }

    private function headers(Worksheet $sheet, int $headerRow): array
    {
        $headers = [];
        $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        for ($column = 1; $column <= $highestColumn; $column++) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column);
            $label = trim(preg_replace('/\s+/', ' ', (string) $this->value($sheet->getCell($letter.$headerRow))));
            if ($label !== '') {
                $headers[$letter] = $label;
            }
        }

        return $headers;
    }

    private function value(Cell $cell): mixed
    {
        if ($cell->isFormula()) {
            $cached = $cell->getOldCalculatedValue();
            if ($cached !== null) {
                return $cached;
            }
            try {
                return $cell->getCalculatedValue();
            } catch (\Throwable) {
                return null;
            }
        }

        return $cell->getValue();
    }

    private function employeeNumber(mixed $value): string
    {
        $value = trim((string) $value);

        return ctype_digit($value) ? str_pad($value, 6, '0', STR_PAD_LEFT) : $value;
    }

    private function organizationKey(string $division, string $department): string
    {
        return sha1(mb_strtolower(trim($division)).'|'.mb_strtolower(trim($department)));
    }

    private function number(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace([',', '₱', ' '], '', $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }
}
