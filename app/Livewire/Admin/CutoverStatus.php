<?php

namespace App\Livewire\Admin;

use App\Services\Hris\CutoverStatusService;
use Livewire\Component;

class CutoverStatus extends Component
{
    public function render(CutoverStatusService $status)
    {
        return view('livewire.admin.cutover-status', [
            'snapshot' => $status->snapshot(),
        ]);
    }
}
