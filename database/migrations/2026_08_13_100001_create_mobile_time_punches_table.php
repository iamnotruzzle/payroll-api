<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->create('mobile_time_punches', function (Blueprint $table) {
            $table->id();
            $table->string('emp_id')->index();
            $table->unsignedBigInteger('dtr_id')->nullable()->index();
            $table->string('punch_type', 16);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('device_timestamp');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['emp_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->dropIfExists('mobile_time_punches');
    }
};
