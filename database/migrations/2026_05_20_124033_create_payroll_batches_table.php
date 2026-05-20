<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->create('payroll_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('payroll_period');
            $table->string('payroll_type')->default('monthly');
            $table->string('generated_by')->nullable();
            $table->timestamp('snapshot_created_at');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index('department_id');
            $table->index('payroll_period');
            $table->index('snapshot_created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->dropIfExists('payroll_batches');
    }
};
