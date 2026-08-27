<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('payroll')->table('payroll_source_batches', function (Blueprint $table) {
            $table->index(['status', 'activated_at'], 'payroll_source_batches_status_activated_idx');
            $table->index('created_at', 'payroll_source_batches_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->table('payroll_source_batches', function (Blueprint $table) {
            $table->dropIndex('payroll_source_batches_status_activated_idx');
            $table->dropIndex('payroll_source_batches_created_at_idx');
        });
    }
};
