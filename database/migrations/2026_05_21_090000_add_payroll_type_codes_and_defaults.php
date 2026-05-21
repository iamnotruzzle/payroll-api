<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'payroll';

    public function up(): void
    {
        Schema::connection('payroll')->table('payroll_types', function (Blueprint $table) {
            if (! Schema::connection('payroll')->hasColumn('payroll_types', 'code')) {
                $table->string('code', 40)->nullable()->after('id');
            }

            if (! Schema::connection('payroll')->hasColumn('payroll_types', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('description');
            }

            if (! Schema::connection('payroll')->hasColumn('payroll_types', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            }
        });

        DB::connection('payroll')->table('payroll_types')->whereNull('code')->orderBy('id')->get()->each(function ($type) {
            DB::connection('payroll')->table('payroll_types')
                ->where('id', $type->id)
                ->update(['code' => str($type->name)->slug('_')->toString()]);
        });

        Schema::connection('payroll')->table('payroll_types', function (Blueprint $table) {
            if (! $this->indexExists('payroll_types', 'payroll_types_code_unique')) {
                $table->unique('code', 'payroll_types_code_unique');
            }
        });

        $now = now();
        foreach ([
            ['code' => 'general', 'name' => 'General', 'description' => 'General monthly salary payroll.', 'sort_order' => 10],
            ['code' => 'hazard', 'name' => 'Hazard', 'description' => 'Hazard pay payroll for eligible public health workers.', 'sort_order' => 20],
            ['code' => 'medicare', 'name' => 'Medicare', 'description' => 'Medicare payroll generation.', 'sort_order' => 30],
        ] as $type) {
            DB::connection('payroll')->table('payroll_types')->updateOrInsert(
                ['code' => $type['code']],
                $type + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        Schema::connection('payroll')->table('payroll_types', function (Blueprint $table) {
            if ($this->indexExists('payroll_types', 'payroll_types_code_unique')) {
                $table->dropUnique('payroll_types_code_unique');
            }

            foreach (['is_active', 'sort_order', 'code'] as $column) {
                if (Schema::connection('payroll')->hasColumn('payroll_types', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::connection('payroll')->getIndexes($table))
            ->contains(fn (array $item) => ($item['name'] ?? null) === $index);
    }
};
