<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = 'payroll_scheduler';

        Schema::connection($connection)->create('schedule_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->index();
            $table->string('code', 40);
            $table->string('name');
            $table->string('unit_type', 40)->default('section');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['department_id', 'code']);
        });

        Schema::connection($connection)->create('schedule_user_units', function (Blueprint $table) {
            $table->id();
            $table->string('emp_id')->index();
            $table->foreignId('schedule_unit_id')->constrained('schedule_units')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['emp_id', 'schedule_unit_id']);
        });

        Schema::connection($connection)->table('schedule_assignments', function (Blueprint $table) {
            $table->foreignId('unit_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('schedule_units')
                ->nullOnDelete();
            $table->index('unit_id');
        });

        Schema::connection($connection)->table('employee_schedule_settings', function (Blueprint $table) {
            $table->foreignId('default_unit_id')
                ->nullable()
                ->after('default_shift_code_id')
                ->constrained('schedule_units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $connection = 'payroll_scheduler';

        Schema::connection($connection)->table('employee_schedule_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_unit_id');
        });

        Schema::connection($connection)->table('schedule_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
        });

        Schema::connection($connection)->dropIfExists('schedule_user_units');
        Schema::connection($connection)->dropIfExists('schedule_units');
    }
};
