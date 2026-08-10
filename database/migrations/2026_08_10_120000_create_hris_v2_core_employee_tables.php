<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'hris_v2';

    public function up(): void
    {
        Schema::connection($this->connection)->create('hris_migration_runs', function (Blueprint $table) {
            $table->id();
            $table->string('batch_key', 64)->unique();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('source_employee_count')->default(0);
            $table->unsignedInteger('migrated_employee_count')->default(0);
            $table->unsignedInteger('source_section_count')->default(0);
            $table->unsignedInteger('migrated_section_count')->default(0);
            $table->json('checksums')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('emp_id', 32)->unique();
            $table->string('firstname');
            $table->string('middlename')->nullable();
            $table->string('lastname');
            $table->string('extension', 32)->nullable();
            $table->string('prefix', 32)->nullable();
            $table->string('suffix', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_external')->default(false);
            $table->date('date_hired')->nullable();
            $table->date('date_separated')->nullable();
            $table->string('separation_reason')->nullable();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('employee_personals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('birthdate')->nullable();
            $table->string('birthplace')->nullable();
            $table->string('sex', 16)->nullable();
            $table->string('civil_status', 32)->nullable();
            $table->string('citizenship')->nullable();
            $table->string('religion')->nullable();
            $table->string('blood_type', 8)->nullable();
            $table->text('residential_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->timestamps();

            $table->unique('employee_id');
        });

        Schema::connection($this->connection)->create('employee_government_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('tin_no', 64)->nullable();
            $table->string('gsis_no', 64)->nullable();
            $table->string('pagibig_no', 64)->nullable();
            $table->string('phic_no', 64)->nullable();
            $table->string('sss_no', 64)->nullable();
            $table->timestamps();

            $table->unique('employee_id');
        });

        Schema::connection($this->connection)->create('employee_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('mobile_no', 64)->nullable();
            $table->string('telephone_no', 64)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_no', 64)->nullable();
            $table->timestamps();

            $table->unique('employee_id');
        });

        Schema::connection($this->connection)->create('employment_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->unsignedBigInteger('division_id')->nullable()->index();
            $table->unsignedBigInteger('position_id')->nullable()->index();
            $table->unsignedBigInteger('employment_status_id')->nullable()->index();
            $table->unsignedTinyInteger('step')->nullable();
            $table->boolean('is_section_head')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_current')->default(true)->index();
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('legacy_record_maps', function (Blueprint $table) {
            $table->id();
            $table->string('source_table', 128);
            $table->string('source_key', 64);
            $table->string('target_table', 128);
            $table->unsignedBigInteger('target_id');
            $table->string('emp_id', 32)->nullable()->index();
            $table->string('checksum', 64)->nullable();
            $table->foreignId('migration_run_id')->nullable()->constrained('hris_migration_runs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_table', 'source_key', 'target_table']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('legacy_record_maps');
        Schema::connection($this->connection)->dropIfExists('employment_assignments');
        Schema::connection($this->connection)->dropIfExists('employee_contacts');
        Schema::connection($this->connection)->dropIfExists('employee_government_ids');
        Schema::connection($this->connection)->dropIfExists('employee_personals');
        Schema::connection($this->connection)->dropIfExists('employees');
        Schema::connection($this->connection)->dropIfExists('hris_migration_runs');
    }
};
