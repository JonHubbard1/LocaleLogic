<?php

namespace App\Console\Commands;

use App\Models\BoundaryName;
use App\Models\Council;
use App\Models\WardHierarchyLookup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedCouncilsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'councils:seed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the councils table from existing boundary and lookup data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Seeding councils table...');

        // 1. Pull from boundary_names where boundary_type = 'lad' first
        $fromBoundaries = BoundaryName::query()
            ->where('boundary_type', 'lad')
            ->where(function ($q) {
                $q->where('gss_code', 'like', 'E06%')
                    ->orWhere('gss_code', 'like', 'E07%')
                    ->orWhere('gss_code', 'like', 'E09%')
                    ->orWhere('gss_code', 'like', 'E10%')
                    ->orWhere('gss_code', 'like', 'S12%')
                    ->orWhere('gss_code', 'like', 'W06%')
                    ->orWhere('gss_code', 'like', 'N09%');
            })
            ->get();

        $councils = [];

        foreach ($fromBoundaries as $boundary) {
            $councils[$boundary->gss_code] = [
                'gss_code' => $boundary->gss_code,
                'name' => $boundary->name,
                'name_welsh' => $boundary->name_welsh,
                'council_type' => Council::inferTypeFromGssCode($boundary->gss_code),
                'nation' => Council::inferNationFromGssCode($boundary->gss_code),
                'region' => null,
                'uses_modern_gov' => null,
                'modern_gov_base_url' => null,
                'democracy_url' => null,
                'website_url' => null,
                'source' => 'boundary_names',
                'scraped_at' => null,
            ];
        }

        // 2. Fill any gaps from ward_hierarchy_lookups distinct lad_codes
        $fromLookups = DB::table('ward_hierarchy_lookups')
            ->select('lad_code', 'lad_name')
            ->distinct()
            ->get();

        foreach ($fromLookups as $lookup) {
            if (isset($councils[$lookup->lad_code])) {
                continue;
            }

            $councils[$lookup->lad_code] = [
                'gss_code' => $lookup->lad_code,
                'name' => $lookup->lad_name,
                'name_welsh' => null,
                'council_type' => Council::inferTypeFromGssCode($lookup->lad_code),
                'nation' => Council::inferNationFromGssCode($lookup->lad_code),
                'region' => null,
                'uses_modern_gov' => null,
                'modern_gov_base_url' => null,
                'democracy_url' => null,
                'website_url' => null,
                'source' => 'ward_hierarchy_lookups',
                'scraped_at' => null,
            ];
        }

        // 3. Upsert everything
        $chunks = array_chunk($councils, 100);
        $total = count($councils);
        $inserted = 0;
        $updated = 0;

        foreach ($chunks as $chunk) {
            $result = Council::upsert(
                $chunk,
                ['gss_code'],
                ['name', 'name_welsh', 'council_type', 'nation', 'source']
            );

            // Upsert returns void in Laravel, so we approximate counts
            $inserted += count($chunk);
        }

        $this->info("Processed {$total} councils.");
        $this->info('Seeding complete.');

        return self::SUCCESS;
    }
}
