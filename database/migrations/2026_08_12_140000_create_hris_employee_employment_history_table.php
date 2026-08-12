<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'hris';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('employee_employment_history')) {
            return;
        }

        Schema::connection($this->connection)->create('employee_employment_history', function (Blueprint $table) {
            $table->id();
            $table->string('emp_id', 32)->index();
            $table->date('effective_from');
            $table->date('effective_to')->nullable()->index();
            $table->string('item_number', 64)->nullable()->index();
            $table->unsignedInteger('position_id')->nullable()->index();
            $table->unsignedInteger('department_id')->nullable()->index();
            $table->unsignedInteger('empstat_id')->nullable()->index();
            $table->unsignedSmallInteger('step')->nullable();
            $table->unsignedSmallInteger('salary_grade')->nullable();
            $table->string('nature', 32)->default('original')->index();
            $table->text('remarks')->nullable();
            $table->string('recorded_by_emp_id', 32)->nullable();
            $table->timestamps();

            $table->index(['emp_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('employee_employment_history');
    }
};
