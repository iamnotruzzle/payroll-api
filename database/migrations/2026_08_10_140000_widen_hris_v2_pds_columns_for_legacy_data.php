<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'hris_v2';

    public function up(): void
    {
        Schema::connection($this->connection)->table('employee_trainings', function (Blueprint $table) {
            $table->text('training_name')->nullable()->change();
            $table->text('training_venue')->nullable()->change();
            $table->text('sponsor')->nullable()->change();
        });

        Schema::connection($this->connection)->table('employee_work_experiences', function (Blueprint $table) {
            $table->string('company_address', 1000)->nullable()->change();
            $table->smallInteger('step_inc')->nullable()->change();
        });

        Schema::connection($this->connection)->table('employee_dependents', function (Blueprint $table) {
            $table->string('employer_address', 1000)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('employee_trainings', function (Blueprint $table) {
            $table->string('training_name')->nullable()->change();
            $table->string('training_venue')->nullable()->change();
            $table->string('sponsor')->nullable()->change();
        });

        Schema::connection($this->connection)->table('employee_work_experiences', function (Blueprint $table) {
            $table->string('company_address')->nullable()->change();
            $table->unsignedTinyInteger('step_inc')->nullable()->change();
        });

        Schema::connection($this->connection)->table('employee_dependents', function (Blueprint $table) {
            $table->string('employer_address')->nullable()->change();
        });
    }
};
