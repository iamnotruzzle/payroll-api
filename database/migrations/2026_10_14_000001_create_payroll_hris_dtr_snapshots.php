<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->create('payroll_hris_dtr_snapshots', function (Blueprint $table) {
            $table->bigIncrements('dtr_id');
            $table->unsignedBigInteger('source_dtr_id')->nullable();
            $table->string('emp_id');
            $table->date('dtr_date');
            foreach (['timein_am', 'timeout_am', 'timein_pm', 'timeout_pm', 'timeout_nextday', 'machine_id'] as $column) {
                $table->string($column)->nullable();
            }
            $table->unsignedBigInteger('source_batch_id')->nullable();
            $table->timestamps();
            $table->unique(['emp_id', 'dtr_date']);
            $table->index('dtr_date');
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->dropIfExists('payroll_hris_dtr_snapshots');
    }
};
