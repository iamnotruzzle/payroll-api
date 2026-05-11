<?php

namespace App\Services\Schedule;

use App\Models\Schedule\ScheduleAuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    public function record(string $action, Model|string $target, ?array $before = null, ?array $after = null, ?string $performedBy = null): ScheduleAuditLog
    {
        return ScheduleAuditLog::create([
            'auditable_type' => is_string($target) ? $target : $target::class,
            'auditable_id' => is_string($target) ? null : $target->getKey(),
            'action' => $action,
            'before_values' => $before,
            'after_values' => $after,
            'performed_by' => $performedBy,
            'performed_at' => now(),
        ]);
    }
}
