<?php

namespace Tests\Feature;

use App\Models\Hris\UserAccount;
use App\Services\Rbac\LegacyHrisRbacBackfill;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LegacyHrisRbacBackfillTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.mysql', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        Config::set('database.default', 'mysql');

        DB::purge('mysql');

        Schema::connection('mysql')->dropIfExists('tbl_useraccount');
        Schema::connection('mysql')->dropIfExists('model_has_roles');
        Schema::connection('mysql')->dropIfExists('roles');

        Schema::connection('mysql')->create('tbl_useraccount', function (Blueprint $table) {
            $table->increments('userid');
            $table->string('emp_id')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->integer('login_attempt')->nullable();
            $table->integer('user_level')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('pims_role')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('mysql')->create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection('mysql')->create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        foreach (['scheduler', 'payroll-processor', 'timekeeper', 'employee'] as $name) {
            Role::query()->create(['name' => $name, 'guard_name' => 'web']);
        }
    }

    public function test_preview_reports_assignments_without_persisting_them(): void
    {
        UserAccount::query()->create([
            'emp_id' => 'A001',
            'username' => 'scheduler-payroll',
            'password' => 'secret',
            'user_level' => 4,
            'pims_role' => 4,
        ]);

        $summary = app(LegacyHrisRbacBackfill::class)->preview();

        $this->assertSame(1, $summary['eligible']);
        $this->assertSame(0, $summary['updated']);
        $this->assertSame(['scheduler', 'payroll-processor', 'timekeeper'], $summary['assignments'][0]['roles']);
        $this->assertFalse(UserAccount::query()->first()->hasRole('scheduler'));
    }

    public function test_apply_skips_accounts_that_already_have_roles_by_default(): void
    {
        $account = UserAccount::query()->create([
            'emp_id' => 'A002',
            'username' => 'explicit',
            'password' => 'secret',
            'user_level' => 4,
            'pims_role' => 4,
        ]);
        $account->assignRole('employee');

        $summary = app(LegacyHrisRbacBackfill::class)->apply();

        $account->refresh();

        $this->assertSame(1, $summary['skipped_existing_roles']);
        $this->assertSame(0, $summary['updated']);
        $this->assertTrue($account->hasRole('employee'));
        $this->assertFalse($account->hasRole('scheduler'));
    }
}
