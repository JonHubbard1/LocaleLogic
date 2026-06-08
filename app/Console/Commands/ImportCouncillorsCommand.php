<?php

namespace App\Console\Commands;

use App\Models\Council;
use App\Services\CouncillorImportService;
use Illuminate\Console\Command;

class ImportCouncillorsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'councillors:import
                            {gssCode? : Optional GSS code of a single council to import}
                            {--batch=10 : Number of councils to process in this run}
                            {--source=modern_gov : Data source: modern_gov (Democracy Club import is currently disabled)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import councillor data for councils from ModernGov';

    /**
     * Execute the console command.
     */
    public function handle(CouncillorImportService $service): int
    {
        $gssCode = $this->argument('gssCode');
        $batch = (int) $this->option('batch');
        $source = $this->option('source');

        if ($source === 'democracy_club') {
            $this->error('Democracy Club import is currently disabled. The DC v0.9 API does not support reliable ward-level filtering and returns incorrect data.');

            return self::FAILURE;
        }

        if ($source !== 'modern_gov' && $source !== 'auto') {
            $this->error("Invalid source '{$source}'. Must be modern_gov.");

            return self::FAILURE;
        }

        if ($gssCode) {
            $council = Council::findByGssCode($gssCode);

            if (! $council) {
                $this->error("Council {$gssCode} not found.");

                return self::FAILURE;
            }

            $this->info("Importing councillors for {$council->name} (source: ModernGov)...");
            $result = $service->importForCouncil($council, 'modern_gov');
            $this->info("Inserted: {$result['mg_inserted']}, Skipped: {$result['skipped']}");

            if (! empty($result['errors'])) {
                $this->error('Errors: ' . count($result['errors']));
            }

            return self::SUCCESS;
        }

        $query = Council::where('uses_modern_gov', true)
            ->whereNotNull('modern_gov_base_url');

        $count = $query->count();

        if ($count === 0) {
            $this->error('No councils with ModernGov configured. Run `php artisan councils:discover` first.');

            return self::FAILURE;
        }

        $councils = $query->limit($batch)->get();
        $this->info("Importing councillors for {$councils->count()} of {$count} ModernGov councils...");

        $totalInserted = 0;
        $totalSkipped = 0;

        foreach ($councils as $council) {
            $this->output->write("Importing {$council->name}... ");
            $result = $service->importForCouncil($council, 'modern_gov');
            $totalInserted += $result['mg_inserted'];
            $totalSkipped += $result['skipped'];
            $this->output->writeln("+{$result['mg_inserted']} / ~{$result['skipped']} skip");
        }

        $this->newLine();
        $this->info("Done. Total inserted: {$totalInserted}, Total skipped: {$totalSkipped}");

        return self::SUCCESS;
    }
}
