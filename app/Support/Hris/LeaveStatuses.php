<?php

namespace App\Support\Hris;

use App\Models\Hris\LeaveStatusLookup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class LeaveStatuses
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DISAPPROVED = 'disapproved';

    public const CANCELLED = 'cancelled';

    /**
     * @return Collection<int, LeaveStatusLookup>
     */
    public static function all(): Collection
    {
        return Cache::remember('hris.leave_statuses', 300, function () {
            return LeaveStatusLookup::query()
                ->orderBy('status_id')
                ->get(['status_id', 'status_name']);
        });
    }

    public static function idFor(string $key): ?int
    {
        $needles = match ($key) {
            self::PENDING => ['pending', 'filed', 'for approval', 'forapproval', 'submitted'],
            self::APPROVED => ['approved', 'approve'],
            self::DISAPPROVED => ['disapproved', 'disapprove', 'rejected', 'denied'],
            self::CANCELLED => ['cancelled', 'canceled', 'cancel'],
            default => [$key],
        };

        $statuses = self::all();

        foreach ($needles as $needle) {
            $match = $statuses->first(function (LeaveStatusLookup $status) use ($needle) {
                return str_contains(strtolower((string) $status->status_name), strtolower($needle));
            });

            if ($match) {
                return (int) $match->status_id;
            }
        }

        // Live HRIS defaults: 0 Pending, 1 Approved, 2 Disapproved, 3 Canceled, 4 Gain, 5/6 Update
        return match (strtolower($key)) {
            self::PENDING, 'pending' => 0,
            self::APPROVED, 'approved' => 1,
            self::DISAPPROVED, 'disapproved' => 2,
            self::CANCELLED, 'cancelled', 'canceled' => 3,
            'gain' => 4,
            'update (credit)', 'credit' => 5,
            'update (debit)', 'debit' => 6,
            default => null,
        };
    }

    public static function nameFor(?int $statusId): string
    {
        if ($statusId === null) {
            return 'Unknown';
        }

        $status = self::all()->firstWhere('status_id', $statusId);

        return $status?->status_name ?: "Status #{$statusId}";
    }

    public static function keyFor(?int $statusId): string
    {
        $name = strtolower(self::nameFor($statusId));

        if (str_contains($name, 'cancel')) {
            return self::CANCELLED;
        }
        if (str_contains($name, 'disapprove') || str_contains($name, 'reject') || str_contains($name, 'denied')) {
            return self::DISAPPROVED;
        }
        if (str_contains($name, 'approve')) {
            return self::APPROVED;
        }

        return self::PENDING;
    }

    /**
     * @return list<int>
     */
    public static function idsFor(string $key): array
    {
        $id = self::idFor($key);

        return $id === null ? [] : [$id];
    }
}
