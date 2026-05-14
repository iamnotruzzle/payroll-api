<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = 'payroll_scheduler';

        Schema::connection($connection)->create('schedule_print_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->nullable()->unique();
            $table->string('organization_name')->default('MARIANO MARCOS MEMORIAL HOSPITAL AND MEDICAL CENTER');
            $table->string('schedule_heading')->default('MONTHLY SCHEDULE OF DUTIES');
            $table->string('area_label')->default('AREA');
            $table->string('logo_path')->nullable();
            $table->string('logo_position', 20)->default('left');
            $table->unsignedSmallInteger('logo_width')->default(72);
            $table->timestamps();
        });

        Schema::connection($connection)->create('schedule_signatories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('purpose', 80);
            $table->string('person_name');
            $table->string('designation')->nullable();
            $table->unsignedSmallInteger('display_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department_id', 'display_order']);
        });
    }

    public function down(): void
    {
        $connection = 'payroll_scheduler';

        Schema::connection($connection)->dropIfExists('schedule_signatories');
        Schema::connection($connection)->dropIfExists('schedule_print_settings');
    }
};
