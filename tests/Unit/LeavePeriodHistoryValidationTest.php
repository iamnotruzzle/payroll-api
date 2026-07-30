<?php

namespace Tests\Unit;

use App\Livewire\Payroll\PayrollGeneration;
use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\LeaveType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class LeavePeriodHistoryValidationTest extends TestCase
{
    public function test_previously_finalized_leave_dates_are_excluded_from_the_current_calculation(): void
    {
        $leave = new EmployeeLeave([
            'emp_id' => '000742',
            'leave_type' => 1,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-03',
            'days_wpay' => 3,
            'days_wopay' => 0,
            'status' => 0,
        ]);
        $leave->leave_id = 99;
        $leave->setRelation('leaveType', new LeaveType(['leave_name' => 'Vacation Leave']));

        $processed = collect([
            '99' => collect([
                '2026-06-02' => [
                    'payroll_run_id' => 12,
                    'payroll_batch_id' => 8,
                    'payroll_period' => '2026-05',
                ],
            ]),
        ]);

        $component = new PayrollGeneration;
        $method = new ReflectionMethod(PayrollGeneration::class, 'leaveDeductionDetails');
        $result = $method->invoke(
            $component,
            new Collection([$leave]),
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-03'),
            $processed,
        );

        $this->assertSame(2, $result['calendar_days']);
        $this->assertSame(['2026-06-01', '2026-06-03'], $result['items'][0]['included_dates']);
        $this->assertTrue($result['items'][0]['already_processed']);
        $this->assertFalse($result['items'][0]['fully_processed']);
        $this->assertSame('2026-06-02', $result['items'][0]['processed_dates'][0]['date']);
    }
}
