<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->table('payroll_batches', function (Blueprint $table) {
            $table->string('configuration_key', 64)->nullable()->after('division_id');
            $table->unique('configuration_key', 'payroll_batches_configuration_key_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->table('payroll_batches', function (Blueprint $table) {
            $table->dropUnique('payroll_batches_configuration_key_unique');
            $table->dropColumn('configuration_key');
        });
    }
};
