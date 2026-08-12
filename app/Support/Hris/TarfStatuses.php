<?php

namespace App\Support\Hris;

/**
 * Legacy tbl_training_details.status codes (from reference HRIS TrainingController).
 */
final class TarfStatuses
{
    public const PENDING_PETU = 0;

    public const PENDING_MCC = 1;

    public const APPROVED = 2;

    public const APPROVED_OT = 3;

    public const COMPLETED = 4;

    public const DISAPPROVED_MCC = 5;

    public const DISAPPROVED_PETU = 6;

    public const CANCELLED = 7;

    /**
     * @return array<int, string>
     */
    public static function labels(): array
    {
        return [
            self::PENDING_PETU => 'Pending PETU',
            self::PENDING_MCC => 'Pending MCC',
            self::APPROVED => 'Approved',
            self::APPROVED_OT => 'Approved (OT)',
            self::COMPLETED => 'Completed',
            self::DISAPPROVED_MCC => 'Disapproved (MCC)',
            self::DISAPPROVED_PETU => 'Disapproved (PETU)',
            self::CANCELLED => 'Cancelled',
        ];
    }

    public static function nameFor(?int $status): string
    {
        if ($status === null) {
            return 'Unknown';
        }

        return self::labels()[$status] ?? "Status {$status}";
    }

    public static function keyFor(?int $status): string
    {
        return match ($status) {
            self::PENDING_PETU, self::PENDING_MCC => 'pending',
            self::APPROVED, self::APPROVED_OT => 'approved',
            self::COMPLETED => 'completed',
            self::DISAPPROVED_MCC, self::DISAPPROVED_PETU => 'disapproved',
            self::CANCELLED => 'cancelled',
            default => 'other',
        };
    }

    /**
     * @return list<int>
     */
    public static function idsFor(string $filter): array
    {
        return match ($filter) {
            'pending' => [self::PENDING_PETU, self::PENDING_MCC],
            'approved' => [self::APPROVED, self::APPROVED_OT],
            'completed' => [self::COMPLETED],
            'disapproved' => [self::DISAPPROVED_MCC, self::DISAPPROVED_PETU],
            'cancelled' => [self::CANCELLED],
            default => [],
        };
    }

    /**
     * @return list<int>
     */
    public static function approvalQueueIds(): array
    {
        return [self::PENDING_PETU, self::PENDING_MCC];
    }
}
