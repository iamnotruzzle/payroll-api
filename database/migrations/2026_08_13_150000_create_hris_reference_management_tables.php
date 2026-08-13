<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'hris';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if (! $schema->hasTable('hris_reference_metadata')) {
            $schema->create('hris_reference_metadata', function (Blueprint $table) {
                $table->id();
                $table->string('reference_type', 32);
                $table->unsignedBigInteger('reference_id');
                $table->boolean('is_active')->default(true)->index();
                $table->text('remarks')->nullable();
                $table->string('updated_by_emp_id', 32)->nullable();
                $table->timestamps();
                $table->unique(['reference_type', 'reference_id'], 'hris_reference_metadata_unique');
            });
        }

        if (! $schema->hasTable('plantilla_items')) {
            $schema->create('plantilla_items', function (Blueprint $table) {
                $table->id();
                $table->string('item_number', 64)->unique();
                $table->unsignedInteger('position_id')->index();
                $table->unsignedInteger('department_id')->index();
                $table->unsignedSmallInteger('salary_grade');
                $table->string('fund_type', 128)->nullable();
                $table->unsignedSmallInteger('authorization_year')->nullable();
                $table->string('status', 24)->default('vacant')->index();
                $table->date('effective_from');
                $table->date('effective_to')->nullable()->index();
                $table->text('remarks')->nullable();
                $table->string('updated_by_emp_id', 32)->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('plantilla_assignments')) {
            $schema->create('plantilla_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plantilla_item_id')->constrained('plantilla_items')->restrictOnDelete();
                $table->string('emp_id', 32)->index();
                $table->date('effective_from');
                $table->date('effective_to')->nullable()->index();
                $table->string('nature', 32)->default('original');
                $table->text('remarks')->nullable();
                $table->string('recorded_by_emp_id', 32)->nullable();
                $table->timestamps();
                $table->index(['plantilla_item_id', 'effective_to']);
                $table->index(['emp_id', 'effective_to']);
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);
        $schema->dropIfExists('plantilla_assignments');
        $schema->dropIfExists('plantilla_items');
        $schema->dropIfExists('hris_reference_metadata');
    }
};
