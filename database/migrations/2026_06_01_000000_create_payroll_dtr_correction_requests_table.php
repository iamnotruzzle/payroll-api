<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->create('payroll_dtr_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->string('emp_id')->index();
            $table->unsignedBigInteger('department_id')->index();
            $table->date('dtr_date')->index();
            $table->string('request_type', 16);
            $table->time('requested_time_in')->nullable();
            $table->time('requested_time_out')->nullable();
            $table->boolean('requested_timeout_nextday')->default(false);
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_mime_type', 100)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->string('status', 16)->default('PENDING')->index();
            $table->string('requested_by_emp_id')->index();
            $table->timestamp('requested_at')->useCurrent();
            $table->string('approver_emp_id')->index();
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by_emp_id')->nullable()->index();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_by_emp_id')->nullable()->index();
            $table->text('approver_remarks')->nullable();
            $table->json('previous_dtr')->nullable();
            $table->json('applied_dtr')->nullable();
            $table->timestamps();

            $table->index(['emp_id', 'dtr_date']);
            $table->index(['department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->dropIfExists('payroll_dtr_correction_requests');
    }
};
