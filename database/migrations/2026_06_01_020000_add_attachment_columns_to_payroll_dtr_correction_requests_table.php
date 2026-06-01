<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->table('payroll_dtr_correction_requests', function (Blueprint $table) {
            if (! Schema::connection('payroll')->hasColumn('payroll_dtr_correction_requests', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('reason');
            }

            if (! Schema::connection('payroll')->hasColumn('payroll_dtr_correction_requests', 'attachment_original_name')) {
                $table->string('attachment_original_name')->nullable()->after('attachment_path');
            }

            if (! Schema::connection('payroll')->hasColumn('payroll_dtr_correction_requests', 'attachment_mime_type')) {
                $table->string('attachment_mime_type', 100)->nullable()->after('attachment_original_name');
            }

            if (! Schema::connection('payroll')->hasColumn('payroll_dtr_correction_requests', 'attachment_size')) {
                $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime_type');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->table('payroll_dtr_correction_requests', function (Blueprint $table) {
            foreach (['attachment_size', 'attachment_mime_type', 'attachment_original_name', 'attachment_path'] as $column) {
                if (Schema::connection('payroll')->hasColumn('payroll_dtr_correction_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
