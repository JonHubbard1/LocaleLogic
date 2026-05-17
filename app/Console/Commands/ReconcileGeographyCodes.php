<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileGeographyCodes extends Command
{
    protected $signature = 'onsud:reconcile
        {--table=properties : Table to reconcile (properties or properties_staging)}
        {--type= : Reconcile only one boundary type (wards, parishes, ced, constituencies, region, police_force_areas, lad)}
        {--dry-run : Preview corrections without writing to database}
        {--batch-size=50000 : Batch size for progress updates}';

    protected $description = 'Reconcile geography code columns using PostGIS spatial containment against boundary polygons';

    /**
     * Map boundary_type in boundary_geometries to column in properties table.
     */
    private array $reconcilers = [
        ['type' => 'wards',              'column' => 'wd25cd',      'label' => 'Ward'],
        ['type' => 'parishes',           'column' => 'parncp25cd',  'label' => 'Parish'],
        ['type' => 'ced',                'column' => 'ced25cd',     'label' => 'County Electoral Division'],
        ['type' => 'constituencies',     'column' => 'pcon24cd',    'label' => 'Parliamentary Constituency'],
        ['type' => 'region',             'column' => 'rgn25cd',     'label' => 'Region'],
        ['type' => 'police_force_areas', 'column' => 'pfa23cd',     'label' => 'Police Force Area'],
        ['type' => 'lad',                'column' => 'lad25cd',     'label' => 'Local Authority'],
    ];

    public function handle(): int
    {
        $table = $this->option('table');
        $dryRun = $this->option('dry-run');
        $filterType = $this->option('type');
        $batchSize = (int) $this->option('batch-size');

        if (! in_array($table, ['properties', 'properties_staging'], true)) {
            $this->error("Invalid table: {$table}. Must be 'properties' or 'properties_staging'.");

            return 1;
        }

        // Verify PostGIS is available
        try {
            DB::statement('SELECT PostGIS_Version()');
        } catch (\Exception $e) {
            $this->error('PostGIS extension is not available.');

            return 1;
        }

        // Verify spatial index exists
        $spatialIndex = DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE indexname = 'idx_{$table}_geom'"
        );
        if (! $spatialIndex) {
            $this->warn("Spatial index missing on {$table}. Creating now...");
            $original = DB::selectOne("SHOW maintenance_work_mem")->maintenance_work_mem;
            DB::statement("SET maintenance_work_mem = '512MB'");
            DB::statement(
                "CREATE INDEX idx_{$table}_geom ON {$table} USING GIST (ST_SetSRID(ST_MakePoint(lng, lat), 4326))"
            );
            DB::statement("SET maintenance_work_mem = '{$original}'");
            $this->info('Spatial index created.');
        }

        $reconcilers = $this->reconcilers;
        if ($filterType) {
            $reconcilers = array_filter(
                $reconcilers,
                fn (array $r): bool => $r['type'] === $filterType
            );
            if (empty($reconcilers)) {
                $this->error("Unknown boundary type: {$filterType}");
                $this->info('Valid types: wards, parishes, ced, constituencies, region, police_force_areas, lad');

                return 1;
            }
        }

        $this->info('========================================');
        $this->info(' Geography Code Reconciliation');
        $this->info(' Table: ' . $table);
        if ($dryRun) {
            $this->warn(' DRY RUN — no rows will be updated');
        }
        $this->info('========================================');
        $this->newLine();

        $totalCorrected = 0;
        $totalTypes = count($reconcilers);

        foreach ($reconcilers as $index => $rec) {
            $step = $index + 1;
            $type = $rec['type'];
            $column = $rec['column'];
            $label = $rec['label'];

            $this->info("[{$step}/{$totalTypes}] {$label} ({$type})");

            $polygonCount = DB::table('boundary_geometries')
                ->where('boundary_type', $type)
                ->whereNotNull('geom')
                ->count();

            if ($polygonCount === 0) {
                $this->warn("  No polygons found for {$type}, skipping.");
                continue;
            }

            $this->info("  {$polygonCount} polygons available.");

            $start = microtime(true);

            if ($dryRun) {
                $mismatched = DB::selectOne(
                    "SELECT COUNT(*) AS count
                     FROM {$table} p
                     JOIN boundary_geometries bg
                         ON bg.boundary_type = ?
                         AND bg.geom IS NOT NULL
                         AND ST_Intersects(
                             ST_SetSRID(ST_MakePoint(p.lng, p.lat), 4326),
                             bg.geom
                         )
                     WHERE p.{$column} IS DISTINCT FROM bg.gss_code
                       AND p.lat IS NOT NULL
                       AND p.lng IS NOT NULL",
                    [$type]
                )->count;

                $this->info("  {$mismatched} rows would be corrected (dry-run).");
                $totalCorrected += $mismatched;
            } else {
                $affected = $this->reconcileInChunks($table, $type, $column, $batchSize);

                $elapsed = round(microtime(true) - $start, 1);
                $this->info("  {$affected} rows corrected in {$elapsed}s");
                $totalCorrected += $affected;
            }
        }

        $this->newLine();
        $this->info('========================================');
        if ($dryRun) {
            $this->info(' Dry-run complete: ' . number_format($totalCorrected) . ' rows would be corrected');
        } else {
            $this->info(' Reconciliation complete: ' . number_format($totalCorrected) . ' rows corrected');
        }
        $this->info('========================================');

        return 0;
    }

    /**
     * Reconcile a single boundary type in chunked transactions.
     *
     * Prevents a single 41 M-row UPDATE from monopolising the DB for hours.
     *
     * @param string $table       Target table (properties or properties_staging)
     * @param string $type        boundary_type in boundary_geometries
     * @param string $column      Column to update on the target table
     * @param int    $chunkSize   UPRNs per chunk
     */
    private function reconcileInChunks(
        string $table,
        string $type,
        string $column,
        int $chunkSize = 50_000
    ): int {
        $totalAffected = 0;
        $batchNumber   = 0;

        $minUprn = DB::table($table)->whereNotNull('uprn')->min('uprn');
        $maxUprn = DB::table($table)->whereNotNull('uprn')->max('uprn');

        if ($minUprn === null || $maxUprn === null) {
            $this->warn('  No UPRNs found in table, skipping.');

            return 0;
        }

        // Build a temp table with the correct spatial assignments.
        // This is still heavy, but it is a read-only query — no row locks.
        $this->info('  Building match table (read-only spatial join)...');
        DB::statement('DROP TABLE IF EXISTS _reconcile_matches');
        DB::statement(
            "CREATE TEMP TABLE _reconcile_matches AS
            SELECT DISTINCT ON (p.uprn) p.uprn, bg.gss_code
            FROM {$table} p
            JOIN boundary_geometries bg
                ON bg.boundary_type = ?
                AND bg.geom IS NOT NULL
                AND ST_Intersects(
                    ST_SetSRID(ST_MakePoint(p.lng, p.lat), 4326),
                    bg.geom
                )
            WHERE p.lat IS NOT NULL
              AND p.lng IS NOT NULL
            ORDER BY p.uprn, ST_Area(bg.geom::geography) DESC",
            [$type]
        );

        DB::statement('CREATE INDEX _reconcile_matches_uprn ON _reconcile_matches(uprn)');

        $matchCount = DB::table('_reconcile_matches')->count();
        $this->info("  {$matchCount} matches found. Updating in chunks of {$chunkSize}...");

        $currentMin = $minUprn;

        while ($currentMin <= $maxUprn) {
            $batchNumber++;
            $currentMax = $currentMin + $chunkSize - 1;

            $affected = DB::affectingStatement(
                "UPDATE {$table} p
                 SET {$column} = m.gss_code
                 FROM _reconcile_matches m
                 WHERE p.uprn = m.uprn
                   AND p.uprn BETWEEN ? AND ?
                   AND p.{$column} IS DISTINCT FROM m.gss_code",
                [$currentMin, $currentMax]
            );

            $totalAffected += $affected;

            if ($affected > 0 || $batchNumber % 10 === 0) {
                $this->info(
                    sprintf(
                        '    batch %d (UPRN %s–%s): +%d corrected (total %s)',
                        $batchNumber,
                        number_format($currentMin),
                        number_format($currentMax),
                        $affected,
                        number_format($totalAffected)
                    )
                );
            }

            $currentMin = $currentMax + 1;
        }

        DB::statement('DROP TABLE IF EXISTS _reconcile_matches');

        return $totalAffected;
    }
}
