<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnoseBoundaryTables extends Command
{
    protected $signature = 'diagnose:boundary-tables';
    protected $description = 'Diagnose boundary-related database tables and constraints';

    public function handle(): int
    {
        $this->info('Diagnosing Boundary Tables');
        $this->newLine();

        // Check if boundary_imports table exists
        $this->info('1. Checking if boundary_imports table exists...');
        if (Schema::hasTable('boundary_imports')) {
            $this->success('   ✓ boundary_imports table exists');

            // Check table structure
            $columns = Schema::getColumnListing('boundary_imports');
            $this->info('   Columns: ' . implode(', ', $columns));

            // Check the data_type constraint
            $this->info('2. Checking data_type constraint...');
            $constraint = DB::select("
                SELECT pg_get_constraintdef(oid) as definition
                FROM pg_constraint
                WHERE conrelid = 'boundary_imports'::regclass
                AND conname LIKE '%data_type%'
            ");

            if (!empty($constraint)) {
                $this->success('   ✓ Constraint found: ' . $constraint[0]->definition);

                if (str_contains($constraint[0]->definition, 'lookups')) {
                    $this->success('   ✓ Constraint includes "lookups" value');
                } else {
                    $this->error('   ✗ Constraint does NOT include "lookups" value');
                    $this->warn('   Run: php artisan migrate --force');
                }
            } else {
                $this->error('   ✗ No data_type constraint found');
            }

            // Check for records
            $this->info('3. Checking table records...');
            $totalRecords = DB::table('boundary_imports')->count();
            $this->info('   Total records: ' . number_format($totalRecords));

            $lookupRecords = DB::table('boundary_imports')
                ->where('boundary_type', 'ward_hierarchy_lookup')
                ->count();
            $this->info('   Ward hierarchy lookup records: ' . $lookupRecords);

            // Try the actual query from the component
            $this->info('4. Testing the query from BoundaryImport component...');
            try {
                $import = \App\Models\BoundaryImport::where('boundary_type', 'ward_hierarchy_lookup')
                    ->where('data_type', 'lookups')
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($import) {
                    $this->success('   ✓ Query successful - Found import with status: ' . $import->status);
                } else {
                    $this->info('   ✓ Query successful - No records found (this is OK)');
                }
            } catch (\Exception $e) {
                $this->error('   ✗ Query failed: ' . $e->getMessage());
                return self::FAILURE;
            }

        } else {
            $this->error('   ✗ boundary_imports table does NOT exist');
            $this->warn('   Run: php artisan migrate --force');
            return self::FAILURE;
        }

        // Check migrations
        $this->info('5. Checking migrations status...');
        $migrations = DB::table('migrations')
            ->where('migration', 'like', '%boundary%')
            ->orderBy('batch')
            ->get();

        foreach ($migrations as $migration) {
            $this->info('   ✓ ' . $migration->migration . ' (batch ' . $migration->batch . ')');
        }

        $this->newLine();
        $this->success('Diagnosis complete!');

        return self::SUCCESS;
    }

    private function success(string $message): void
    {
        $this->line("<fg=green>$message</>");
    }
}
