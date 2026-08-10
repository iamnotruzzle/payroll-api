<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'hris_v2';

    public function up(): void
    {
        if (! Schema::connection($this->connection)->hasTable('employee_dependents')) {
            Schema::connection($this->connection)->create('employee_dependents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('firstname');
                $table->string('middlename')->nullable();
                $table->string('lastname');
                $table->string('extension', 32)->nullable();
                $table->string('relationship', 64)->nullable()->index();
                $table->date('birthdate')->nullable();
                $table->string('sex', 16)->nullable();
                $table->string('occupation')->nullable();
                $table->string('employer_name')->nullable();
                $table->string('employer_address')->nullable();
                $table->string('telephone_no', 64)->nullable();
                $table->unsignedBigInteger('legacy_dependent_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::connection($this->connection)->hasTable('employee_educations')) {
            Schema::connection($this->connection)->create('employee_educations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('education_level', 64)->nullable()->index();
                $table->string('education_title')->nullable();
                $table->string('school')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->decimal('units', 8, 2)->nullable();
                $table->string('year_graduated', 16)->nullable();
                $table->string('honors')->nullable();
                $table->string('url')->nullable();
                $table->unsignedBigInteger('legacy_education_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::connection($this->connection)->hasTable('employee_eligibilities')) {
            Schema::connection($this->connection)->create('employee_eligibilities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->unsignedBigInteger('eligibility_lookup_id')->nullable()->index();
                $table->string('title')->nullable();
                $table->date('confer_date')->nullable();
                $table->string('confer_place')->nullable();
                $table->decimal('rating', 8, 2)->nullable();
                $table->string('license_no', 64)->nullable();
                $table->date('exp_date')->nullable();
                $table->unsignedBigInteger('legacy_eligibility_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::connection($this->connection)->hasTable('employee_work_experiences')) {
            Schema::connection($this->connection)->create('employee_work_experiences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('work_position')->nullable();
                $table->string('work_status')->nullable();
                $table->string('company_name')->nullable();
                $table->string('company_address')->nullable();
                $table->decimal('salary', 12, 2)->nullable();
                $table->string('salary_grade', 32)->nullable();
                $table->unsignedTinyInteger('step_inc')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->boolean('is_government')->default(false);
                $table->unsignedBigInteger('legacy_work_exp_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::connection($this->connection)->hasTable('employee_trainings')) {
            Schema::connection($this->connection)->create('employee_trainings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('training_name')->nullable();
                $table->string('training_venue')->nullable();
                $table->string('sponsor')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->decimal('hours', 8, 2)->nullable();
                $table->unsignedBigInteger('type_id')->nullable()->index();
                $table->string('type_name')->nullable();
                $table->string('url')->nullable();
                $table->unsignedBigInteger('legacy_training_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::connection($this->connection)->hasTable('employee_voluntary_works')) {
            Schema::connection($this->connection)->create('employee_voluntary_works', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('organization_name')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->decimal('hours', 8, 2)->nullable();
                $table->string('position')->nullable();
                $table->unsignedBigInteger('legacy_volwork_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::connection($this->connection)->hasTable('employee_other_infos')) {
            Schema::connection($this->connection)->create('employee_other_infos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('title')->nullable();
                $table->string('type', 64)->nullable()->index();
                $table->unsignedBigInteger('legacy_otherinfo_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::connection($this->connection)->hasTable('employee_character_references')) {
            Schema::connection($this->connection)->create('employee_character_references', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('name')->nullable();
                $table->string('address')->nullable();
                $table->string('telephone_no', 64)->nullable();
                $table->unsignedBigInteger('legacy_reference_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('employee_character_references');
        Schema::connection($this->connection)->dropIfExists('employee_other_infos');
        Schema::connection($this->connection)->dropIfExists('employee_voluntary_works');
        Schema::connection($this->connection)->dropIfExists('employee_trainings');
        Schema::connection($this->connection)->dropIfExists('employee_work_experiences');
        Schema::connection($this->connection)->dropIfExists('employee_eligibilities');
        Schema::connection($this->connection)->dropIfExists('employee_educations');
        Schema::connection($this->connection)->dropIfExists('employee_dependents');
    }
};
