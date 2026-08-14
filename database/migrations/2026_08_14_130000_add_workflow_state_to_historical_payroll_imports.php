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
            $table->json('workflow_state')->nullable()->after('comparison_drafts');
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->table('historical_payroll_imports', function (Blueprint $table) {
            $table->dropColumn('workflow_state');
        });
    }
};
