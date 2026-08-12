<?php

namespace App\Mail;

use App\Models\Hris\Department;
use App\Models\Schedule\MonthlySchedule;
use App\Services\Schedule\SchedulePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScheduleDistributionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $scheduleId,
        public ?int $unitId = null,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        $schedule = $this->schedule();
        $departmentName = Department::find($schedule->department_id)?->department ?? 'Department';
        $period = sprintf('%04d-%02d', $schedule->year, $schedule->month);

        return new Envelope(
            subject: "Monthly schedule {$period} — {$departmentName}",
        );
    }

    public function content(): Content
    {
        $schedule = $this->schedule();
        $departmentName = Department::find($schedule->department_id)?->department ?? 'Department';

        return new Content(
            text: 'mail.schedule-distribution',
            with: [
                'departmentName' => $departmentName,
                'period' => sprintf('%04d-%02d', $schedule->year, $schedule->month),
                'status' => $schedule->status,
                'note' => $this->note,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = app(SchedulePdfService::class)->generate($this->schedule(), $this->unitId);

        return [
            Attachment::fromData(fn () => $pdf['binary'], $pdf['filename'])
                ->withMime('application/pdf'),
        ];
    }

    private function schedule(): MonthlySchedule
    {
        return MonthlySchedule::query()->findOrFail($this->scheduleId);
    }
}
