<?php

namespace App\Console\Commands;

use App\Models\DataVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OnsudStatusCommand extends Command
{
    protected $signature = 'onsud:status';

    protected $description = 'Show ONSUD import status and version history';

    public function handle(): int
    {
        $current = DataVersion::where('dataset', 'ONSUD')
            ->where('status', 'current')
            ->first();

        if ($current) {
            $this->info("Current ONSUD Version:");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Epoch', $current->epoch],
                    ['Release Date', $current->release_date],
                    ['Imported At', $current->imported_at->format('Y-m-d H:i:s')],
                    ['Record Count', number_format($current->record_count)],
                    ['Status', $current->status],
                    ['File Hash', substr($current->file_hash, 0, 16) . '...'],
                ]
            );
        } else {
            $this->warn("No current ONSUD version found");
        }

        $history = DataVersion::where('dataset', 'ONSUD')
            ->orderBy('epoch', 'desc')
            ->limit(5)
            ->get();

        if ($history->isNotEmpty()) {
            $this->newLine();
            $this->info("Recent Import History:");
            $this->table(
                ['Epoch', 'Release Date', 'Records', 'Status', 'Imported At'],
                $history->map(fn($v) => [
                    $v->epoch,
                    $v->release_date,
                    number_format($v->record_count ?? 0),
                    $v->status,
                    $v->imported_at->diffForHumans(),
                ])
            );
        }

        // Show table statistics
        $this->newLine();
        $this->info("Database Statistics:");

        $propertiesCount = DB::table('properties')->count();
        $stagingCount = DB::table('properties_staging')->count();

        $tableStats = [
            ['properties', number_format($propertiesCount)],
            ['properties_staging', number_format($stagingCount)],
        ];

        if (DB::getSchemaBuilder()->hasTable('properties_old')) {
            $oldCount = DB::table('properties_old')->count();
            $tableStats[] = ['properties_old', number_format($oldCount)];
        }

        $this->table(['Table', 'Record Count'], $tableStats);

        return 0;
    }
}
