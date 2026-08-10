<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'hris_v2';

    public function up(): void
    {
        Schema::connection($this->connection)->table('employees', function (Blueprint $table) {
            $table->string('prefix', 64)->nullable()->change();
            $table->string('suffix', 255)->nullable()->change();
            $table->string('extension', 64)->nullable()->change();
            $table->string('separation_reason', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('employees', function (Blueprint $table) {
            $table->string('prefix', 32)->nullable()->change();
            $table->string('suffix', 32)->nullable()->change();
            $table->string('extension', 32)->nullable()->change();
            $table->string('separation_reason')->nullable()->change();
        });
    }
};
