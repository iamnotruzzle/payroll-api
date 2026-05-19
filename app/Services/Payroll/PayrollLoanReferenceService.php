<?php

namespace App\Services\Payroll;

use App\Models\Payroll\PayrollLoanEntity;
use App\Models\Payroll\PayrollLoanImportItem;
use App\Models\Payroll\PayrollLoanType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PayrollLoanReferenceService
{
    public const DEFAULT_ENTITIES = ['GSIS', 'PAG-IBIG', 'UCPB', 'DBP', 'LBP', 'COCO', 'MMMHCOOP', 'OTHER'];

    public const DEFAULT_COLUMN_GROUPS = [
        'GSIS' => [
            'gsis_emergency' => 'Emergency Loan',
            'gsis_computer' => 'Computer Loan',
            'gsis_conso' => 'Conso / MPL',
            'gsis_policy' => 'Policy Loan',
            'gsis_uoli' => 'UOLI Prem.',
            'gsis_optional' => 'Optional / AJ',
        ],
        'Pag-IBIG' => [
            'pagibig_mpl' => 'MPL',
            'pagibig_calamity' => 'Calamity',
            'pagibig_mp2' => 'MP2',
        ],
        'Bank Loans' => [
            'ucpb' => 'UCPB',
            'dbp' => 'DBP',
            'lbp' => 'LBP',
            'coco' => 'COCO',
            'other_loans' => 'Other Loans',
        ],
        'Other Deductions' => [
            'mmmh_coop' => 'MMMH Coop',
            'death_aid' => 'Death Aid',
            'ea_monthly_dues' => 'EA Monthly Dues',
        ],
    ];

    public function entityCodes(): array
    {
        if (! $this->referenceTablesExist()) {
            return self::DEFAULT_ENTITIES;
        }

        $codes = PayrollLoanEntity::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->pluck('code')
            ->all();

        return $codes ?: self::DEFAULT_ENTITIES;
    }

    public function typeNames(): array
    {
        if (! $this->referenceTablesExist()) {
            return collect(self::DEFAULT_COLUMN_GROUPS)->flatMap(fn ($columns) => $columns)->values()->all();
        }

        return PayrollLoanType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function typeNamesForEntity(string $entityCode): array
    {
        if (! $this->referenceTablesExist()) {
            return match (strtoupper($entityCode)) {
                'GSIS' => array_values(self::DEFAULT_COLUMN_GROUPS['GSIS']),
                'PAG-IBIG', 'PAGIBIG' => array_values(self::DEFAULT_COLUMN_GROUPS['Pag-IBIG']),
                'UCPB' => ['UCPB'],
                'DBP' => ['DBP'],
                'LBP' => ['LBP'],
                'COCO' => ['COCO'],
                'MMMHCOOP', 'MMMH COOP' => ['MMMH Coop', 'EA Monthly Dues'],
                'OTHER' => ['Other Loans', 'Death Aid', 'EA Monthly Dues'],
                default => ['Other Loans'],
            };
        }

        return PayrollLoanType::query()
            ->where('is_active', true)
            ->whereHas('entity', fn ($query) => $query->where('code', $entityCode))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function columnGroups(): array
    {
        if (! $this->referenceTablesExist()) {
            return self::DEFAULT_COLUMN_GROUPS;
        }

        $groups = PayrollLoanType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('review_group')
            ->map(fn (Collection $types) => $types
                ->mapWithKeys(fn (PayrollLoanType $type) => [$type->review_column_key => $type->review_column_label])
                ->all())
            ->all();

        return $groups ?: self::DEFAULT_COLUMN_GROUPS;
    }

    public function columnKeyFor(PayrollLoanImportItem $item): string
    {
        $entity = strtoupper((string) $item->entity);
        $typeText = strtoupper((string) $item->loan_type.' '.$item->loan_account_no.' '.$item->remarks);
        $types = $this->loanTypesForEntity($entity);

        foreach ($types as $type) {
            foreach (($type->match_keywords ?: []) as $keyword) {
                if ($keyword !== '' && str_contains($typeText, strtoupper($keyword))) {
                    return $type->review_column_key;
                }
            }
        }

        return $types->first()?->review_column_key
            ?? match ($entity) {
                'UCPB' => 'ucpb',
                'DBP' => 'dbp',
                'LBP' => 'lbp',
                'COCO' => 'coco',
                'MMMHCOOP', 'MMMH COOP' => 'mmmh_coop',
                'EA', 'EMPLOYEES ASSOCIATION' => 'ea_monthly_dues',
                default => str_contains($typeText, 'DEATH AID') ? 'death_aid' : 'other_loans',
            };
    }

    private function loanTypesForEntity(string $entity): Collection
    {
        if (! $this->referenceTablesExist()) {
            return collect();
        }

        return PayrollLoanType::query()
            ->where('is_active', true)
            ->whereHas('entity', fn ($query) => $query->where('code', $entity)->orWhere('name', $entity))
            ->orderBy('sort_order')
            ->get();
    }

    private function referenceTablesExist(): bool
    {
        return Schema::connection('payroll')->hasTable('payroll_loan_entities')
            && Schema::connection('payroll')->hasTable('payroll_loan_types');
    }
}
