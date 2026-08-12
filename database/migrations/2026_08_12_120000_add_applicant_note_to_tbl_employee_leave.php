<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'hris';

    public function up(): void
    {
        if (! Schema::connection('hris')->hasTable('tbl_employee_leave')) {
            return;
        }

        if (! Schema::connection('hris')->hasColumn('tbl_employee_leave', 'applicant_note')) {
            Schema::connection('hris')->table('tbl_employee_leave', function (Blueprint $table) {
                $table->text('applicant_note')->nullable()->after('remarks');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('hris')->hasColumn('tbl_employee_leave', 'applicant_note')) {
            Schema::connection('hris')->table('tbl_employee_leave', function (Blueprint $table) {
                $table->dropColumn('applicant_note');
            });
        }
    }
};
