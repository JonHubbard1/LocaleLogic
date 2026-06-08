<?php

namespace App\Jobs;

use App\Models\Council;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DiscoverModernGovCouncilsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?string $nation = null,
        public bool $dryRun = false,
        public bool $noCheck = false,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cacheKey = 'moderngov_discovery_status';

        Cache::put($cacheKey, [
            'status' => 'running',
            'started_at' => now()->toIso8601String(),
            'nation' => $this->nation,
        ], 3600);

        try {
            $options = [
                '--dry-run' => $this->dryRun,
                '--no-check' => $this->noCheck,
            ];

            if ($this->nation) {
                $options['--nation'] = $this->nation;
            }

            $exitCode = Artisan::call('moderngov:discover-councils', $options);
            $output = Artisan::output();

            $lines = array_filter(explode("\n", $output));
            $lastLine = end($lines) ?: 'Done';

            Cache::put($cacheKey, [
                'status' => $exitCode === 0 ? 'completed' : 'failed',
                'finished_at' => now()->toIso8601String(),
                'nation' => $this->nation,
                'summary' => $lastLine,
                'output' => $output,
            ], 3600);

            Log::info('DiscoverModernGovCouncilsJob finished', [
                'exit_code' => $exitCode,
                'nation' => $this->nation,
            ]);
        } catch (\Throwable $e) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'finished_at' => now()->toIso8601String(),
                'nation' => $this->nation,
                'error' => $e->getMessage(),
            ], 3600);

            Log::error('DiscoverModernGovCouncilsJob failed', [
                'error' => $e->getMessage(),
                'nation' => $this->nation,
            ]);

            throw $e;
        }
    }
}
