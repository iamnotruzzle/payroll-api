<?php

namespace App\Services\Hris;

use App\Models\Schedule\Schedulev2SyncRun;
use App\Support\Hris\HrisCutover;
use Illuminate\Support\Facades\DB;
use Throwable;

class CutoverStatusService
{
    /**
     * Snapshot for the Admin Cutover status page.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $moduleFlags = [];
        foreach (HrisCutover::MODULE_LABELS as $key => $label) {
            $moduleFlags[] = [
                'key' => $key,
                'label' => $label,
                'env' => 'HRIS_CUTOVER_'.strtoupper($key === 'self_service' ? 'SELF_SERVICE' : $key),
                'enabled' => HrisCutover::moduleComplete($key),
            ];
        }

        return [
            'use_v2' => HrisCutover::usesV2(),
            'freeze_legacy_writes' => HrisCutover::freezeLegacyWrites(),
            'api_require_auth' => (bool) config('api.require_auth', false),
            'module_flags' => $moduleFlags,
            'schedulev2' => $this->schedulev2Status(),
            'last_sync_run' => $this->lastSyncRunSummary(),
            'runbook_path' => 'docs/hris-cutover.md',
            'checklist' => $this->checklistHints(),
        ];
    }

    /**
     * @return array{connection:string,database:?string,configured:bool,reachable:?bool,error:?string}
     */
    private function schedulev2Status(): array
    {
        $connection = (string) config('schedule.schedulev2.connection', 'schedulev2');
        $database = config("database.connections.{$connection}.database");
        $configured = filled($database);
        $reachable = null;
        $error = null;

        if ($configured) {
            try {
                DB::connection($connection)->getPdo();
                $reachable = true;
            } catch (Throwable $e) {
                $reachable = false;
                $error = $e->getMessage();
            }
        }

        return [
            'connection' => $connection,
            'database' => $database ? (string) $database : null,
            'configured' => $configured,
            'reachable' => $reachable,
            'error' => $error,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lastSyncRunSummary(): ?array
    {
        try {
            $run = Schedulev2SyncRun::query()->orderByDesc('id')->first();
        } catch (Throwable) {
            return null;
        }

        if (! $run) {
            return null;
        }

        return [
            'id' => $run->id,
            'batch_key' => $run->batch_key,
            'dry_run' => (bool) $run->dry_run,
            'status' => $run->status,
            'from_date' => optional($run->from_date)?->toDateString(),
            'to_date' => optional($run->to_date)?->toDateString(),
            'department_id' => $run->department_id,
            'division_id' => $run->division_id,
            'stats' => $run->stats,
            'error_count' => is_array($run->errors) ? count($run->errors) : 0,
            'started_at' => optional($run->started_at)?->toDateTimeString(),
            'finished_at' => optional($run->finished_at)?->toDateTimeString(),
        ];
    }

    /**
     * @return list<array{label:string,done:bool,note:string}>
     */
    private function checklistHints(): array
    {
        $useV2 = HrisCutover::usesV2();
        $freeze = HrisCutover::freezeLegacyWrites();
        $sched = HrisCutover::moduleComplete('schedule');
        $employees = HrisCutover::moduleComplete('employees');

        return [
            [
                'label' => 'Employees on hris_v2 (HRIS_USE_V2)',
                'done' => $useV2,
                'note' => $useV2 ? 'Reading/writing employee master via v2.' : 'Still on legacy tbl_* for Employees UI.',
            ],
            [
                'label' => 'Module cutover flags (ops dual-run)',
                'done' => $employees || HrisCutover::moduleComplete('leave') || HrisCutover::moduleComplete('self_service'),
                'note' => 'Flip HRIS_CUTOVER_* after pilot signoff — see runbook.',
            ],
            [
                'label' => 'Freeze legacy employee-master writes',
                'done' => $freeze && $useV2,
                'note' => $freeze && ! $useV2
                    ? 'Freeze is on but HRIS_USE_V2 is false — enable v2 before relying on freeze.'
                    : ($freeze ? 'Legacy employee master writes blocked.' : 'Not frozen yet (default).'),
            ],
            [
                'label' => 'Schedule cutover (NDOS archive)',
                'done' => $sched,
                'note' => $sched
                    ? 'HRIS_CUTOVER_SCHEDULE=true — archive NDOS usage per runbook.'
                    : 'Awaiting Nursing / dept pilots + HRIS_CUTOVER_SCHEDULE.',
            ],
            [
                'label' => 'API lockdown (API_REQUIRE_AUTH)',
                'done' => (bool) config('api.require_auth', false),
                'note' => 'Clocks should use Phase 6 secured endpoints on this app.',
            ],
        ];
    }
}
