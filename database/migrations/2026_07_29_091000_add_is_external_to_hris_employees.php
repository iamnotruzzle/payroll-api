<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'hris';

    public function up(): void
    {
        if (! Schema::connection('hris')->hasColumn('tbl_employee', 'is_external')) {
            Schema::connection('hris')->table('tbl_employee', function (Blueprint $table) {
                $table->boolean('is_external')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('hris')->hasColumn('tbl_employee', 'is_external')) {
            Schema::connection('hris')->table('tbl_employee', function (Blueprint $table) {
                $table->dropColumn('is_external');
            });
        }
    }
};
