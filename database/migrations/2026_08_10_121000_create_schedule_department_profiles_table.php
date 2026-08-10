<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = 'payroll_scheduler';

        Schema::connection($connection)->create('schedule_department_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->unique();
            $table->boolean('uses_units')->default(false);
            $table->boolean('uses_floaters')->default(false);
            $table->boolean('uses_on_call')->default(false);
            $table->boolean('uses_swaps')->default(false);
            $table->boolean('uses_census')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('payroll_scheduler')->dropIfExists('schedule_department_profiles');
    }
};
