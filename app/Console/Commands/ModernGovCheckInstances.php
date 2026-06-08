<?php

namespace App\Console\Commands;

use App\Models\Council;
use App\Services\CouncilDiscoveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ModernGovCheckInstances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'moderngov:check-instances
                            {--council= : Check a specific council by GSS code}
                            {--all : Check all councils with a modern_gov_base_url set}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Health-check ModernGov URLs and update is_active status';

    public function handle(): int
    {
        $service = new CouncilDiscoveryService;

        $query = Council::query()
            ->whereNotNull('modern_gov_base_url');

        if ($this->option('council')) {
            $query->where('gss_code', $this->option('council'));
        } elseif (! $this->option('all')) {
            // Default: only check councils where uses_modern_gov is null or false
            $query->where(function ($q) {
                $q->whereNull('uses_modern_gov')
                    ->orWhere('uses_modern_gov', false);
            });
        }

        $councils = $query->orderBy('name')->get();

        if ($councils->isEmpty()) {
            $this->info('No councils to health-check.');

            return self::SUCCESS;
        }

        $this->info("Health-checking {$councils->count()} council(s)...");

        $active = 0;
        $inactive = 0;

        foreach ($councils as $council) {
            $url = $council->modern_gov_base_url;
            $verified = $service->verifyModernGovUrl($url);

            $council->update(['uses_modern_gov' => $verified]);

            $status = $verified ? '✅ Active' : '❌ Inactive';
            $this->info("  {$council->name}: {$status}");

            if ($verified) {
                $active++;
            } else {
                $inactive++;
                Log::warning('ModernGov URL health check failed', [
                    'council' => $council->name,
                    'url' => $url,
                ]);
            }

            usleep(200_000); // 200ms between checks
        }

        $this->newLine();
        $this->info("Done. Active: {$active}, Inactive: {$inactive}");

        return self::SUCCESS;
    }
}
