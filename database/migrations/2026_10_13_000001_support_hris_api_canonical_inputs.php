<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->table('payroll_canonical_departments', function (Blueprint $table) {
            $table->unsignedBigInteger('division_external_id')->nullable()->change();
        });
        Schema::connection('payroll')->table('payroll_canonical_leaves', function (Blueprint $table) {
            $table->string('status')->nullable();
            $table->json('leave_dates')->nullable();
        });
    }
};
