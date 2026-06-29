<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->create('payroll_loan_import_item_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('payroll_loan_imports')->cascadeOnDelete();
            $table->foreignId('import_item_id')->constrained('payroll_loan_import_items')->cascadeOnDelete();
            $table->string('action', 20)->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('performed_by', 80)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('reverted_at')->nullable();
            $table->string('reverted_by', 80)->nullable();

            $table->index(['import_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('payroll')->dropIfExists('payroll_loan_import_item_audits');
    }
};
