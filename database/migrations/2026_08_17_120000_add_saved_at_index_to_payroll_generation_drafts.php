<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->table('payroll_generation_drafts', function (Blueprint $table) {
            $table->index('saved_at', 'payroll_drafts_saved_at_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->table('payroll_generation_drafts', function (Blueprint $table) {
            $table->dropIndex('payroll_drafts_saved_at_idx');
        });
    }
};
