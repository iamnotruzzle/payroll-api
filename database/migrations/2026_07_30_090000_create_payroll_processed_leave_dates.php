<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->create('payroll_processed_leave_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_generates')->cascadeOnDelete();
            $table->foreignId('payroll_batch_id')->constrained('payroll_batches')->cascadeOnDelete();
            $table->string('emp_id', 80)->index();
            $table->unsignedBigInteger('leave_id');
            $table->date('leave_date');
            $table->string('processed_by', 80)->nullable();
            $table->timestamps();

            $table->unique(['leave_id', 'leave_date'], 'payroll_processed_leave_date_unique');
            $table->index(['emp_id', 'leave_date']);
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->dropIfExists('payroll_processed_leave_dates');
    }
};
