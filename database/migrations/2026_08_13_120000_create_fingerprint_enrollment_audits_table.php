<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('hris')->create('fingerprint_enrollment_audits', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 32)->index();
            $table->unsignedTinyInteger('slot');
            $table->string('action', 16);
            $table->string('previous_hash', 64)->nullable();
            $table->string('new_hash', 64);
            $table->unsignedInteger('template_length');
            $table->unsignedSmallInteger('quality')->nullable();
            $table->string('reader_model')->nullable();
            $table->string('reader_serial')->nullable();
            $table->string('performed_by', 32)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('hris')->dropIfExists('fingerprint_enrollment_audits');
    }
};
