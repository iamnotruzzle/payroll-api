<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\Schedule\ScheduleDraftGenerationService;
use Carbon\CarbonImmutable;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('schedule:generate-next-month-draft {--force}', function () {
    $today = CarbonImmutable::today(config('app.timezone'));
    $draftMonthStart = $today->addDays(7);

    if (! $this->option('force') && $draftMonthStart->day !== 1) {
        $this->info('Next-month draft generation is not due today.');
        return 0;
    }

    $service = app(ScheduleDraftGenerationService::class);
    $result = $service->generate(
        $draftMonthStart->year,
        $draftMonthStart->month,
        null,
        null,
        'system:auto-draft',
    );

    $this->info(sprintf(
        'Draft schedule %d generated for %04d-%02d with %d conflict(s).',
        $result['schedule']->id,
        $draftMonthStart->year,
        $draftMonthStart->month,
        count($result['conflicts']),
    ));

    return 0;
})->purpose('Generate the next monthly draft schedule exactly one week before the upcoming month.');

Schedule::command('schedule:generate-next-month-draft')->dailyAt('00:15');
