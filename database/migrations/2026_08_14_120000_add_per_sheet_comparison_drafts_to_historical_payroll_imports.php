<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->table('historical_payroll_imports', function (Blueprint $table) {
            $table->json('comparison_drafts')->nullable()->after('comparison_configuration');
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->table('historical_payroll_imports', function (Blueprint $table) {
            $table->dropColumn('comparison_drafts');
        });
    }
};
