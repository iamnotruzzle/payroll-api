<?php

namespace App\Providers;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->afterResolving('migration.repository', function ($repository): void {
            if ($repository instanceof DatabaseMigrationRepository) {
                $repository->setSource(config('database.migrations.connection', 'payroll_scheduler'));
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            $blockedCommands = [
                'db:wipe',
                'migrate:fresh',
                'migrate:refresh',
                'migrate:reset',
                'migrate:rollback',
            ];

            if (in_array($event->command, $blockedCommands, true)) {
                throw new RuntimeException("The '{$event->command}' command is disabled for this project. Use additive migrations against the payroll_scheduler connection only.");
            }
        });
    }
}
