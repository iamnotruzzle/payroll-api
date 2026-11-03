<?php

namespace App\Services\Payroll;

use RuntimeException;

class HrisApiRecordException extends RuntimeException
{
    public function __construct(string $message, public readonly array $record)
    {
        parent::__construct($message);
    }
}
