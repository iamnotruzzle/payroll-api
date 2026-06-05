<?php

namespace Tests\Unit;

use App\Support\Rbac\LegacyHrisRoleMapper;
use PHPUnit\Framework\TestCase;

class LegacyHrisRoleMapperTest extends TestCase
{
    private LegacyHrisRoleMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new LegacyHrisRoleMapper;
    }

    public function test_privileged_legacy_values_map_to_single_top_level_roles(): void
    {
        $this->assertSame(['super-admin'], $this->mapper->rolesFor(1, 4));
        $this->assertSame(['super-admin'], $this->mapper->rolesFor(4, 1));
        $this->assertSame(['admin'], $this->mapper->rolesFor(2, 4));
        $this->assertSame(['admin'], $this->mapper->rolesFor(4, 2));
        $this->assertSame(['super-admin'], $this->mapper->rolesFor(5, 5, '001783'));
    }

    public function test_operational_legacy_values_combine_schedule_payroll_and_timekeeping_roles(): void
    {
        $this->assertSame(
            ['scheduler', 'schedule-approver', 'payroll-processor', 'timekeeper'],
            $this->mapper->rolesFor(3, 4),
        );

        $this->assertSame(['scheduler', 'payroll-approver'], $this->mapper->rolesFor(4, 3));
        $this->assertSame(['timekeeper'], $this->mapper->rolesFor(null, 5));
    }

    public function test_unknown_legacy_values_fall_back_to_employee_and_are_reported(): void
    {
        $this->assertSame(['employee'], $this->mapper->rolesFor(99, null));
        $this->assertSame(['employee'], $this->mapper->rolesFor(null, 99));
        $this->assertSame(['scheduler'], $this->mapper->rolesFor(4, 99));

        $this->assertSame(
            ['user_level' => 99, 'pims_role' => 98],
            $this->mapper->unmappedValues(99, 98),
        );
    }
}
