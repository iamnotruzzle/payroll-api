<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'hris';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('employee_leave_credit_ledger')) {
            return;
        }

        Schema::connection($this->connection)->create('employee_leave_credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->string('emp_id', 32)->index();
            $table->string('bucket', 8)->index();
            $table->decimal('delta', 10, 3);
            $table->decimal('balance_after', 10, 3);
            $table->date('effective_date')->index();
            $table->string('source', 32)->index();
            $table->unsignedBigInteger('leave_id')->nullable()->index();
            $table->unsignedBigInteger('leave_log_id')->nullable()->index();
            $table->text('remarks')->nullable();
            $table->string('recorded_by_emp_id', 32)->nullable();
            $table->timestamps();

            $table->index(['emp_id', 'bucket', 'id']);
            $table->index(['emp_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('employee_leave_credit_ledger');
    }
};
