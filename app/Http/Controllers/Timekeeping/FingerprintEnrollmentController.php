<?php

namespace App\Http\Controllers\Timekeeping;

use App\Http\Controllers\Controller;
use App\Models\FingerprintEnrollmentAudit;
use App\Models\Hris\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FingerprintEnrollmentController extends Controller
{
    public function store(Request $request, string $employee, int $slot): JsonResponse
    {
        abort_unless($request->user()?->can('timekeeping.manage'), 403);
        abort_unless(in_array($slot, [1, 2], true), 404);

        $data = $request->validate([
            'template' => ['required', 'string', 'max:20000'],
            'format' => ['required', 'in:DP_REGISTRATION'],
            'quality' => ['nullable', 'integer', 'min:0', 'max:100'],
            'reader_model' => ['required', 'string', 'max:255'],
            'reader_serial' => ['nullable', 'string', 'max:255'],
        ]);

        $template = base64_decode($data['template'], true);
        if ($template === false || strlen($template) !== (int) config('biometrics.template_length', 1632)) {
            return response()->json(['message' => 'The captured template is not compatible with the existing fingerprint format.'], 422);
        }

        $lock = Cache::lock("fingerprint-enrollment:{$employee}:{$slot}", 15);
        if (! $lock->get()) {
            return response()->json(['message' => 'This fingerprint slot is currently being updated.'], 409);
        }

        try {
            $column = 'fingerprint_'.$slot;
            $person = Employee::query()->where('emp_id', $employee)->where('is_active', 'Y')->firstOrFail();
            $previousHex = (string) (DB::connection('hris')->table('tbl_employee')->where('emp_id', $person->emp_id)->value(DB::raw("HEX({$column})")) ?? '');
            $previous = $previousHex !== '' ? hex2bin($previousHex) : '';

            DB::connection('hris')->transaction(function () use ($request, $person, $column, $template, $previous, $slot, $data): void {
                $templateHex = bin2hex($template);
                DB::connection('hris')->table('tbl_employee')->where('emp_id', $person->emp_id)->update([$column => DB::raw("UNHEX('{$templateHex}')")]);
                FingerprintEnrollmentAudit::create([
                    'employee_id' => $person->emp_id,
                    'slot' => $slot,
                    'action' => strlen($previous) > 0 ? 'replace' : 'enroll',
                    'previous_hash' => strlen($previous) > 0 ? hash('sha256', $previous) : null,
                    'new_hash' => hash('sha256', $template),
                    'template_length' => strlen($template),
                    'quality' => $data['quality'] ?? null,
                    'reader_model' => $data['reader_model'],
                    'reader_serial' => $data['reader_serial'] ?? null,
                    'performed_by' => $request->user()?->emp_id,
                    'ip_address' => $request->ip(),
                ]);
            });

            return response()->json(['message' => 'Fingerprint saved.', 'slot' => $slot]);
        } finally {
            $lock->release();
        }
    }
}
