<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class HashSuperAdminSwitchPassword extends Command
{
    protected $signature = 'payroll:hash-super-admin-switch';

    protected $description = 'Generate a secure hash for the super-admin quick-switch password';

    public function handle(): int
    {
        $password = (string) $this->secret('New switch password (minimum 12 characters)');
        if (mb_strlen($password) < 12) {
            $this->error('The switch password must contain at least 12 characters.');

            return self::FAILURE;
        }

        if (! hash_equals($password, (string) $this->secret('Confirm switch password'))) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Add this line to .env:');
        $this->line('SUPER_ADMIN_SWITCH_PASSWORD_HASH="'.Hash::make($password).'"');

        return self::SUCCESS;
    }
}
