<?php

namespace App\Contracts\Payroll;

use App\Models\Payroll\PayrollSourceBatch;

interface CanonicalSourceAdapter
{
    public function stage(?string $performedBy = null): PayrollSourceBatch;
}
