<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->table('historical_payroll_import_sheets', function (Blueprint $table) {
            $table->json('organization_mappings')->nullable()->after('column_map');
        });

        Schema::connection('payroll')->table('historical_payroll_import_rows', function (Blueprint $table) {
            $table->string('source_division')->nullable()->after('source_employee_name');
            $table->string('source_department')->nullable()->after('source_division');
            $table->string('organization_key', 40)->nullable()->after('source_department')->index();
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->table('historical_payroll_import_rows', function (Blueprint $table) {
            $table->dropIndex(['organization_key']);
            $table->dropColumn(['source_division', 'source_department', 'organization_key']);
        });

        Schema::connection('payroll')->table('historical_payroll_import_sheets', function (Blueprint $table) {
            $table->dropColumn('organization_mappings');
        });
    }
};
