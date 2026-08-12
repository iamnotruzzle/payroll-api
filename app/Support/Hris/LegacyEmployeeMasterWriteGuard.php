<?php

namespace App\Support\Hris;

use RuntimeException;

/**
 * Blocks writes to legacy employee master / PDS section tables after cutover freeze.
 *
 * Scope: tbl_employee core + PDS section tables (dependents, education, etc.)
 * used by EmployeeProfileWriteService / EmployeePdsSectionService when
 * HRIS_USE_V2 is false (or any path that still calls legacy writers).
 *
 * Out of scope (intentionally not frozen): leave credits / leave requests,
 * TARF, IPCR, DTR punches, VL/SL columns updated by leave accrual.
 */
class LegacyEmployeeMasterWriteGuard
{
    public static function assertWritable(string $context = 'legacy employee master'): void
    {
        if (! self::isFrozen()) {
            return;
        }

        throw new RuntimeException(
            'Legacy employee-master writes are frozen (HRIS_FREEZE_LEGACY_WRITES=true). '
            ."Use hris_v2 with HRIS_USE_V2=true for {$context}. "
            .'Leave, training, IPCR, and DTR on intentional legacy tables are unaffected. '
            .'See docs/hris-cutover.md.'
        );
    }

    public static function isFrozen(): bool
    {
        return (bool) config('hris.freeze_legacy_writes', false);
    }
}
