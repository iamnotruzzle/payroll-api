<?php

namespace App\Mail;

use App\Models\Hris\TrainingDetail;
use App\Services\Hris\TrainingService;
use App\Support\Hris\TarfStatuses;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrainingStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $tarfNo,
        public int $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectForCode().' — '.$this->tarfNo,
        );
    }

    public function content(): Content
    {
        $detail = TrainingDetail::query()->find($this->tarfNo);

        return new Content(
            text: 'mail.training-status',
            with: [
                'tarfNo' => $this->tarfNo,
                'trainingName' => $detail?->training_name ?? $this->tarfNo,
                'statusName' => TarfStatuses::nameFor($detail?->status !== null ? (int) $detail->status : null),
                'startDate' => optional($detail?->start_date)->format('Y-m-d'),
                'endDate' => optional($detail?->end_date)->format('Y-m-d'),
                'message' => $this->bodyForCode(),
            ],
        );
    }

    private function subjectForCode(): string
    {
        return match ($this->code) {
            TrainingService::MAIL_ASSESSED => 'TARF forwarded to MCC',
            TrainingService::MAIL_APPROVED => 'TARF approved',
            TrainingService::MAIL_DISAPPROVED => 'TARF disapproved',
            TrainingService::MAIL_RESCHEDULED => 'TARF rescheduled',
            TrainingService::MAIL_INVITE => 'Training invitation',
            default => 'TARF update',
        };
    }

    private function bodyForCode(): string
    {
        return match ($this->code) {
            TrainingService::MAIL_ASSESSED => 'Your training request was assessed by PETU and forwarded for MCC approval.',
            TrainingService::MAIL_APPROVED => 'Your training request was approved.',
            TrainingService::MAIL_DISAPPROVED => 'Your training request was disapproved.',
            TrainingService::MAIL_RESCHEDULED => 'Training dates were rescheduled. Please review the updated schedule.',
            TrainingService::MAIL_INVITE => 'You have been invited to a training request. Please accept or decline in My Training.',
            default => 'There is an update on a training request you are associated with.',
        };
    }
}
