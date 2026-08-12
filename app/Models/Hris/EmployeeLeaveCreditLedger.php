<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveCreditLedger extends Model
{
    public const BUCKET_VL = 'VL';

    public const BUCKET_SL = 'SL';

    public const SOURCE_OPENING = 'opening';

    public const SOURCE_APPLY = 'apply';

    public const SOURCE_RESTORE = 'restore';

    public const SOURCE_ACCRUAL = 'accrual';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_LWOP = 'lwop';

    protected $connection = 'hris';

    protected $table = 'employee_leave_credit_ledger';

    protected $fillable = [
        'emp_id',
        'bucket',
        'delta',
        'balance_after',
        'effective_date',
        'source',
        'leave_id',
        'leave_log_id',
        'remarks',
        'recorded_by_emp_id',
    ];

    protected function casts(): array
    {
        return [
            'delta' => 'float',
            'balance_after' => 'float',
            'effective_date' => 'date',
            'leave_id' => 'integer',
            'leave_log_id' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sourceLabels(): array
    {
        return [
            self::SOURCE_OPENING => 'Opening balance',
            self::SOURCE_APPLY => 'Leave apply',
            self::SOURCE_RESTORE => 'Leave restore',
            self::SOURCE_ACCRUAL => 'Monthly accrual',
            self::SOURCE_MANUAL => 'Manual adjustment',
            self::SOURCE_LWOP => 'LWOP / paydown',
        ];
    }

    public function getSourceLabelAttribute(): string
    {
        return self::sourceLabels()[$this->source] ?? (string) $this->source;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'emp_id', 'emp_id');
    }

    public function leave(): BelongsTo
    {
        return $this->belongsTo(EmployeeLeave::class, 'leave_id', 'leave_id');
    }

    public function leaveLog(): BelongsTo
    {
        return $this->belongsTo(EmployeeLeaveLog::class, 'leave_log_id', 'log_id');
    }
}
