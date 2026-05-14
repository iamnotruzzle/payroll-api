<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->create('payroll_bank_templates', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::connection('payroll')->create('payroll_bank_template_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('payroll_bank_templates')->cascadeOnDelete();
            $table->string('column_key', 100);
            $table->string('label', 255);
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('width')->default(150)->comment('Excel column width in points');
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->dropIfExists('payroll_bank_template_columns');
        Schema::connection('payroll')->dropIfExists('payroll_bank_templates');
    }
};
