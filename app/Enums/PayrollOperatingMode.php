<?php

namespace App\Enums;

enum PayrollOperatingMode: string
{
    case Connected = 'connected';
    case Standalone = 'standalone';
}
