<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Geography Lookup Tables Status Command
 *
 * Displays the current state of all geography lookup tables,
 * showing record counts and sample data.
 */
class GeographyStatusCommand extends Command
{
    protected $signature = 'geography:status
        {--table= : Show detailed data for a specific table}';

    protected $description = 'Show geography lookup tables status and record counts';

    public function handle(): int
    {
        if ($this->option('table')) {
            return $this->showTableDetails($this->option('table'));
        }

        $this->info('Geography Lookup Tables Status');
        $this->info('================================');
        $this->newLine();

        $tables = [
            'regions' => 'Regions',
            'counties' => 'Counties',
            'local_authority_districts' => 'Local Authority Districts',
            'wards' => 'Wards',
            'county_electoral_divisions' => 'County Electoral Divisions',
            'parishes' => 'Parishes',
            'constituencies' => 'Westminster Constituencies',
            'police_force_areas' => 'Police Force Areas',
        ];

        $tableData = [];

        foreach ($tables as $table => $label) {
            try {
                $count = DB::table($table)->count();
                $status = $count > 0 ? '✓' : '✗';
                $tableData[] = [$status, $label, number_format($count)];
            } catch (\Exception $e) {
                $tableData[] = ['✗', $label, 'Error'];
            }
        }

        $this->table(
            ['Status', 'Table', 'Records'],
            $tableData
        );

        $this->newLine();
        $this->info('Use --table=<name> to view detailed data for a specific table');
        $this->info('Available tables: ' . implode(', ', array_keys($tables)));

        return self::SUCCESS;
    }

    protected function showTableDetails(string $tableName): int
    {
        $tables = [
            'regions' => ['rgn25cd', 'rgn25nm'],
            'counties' => ['cty25cd', 'cty25nm'],
            'lads' => ['lad25cd', 'lad25nm', 'lad25nmw', 'rgn25cd'],
            'wards' => ['wd25cd', 'wd25nm', 'lad25cd'],
            'ceds' => ['ced25cd', 'ced25nm', 'cty25cd'],
            'parishes' => ['parncp25cd', 'parncp25nm', 'parncp25nmw', 'lad25cd'],
            'constituencies' => ['pcon24cd', 'pcon24nm'],
            'police' => ['pfa23cd', 'pfa23nm'],
        ];

        $tableMap = [
            'regions' => 'regions',
            'counties' => 'counties',
            'lads' => 'local_authority_districts',
            'wards' => 'wards',
            'ceds' => 'county_electoral_divisions',
            'parishes' => 'parishes',
            'constituencies' => 'constituencies',
            'police' => 'police_force_areas',
        ];

        if (!isset($tables[$tableName])) {
            $this->error("Unknown table: {$tableName}");
            $this->info('Available tables: ' . implode(', ', array_keys($tables)));
            return self::FAILURE;
        }

        $actualTable = $tableMap[$tableName];
        $columns = $tables[$tableName];

        $this->info("Table: {$actualTable}");
        $this->newLine();

        $total = DB::table($actualTable)->count();
        $this->info("Total records: " . number_format($total));
        $this->newLine();

        if ($total > 0) {
            $records = DB::table($actualTable)->limit(10)->get();
            $tableData = [];

            foreach ($records as $record) {
                $row = [];
                foreach ($columns as $column) {
                    $row[] = $record->$column ?? 'NULL';
                }
                $tableData[] = $row;
            }

            $this->table($columns, $tableData);

            if ($total > 10) {
                $this->info("Showing 10 of " . number_format($total) . " records");
            }
        } else {
            $this->warn('No records found');
        }

        return self::SUCCESS;
    }
}
