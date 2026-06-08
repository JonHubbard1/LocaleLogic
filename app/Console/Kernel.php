<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Check for ONS boundary updates every Monday at 9am
        $schedule->command('boundaries:check-updates --notify')
            ->weekly()
            ->mondays()
            ->at('09:00')
            ->timezone('Europe/London');

        // ModernGov councillor import: daily for councils that have it (catches byelections/defections)
        $schedule->command('councillors:import --batch=20')
            ->dailyAt('04:00')
            ->timezone('Europe/London')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        // Load database commands (migrate, seed, etc.)
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
