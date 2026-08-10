<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'hris_v2';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('employee_documents')) {
            return;
        }

        Schema::connection($this->connection)->create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('emp_id', 32)->index();
            $table->string('category', 64)->default('general')->index();
            $table->string('title');
            $table->string('original_name');
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('uploaded_by_emp_id', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('employee_documents');
    }
};
