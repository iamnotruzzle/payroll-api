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
            $table->unsignedBigInteger('comparison_draft_id')->nullable()->after('difference_count')->index();
            $table->json('comparison_configuration')->nullable()->after('comparison_draft_id');
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->table('historical_payroll_imports', function (Blueprint $table) {
            $table->dropIndex(['comparison_draft_id']);
            $table->dropColumn(['comparison_draft_id', 'comparison_configuration']);
        });
    }
};
