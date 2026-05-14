<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('payroll_scheduler')->create('schedule_print_logos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('label')->default('Logo');
            $table->string('path');
            $table->decimal('x_position', 5, 2)->default(2);
            $table->decimal('y_position', 5, 2)->default(2);
            $table->unsignedSmallInteger('width')->default(72);
            $table->unsignedSmallInteger('display_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::connection('payroll_scheduler')->dropIfExists('schedule_print_logos');
    }
};
