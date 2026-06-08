<?php

namespace App\Console\Commands;

use App\Models\Council;
use App\Services\CouncilDiscoveryService;
use App\Services\LlmDiscoveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ModernGovDiscoverCouncils extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'moderngov:discover-councils
                            {--batch=50 : Number of councils per LLM batch}
                            {--nation= : Discover only a specific nation (england,scotland,wales,northern_ireland)}
                            {--dry-run : Preview results without saving}
                            {--no-check : Skip automatic health check after import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover ModernGov URLs for UK councils using an LLM';

    public function handle(): int
    {
        $llmService = new LlmDiscoveryService;
        $checkService = new CouncilDiscoveryService;

        $dryRun = $this->option('dry-run');
        $noCheck = $this->option('no-check');
        $batchSize = (int) $this->option('batch');
        $targetNation = $this->option('nation');

        // Build query for councils that need discovery
        $query = Council::query()
            ->where(function ($q) {
                $q->whereNull('modern_gov_base_url')
                    ->orWhere('uses_modern_gov', false);
            });

        if ($targetNation) {
            $query->where('nation', strtolower(str_replace('_', ' ', $targetNation)));
        }

        $councils = $query->orderBy('name')->get(['gss_code', 'name', 'nation', 'council_type']);

        if ($councils->isEmpty()) {
            $this->info('No councils need ModernGov discovery.');

            return self::SUCCESS;
        }

        $this->info("Discovering ModernGov URLs for {$councils->count()} councils...");

        $totalDiscovered = 0;
        $totalUpdated = 0;
        $chunks = $councils->chunk($batchSize);

        foreach ($chunks as $chunkIndex => $chunk) {
            $this->info("Batch " . ($chunkIndex + 1) . "/{$chunks->count()} — sending " . $chunk->count() . ' councils to LLM...');

            $batchCouncils = $chunk->map(fn ($c) => ['name' => $c->name, 'gss_code' => $c->gss_code])->toArray();

            try {
                $discovered = $llmService->discoverForCouncils($batchCouncils);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            if (empty($discovered)) {
                $this->warn('  LLM returned no results for this batch.');
                continue;
            }

            $this->info('  LLM returned ' . count($discovered) . ' potential matches.');

            foreach ($discovered as $item) {
                $totalDiscovered++;

                // Try to match by name first
                $match = $chunk->first(fn ($c) => strcasecmp($c->name, $item['name']) === 0);

                // Fallback: fuzzy name contains
                if (! $match) {
                    $match = $chunk->first(fn ($c) =>
                        str_contains(strtolower($c->name), strtolower($item['name']))
                        || str_contains(strtolower($item['name']), strtolower($c->name))
                    );
                }

                if (! $match) {
                    $this->warn("  Could not match '{$item['name']}' to any council in this batch.");
                    continue;
                }

                $this->info("  {$match->name}: {$item['url']}");

                if ($dryRun) {
                    continue;
                }

                // Verify with a quick HTTP check before saving
                $verified = $checkService->verifyModernGovUrl($item['url']);

                $match->update([
                    'modern_gov_base_url' => $item['url'],
                    'uses_modern_gov' => $verified,
                ]);

                $totalUpdated++;

                Log::info('ModernGov URL discovered via LLM', [
                    'council' => $match->name,
                    'url' => $item['url'],
                    'verified' => $verified,
                ]);
            }

            // Small delay between batches to avoid rate limits
            if ($chunkIndex < $chunks->count() - 1) {
                sleep(2);
            }
        }

        $this->newLine();
        $this->info("Done. Discovered: {$totalDiscovered}, Updated: {$totalUpdated}");

        if (! $dryRun && ! $noCheck && $totalUpdated > 0) {
            $this->newLine();
            $this->info('Running health check on newly discovered URLs...');
            $this->call('moderngov:check-instances');
        }

        return self::SUCCESS;
    }
}
