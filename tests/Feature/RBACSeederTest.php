<?php

namespace Tests\Feature;

use App\Models\Hris\UserAccount;
use Database\Seeders\RBACSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RBACSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['hris', 'payroll_scheduler'] as $connection) {
            Config::set("database.connections.{$connection}", [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ]);

            DB::purge($connection);
        }

        Config::set('database.default', 'payroll_scheduler');

        $this->createPermissionTables();
        $this->createUserAccountTable();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_rbac_seeder_assigns_designated_employee_roles(): void
    {
        foreach (['001783', '000720', '000825', '001866', '001555', '000035', '000822', '002039', '002205'] as $employeeId) {
            UserAccount::query()->create([
                'emp_id' => $employeeId,
                'username' => "user{$employeeId}",
                'password' => 'secret',
            ]);
        }

        $this->seed(RBACSeeder::class);

        $this->assertSame(['super-admin'], $this->rolesFor('001783'));

        foreach (['000720', '000825', '001866', '001555', '000035', '000822'] as $employeeId) {
            $this->assertSame(['hr-payroll'], $this->rolesFor($employeeId));
        }

        foreach (['002039', '002205'] as $employeeId) {
            $this->assertSame(['accounting-payroll'], $this->rolesFor($employeeId));
        }
    }

    private function rolesFor(string $employeeId): array
    {
        return UserAccount::query()
            ->where('emp_id', $employeeId)
            ->firstOrFail()
            ->roles()
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    private function createPermissionTables(): void
    {
        Schema::connection('hris')->create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection('hris')->create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection('hris')->create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::connection('hris')->create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::connection('hris')->create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });
    }

    private function createUserAccountTable(): void
    {
        Schema::connection('hris')->create('tbl_useraccount', function (Blueprint $table) {
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
    }
}
