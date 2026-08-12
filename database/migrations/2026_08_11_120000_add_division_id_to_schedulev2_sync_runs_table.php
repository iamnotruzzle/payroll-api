<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('database.default');

        if (! Schema::connection($connection)->hasTable('schedulev2_sync_runs')) {
            return;
        }

        Schema::connection($connection)->table('schedulev2_sync_runs', function (Blueprint $table) use ($connection) {
            if (! Schema::connection($connection)->hasColumn('schedulev2_sync_runs', 'division_id')) {
                $table->unsignedBigInteger('division_id')->nullable()->after('department_id')->index();
            }
        });
    }

    public function down(): void
    {
        $connection = config('database.default');

        if (! Schema::connection($connection)->hasTable('schedulev2_sync_runs')) {
            return;
        }

        Schema::connection($connection)->table('schedulev2_sync_runs', function (Blueprint $table) use ($connection) {
            if (Schema::connection($connection)->hasColumn('schedulev2_sync_runs', 'division_id')) {
                $table->dropColumn('division_id');
            }
        });
    }
};
