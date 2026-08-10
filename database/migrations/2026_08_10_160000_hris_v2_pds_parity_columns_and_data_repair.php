<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'hris_v2';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if ($schema->hasTable('employee_personals')) {
            $schema->table('employee_personals', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('employee_personals', 'height')) {
                    $table->decimal('height', 8, 2)->nullable()->after('blood_type');
                }
                if (! $schema->hasColumn('employee_personals', 'weight')) {
                    $table->decimal('weight', 8, 2)->nullable()->after('height');
                }
                if (! $schema->hasColumn('employee_personals', 'is_related_third_degree')) {
                    $table->boolean('is_related_third_degree')->nullable();
                }
                if (! $schema->hasColumn('employee_personals', 'is_related_fourth_degree')) {
                    $table->boolean('is_related_fourth_degree')->nullable();
                }
                if (! $schema->hasColumn('employee_personals', 'is_admin_offense')) {
                    $table->boolean('is_admin_offense')->nullable();
                }
                if (! $schema->hasColumn('employee_personals', 'is_criminally_charged')) {
                    $table->boolean('is_criminally_charged')->nullable();
                }
                if (! $schema->hasColumn('employee_personals', 'is_convicted')) {
                    $table->boolean('is_convicted')->nullable();
                }
                if (! $schema->hasColumn('employee_personals', 'is_separated_service')) {
                    $table->boolean('is_separated_service')->nullable();
                }
                if (! $schema->hasColumn('employee_personals', 'is_election_candidate')) {
                    $table->boolean('is_election_candidate')->nullable();
                }
                if (! $schema->hasColumn('employee_personals', 'is_resigned_for_campaign')) {
                    $table->boolean('is_resigned_for_campaign')->nullable();
                }
                if (! $schema->hasColumn('employee_personals', 'is_immigrant')) {
                    $table->boolean('is_immigrant')->nullable();
                }
                if (! $schema->hasColumn('employee_personals', 'is_indigenous')) {
                    $table->boolean('is_indigenous')->nullable();
                }
                if (! $schema->hasColumn('employee_personals', 'is_pwd')) {
                    $table->boolean('is_pwd')->nullable();
                }
                if (! $schema->hasColumn('employee_personals', 'is_solo_parent')) {
                    $table->boolean('is_solo_parent')->nullable();
                }
            });
        }

        if ($schema->hasTable('employee_government_ids')) {
            $schema->table('employee_government_ids', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('employee_government_ids', 'issued_id_type')) {
                    $table->string('issued_id_type', 128)->nullable()->after('sss_no');
                }
                if (! $schema->hasColumn('employee_government_ids', 'issued_id_no')) {
                    $table->string('issued_id_no', 128)->nullable()->after('issued_id_type');
                }
                if (! $schema->hasColumn('employee_government_ids', 'issued_id_date_place')) {
                    $table->string('issued_id_date_place', 255)->nullable()->after('issued_id_no');
                }
            });
        }

        if ($schema->hasTable('employee_eligibilities') && $schema->hasColumn('employee_eligibilities', 'license_no')) {
            DB::connection($this->connection)->statement(
                'ALTER TABLE employee_eligibilities MODIFY license_no VARCHAR(100) NULL'
            );
        }

        $this->repairOtherInfoTypes();
        $this->repairWorkGovernmentFlags();
        $this->repairWorkStatusLabels();
        $this->repairCitizenshipAndReligionLabels();
        $this->backfillPersonalParityFromLegacy();
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);

        if ($schema->hasTable('employee_personals')) {
            $schema->table('employee_personals', function (Blueprint $table) use ($schema) {
                foreach ([
                    'height', 'weight',
                    'is_related_third_degree', 'is_related_fourth_degree',
                    'is_admin_offense', 'is_criminally_charged', 'is_convicted', 'is_separated_service',
                    'is_election_candidate', 'is_resigned_for_campaign', 'is_immigrant',
                    'is_indigenous', 'is_pwd', 'is_solo_parent',
                ] as $column) {
                    if ($schema->hasColumn('employee_personals', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if ($schema->hasTable('employee_government_ids')) {
            $schema->table('employee_government_ids', function (Blueprint $table) use ($schema) {
                foreach (['issued_id_type', 'issued_id_no', 'issued_id_date_place'] as $column) {
                    if ($schema->hasColumn('employee_government_ids', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function repairOtherInfoTypes(): void
    {
        // Use string literals — PDO numeric binding of "0"/"1"/"2" causes MySQL
        // to coerce existing labels like "skill" and abort under STRICT mode.
        DB::connection($this->connection)->statement("UPDATE employee_other_infos SET `type` = 'skill' WHERE `type` = '0'");
        DB::connection($this->connection)->statement("UPDATE employee_other_infos SET `type` = 'recognition' WHERE `type` = '1'");
        DB::connection($this->connection)->statement("UPDATE employee_other_infos SET `type` = 'membership' WHERE `type` = '2'");
    }

    private function repairWorkGovernmentFlags(): void
    {
        if (! Schema::connection('hris')->hasTable('tbl_employee_work_exp')) {
            return;
        }

        $rows = DB::connection($this->connection)
            ->table('employee_work_experiences')
            ->whereNotNull('legacy_work_exp_id')
            ->get(['id', 'legacy_work_exp_id']);

        foreach ($rows->chunk(500) as $chunk) {
            $legacyIds = $chunk->pluck('legacy_work_exp_id')->all();
            $legacyFlags = DB::connection('hris')
                ->table('tbl_employee_work_exp')
                ->whereIn('work_exp_id', $legacyIds)
                ->pluck('is_government', 'work_exp_id');

            foreach ($chunk as $row) {
                $raw = $legacyFlags[$row->legacy_work_exp_id] ?? null;
                $isGov = in_array(strtoupper((string) $raw), ['Y', '1', 'TRUE', 'YES'], true);
                DB::connection($this->connection)
                    ->table('employee_work_experiences')
                    ->where('id', $row->id)
                    ->update(['is_government' => $isGov]);
            }
        }
    }

    private function repairWorkStatusLabels(): void
    {
        if (! Schema::connection('hris')->hasTable('tbl_employmentstat')) {
            return;
        }

        $statuses = DB::connection('hris')
            ->table('tbl_employmentstat')
            ->pluck('status', 'empstat_id');

        foreach ($statuses as $id => $label) {
            $id = (string) $id;
            $label = str_replace("'", "''", (string) $label);
            DB::connection($this->connection)->statement(
                "UPDATE employee_work_experiences SET `work_status` = '{$label}' WHERE `work_status` = '{$id}'"
            );
        }
    }

    private function repairCitizenshipAndReligionLabels(): void
    {
        if (! Schema::connection($this->connection)->hasTable('employee_personals')) {
            return;
        }

        if (Schema::connection('hris')->hasTable('tbl_citizenship')) {
            $rows = DB::connection('hris')->table('tbl_citizenship')->pluck('citizenship', 'citizenship_id');
            foreach ($rows as $id => $label) {
                $id = (string) $id;
                $label = str_replace("'", "''", (string) $label);
                DB::connection($this->connection)->statement(
                    "UPDATE employee_personals SET `citizenship` = '{$label}' WHERE `citizenship` = '{$id}'"
                );
            }
        }

        if (Schema::connection('hris')->hasTable('tbl_religions')) {
            $rows = DB::connection('hris')->table('tbl_religions')->pluck('religion', 'religion_id');
            foreach ($rows as $id => $label) {
                $id = (string) $id;
                $label = str_replace("'", "''", (string) $label);
                DB::connection($this->connection)->statement(
                    "UPDATE employee_personals SET `religion` = '{$label}' WHERE `religion` = '{$id}'"
                );
            }
        }
    }

    private function backfillPersonalParityFromLegacy(): void
    {
        if (! Schema::connection('hris')->hasTable('tbl_employee')) {
            return;
        }

        $employees = DB::connection($this->connection)
            ->table('employees')
            ->get(['id', 'emp_id']);

        foreach ($employees->chunk(200) as $chunk) {
            $empIds = $chunk->pluck('emp_id')->all();
            $legacyRows = DB::connection('hris')
                ->table('tbl_employee')
                ->whereIn('emp_id', $empIds)
                ->get()
                ->keyBy('emp_id');

            foreach ($chunk as $employee) {
                $legacy = $legacyRows[$employee->emp_id] ?? null;
                if (! $legacy) {
                    continue;
                }

                $yes = static fn ($value) => in_array(strtoupper((string) $value), ['Y', '1', 'TRUE', 'YES'], true);

                DB::connection($this->connection)
                    ->table('employee_personals')
                    ->where('employee_id', $employee->id)
                    ->update([
                        'height' => is_numeric($legacy->height ?? null) ? $legacy->height : null,
                        'weight' => is_numeric($legacy->weight ?? null) ? $legacy->weight : null,
                        'is_related_third_degree' => $yes($legacy->is_degree3 ?? null),
                        'is_related_fourth_degree' => $yes($legacy->is_degree4 ?? null),
                        'is_admin_offense' => $yes($legacy->is_adminoffense ?? null),
                        'is_criminally_charged' => $yes($legacy->is_criminallycharged ?? null),
                        'is_convicted' => $yes($legacy->is_convictedtocourt ?? null),
                        'is_separated_service' => $yes($legacy->is_separated ?? null),
                        'is_election_candidate' => $yes($legacy->is_candidate ?? null),
                        'is_resigned_for_campaign' => $yes($legacy->is_campaign ?? null),
                        'is_immigrant' => $yes($legacy->is_immigrant ?? null),
                        'is_indigenous' => $yes($legacy->is_indigenous ?? null),
                        'is_pwd' => $yes($legacy->is_pwd ?? null),
                        'is_solo_parent' => $yes($legacy->is_soloparent ?? null),
                    ]);

                DB::connection($this->connection)
                    ->table('employee_government_ids')
                    ->where('employee_id', $employee->id)
                    ->update([
                        'issued_id_type' => $legacy->gov_id ?: null,
                        'issued_id_no' => $legacy->govid_no ?: null,
                        'issued_id_date_place' => $legacy->govid_dateplace ?: null,
                    ]);
            }
        }
    }
};
