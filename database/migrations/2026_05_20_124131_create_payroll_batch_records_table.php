<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->create('payroll_batch_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_batch_id');
            $table->string('emp_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->decimal('gross', 14, 2)->default(0);
            $table->decimal('net', 14, 2)->default(0);
            $table->decimal('fifteenth', 14, 2)->default(0);
            $table->decimal('thirtieth', 14, 2)->default(0);
            $table->json('snapshot_json');
            $table->timestamps();
            $table->index('payroll_batch_id');
            $table->index('emp_id');
            $table->index('department_id');
            $table->index('gross');
            $table->index('net');
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->dropIfExists('payroll_batch_records');
    }
};
