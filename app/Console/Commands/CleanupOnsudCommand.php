<?php

namespace App\Console\Commands;

use App\Services\TableSwapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CleanupOnsudCommand extends Command
{
    protected $signature = 'onsud:cleanup
        {--staging : Clear staging table only}
        {--old : Drop old properties table}
        {--files : Remove downloaded ONSUD files}
        {--all : Perform all cleanup operations}';

    protected $description = 'Clean up ONSUD import artifacts';

    private TableSwapService $tableSwapService;

    public function __construct(TableSwapService $tableSwapService)
    {
        parent::__construct();
        $this->tableSwapService = $tableSwapService;
    }

    public function handle(): int
    {
        $performedAny = false;

        if ($this->option('all')) {
            $this->cleanupStaging();
            $this->cleanupOldTable();
            $this->cleanupFiles();
            return 0;
        }

        if ($this->option('staging')) {
            $this->cleanupStaging();
            $performedAny = true;
        }

        if ($this->option('old')) {
            $this->cleanupOldTable();
            $performedAny = true;
        }

        if ($this->option('files')) {
            $this->cleanupFiles();
            $performedAny = true;
        }

        if (!$performedAny) {
            $this->warn("No cleanup option specified. Use --staging, --old, --files, or --all");
            $this->info("Examples:");
            $this->line("  php artisan onsud:cleanup --staging");
            $this->line("  php artisan onsud:cleanup --old");
            $this->line("  php artisan onsud:cleanup --files");
            $this->line("  php artisan onsud:cleanup --all");
            return 1;
        }

        return 0;
    }

    private function cleanupStaging(): void
    {
        $count = DB::table('properties_staging')->count();

        if ($count > 0) {
            if ($this->confirm("Clear {$count} records from properties_staging table?", true)) {
                DB::statement('SET CONSTRAINTS ALL DEFERRED');
                DB::table('properties_staging')->truncate();
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
                $this->info("Staging table cleared ({$count} records removed)");
            } else {
                $this->warn("Staging table cleanup cancelled");
            }
        } else {
            $this->info("Staging table is already empty");
        }
    }

    private function cleanupOldTable(): void
    {
        if (DB::getSchemaBuilder()->hasTable('properties_old')) {
            $count = DB::table('properties_old')->count();

            if ($this->confirm("Drop properties_old table with {$count} records?", true)) {
                $this->tableSwapService->dropOldTable();
                $this->info("Old properties table dropped ({$count} records removed)");
            } else {
                $this->warn("Old table cleanup cancelled");
            }
        } else {
            $this->info("No properties_old table found");
        }
    }

    private function cleanupFiles(): void
    {
        $onsudPath = storage_path('app/onsud');

        if (File::exists($onsudPath)) {
            $size = $this->getDirectorySize($onsudPath);
            $sizeFormatted = $this->formatBytes($size);

            if ($this->confirm("Remove ONSUD files directory ({$sizeFormatted})?", true)) {
                File::deleteDirectory($onsudPath);
                $this->info("ONSUD files removed ({$sizeFormatted} freed)");
            } else {
                $this->warn("File cleanup cancelled");
            }
        } else {
            $this->info("No ONSUD files directory found");
        }
    }

    private function getDirectorySize(string $path): int
    {
        $size = 0;
        $files = File::allFiles($path);

        foreach ($files as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
