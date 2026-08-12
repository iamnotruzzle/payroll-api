<?php

namespace App\Livewire\Training;

use App\Models\Hris\TrainingDetail;
use App\Support\Hris\TarfStatuses;
use Carbon\CarbonImmutable;
use Livewire\Component;

class TarfCalendar extends Component
{
    public string $month = '';

    public function mount(): void
    {
        $this->month = CarbonImmutable::today()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->month = CarbonImmutable::createFromFormat('Y-m', $this->month)->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = CarbonImmutable::createFromFormat('Y-m', $this->month)->addMonth()->format('Y-m');
    }

    public function render()
    {
        abort_unless(
            auth()->user()?->can('training.view')
            || auth()->user()?->can('training.manage')
            || auth()->user()?->can('training.approve'),
            403
        );

        $start = CarbonImmutable::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end = $start->endOfMonth();

        $items = TrainingDetail::query()
            ->with(['ldiType', 'requests.employee'])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->orderBy('start_date')
            ->orderBy('tarf_no')
            ->get();

        return view('livewire.training.tarf-calendar', [
            'items' => $items,
            'monthLabel' => $start->format('F Y'),
            'statusLabels' => TarfStatuses::labels(),
        ]);
    }
}
