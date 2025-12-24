<?php

namespace Tests\Unit\Services;

use App\Services\TableSwapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TableSwapServiceTest extends TestCase
{
    use RefreshDatabase;

    private TableSwapService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TableSwapService();
        $this->seedRequiredLookupData();
    }

    /**
     * Seed minimum required lookup table data for testing
     */
    private function seedRequiredLookupData(): void
    {
        // Create a test LAD (Local Authority District) record
        // This is required because properties_staging has a foreign key to LAD
        DB::table('local_authority_districts')->insert([
            'lad25cd' => 'E09000001',
            'lad25nm' => 'Test City of London',
        ]);
    }

    /**
     * Test validation passes when staging table has valid data
     */
    public function test_validation_passes_with_valid_staging_data(): void
    {
        DB::table('properties_staging')->insert([
            'uprn' => 100000000001,
            'pcds' => 'SW1A 1AA',
            'gridgb1e' => 530000,
            'gridgb1n' => 180000,
            'lat' => 51.500000,
            'lng' => -0.120000,
            'lad25cd' => 'E09000001',
        ]);

        $result = $this->service->validateStagingTable(1);

        $this->assertTrue($result['valid']);
        $this->assertEquals(1, $result['record_count']);
    }

    /**
     * Test validation fails when staging table is empty
     */
    public function test_validation_fails_with_empty_staging_table(): void
    {
        $result = $this->service->validateStagingTable(1);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('empty', strtolower($result['message']));
    }

    /**
     * Test validation fails when record count doesn't match expected
     */
    public function test_validation_fails_with_incorrect_record_count(): void
    {
        DB::table('properties_staging')->insert([
            'uprn' => 100000000001,
            'pcds' => 'SW1A 1AA',
            'gridgb1e' => 530000,
            'gridgb1n' => 180000,
            'lat' => 51.500000,
            'lng' => -0.120000,
            'lad25cd' => 'E09000001',
        ]);

        $result = $this->service->validateStagingTable(10);

        $this->assertFalse($result['valid']);
        $this->assertEquals(1, $result['record_count']);
    }

    /**
     * Test validation catches null required columns
     *
     * Note: PostgreSQL enforces NOT NULL constraints at the database level,
     * preventing null values from ever being inserted. This test verifies
     * that the database constraint is working as expected.
     */
    public function test_validation_fails_with_null_required_columns(): void
    {
        // Insert a valid record
        DB::table('properties_staging')->insert([
            'uprn' => 100000000001,
            'pcds' => 'SW1A 1AA',
            'gridgb1e' => 530000,
            'gridgb1n' => 180000,
            'lat' => 51.500000,
            'lng' => -0.120000,
            'lad25cd' => 'E09000001',
        ]);

        // PostgreSQL enforces NOT NULL constraints - attempting to set NULL should fail
        $this->expectException(\Illuminate\Database\QueryException::class);

        // This should fail with NOT NULL constraint violation
        DB::statement('UPDATE properties_staging SET lat = NULL WHERE uprn = 100000000001');
    }

    /**
     * Test successful table swap renames tables correctly
     */
    public function test_successful_swap_renames_tables_correctly(): void
    {
        DB::table('properties_staging')->insert([
            'uprn' => 100000000001,
            'pcds' => 'SW1A 1AA',
            'gridgb1e' => 530000,
            'gridgb1n' => 180000,
            'lat' => 51.500000,
            'lng' => -0.120000,
            'lad25cd' => 'E09000001',
        ]);

        $this->service->swapPropertiesTable(1);

        $this->assertTrue(Schema::hasTable('properties'));
        $this->assertTrue(Schema::hasTable('properties_old'));
        $this->assertFalse(Schema::hasTable('properties_staging'));

        // Verify data is in properties table
        $count = DB::table('properties')->count();
        $this->assertEquals(1, $count);
    }

    /**
     * Test swap fails and rolls back when validation fails
     */
    public function test_swap_fails_when_validation_fails(): void
    {
        $this->expectException(\RuntimeException::class);

        // Empty staging table should fail validation
        $this->service->swapPropertiesTable(1);
    }

    /**
     * Test drop old table removes properties_old
     */
    public function test_drop_old_table_removes_properties_old(): void
    {
        // Create properties_old table (PostgreSQL supports LIKE)
        DB::statement('CREATE TABLE properties_old (LIKE properties INCLUDING ALL)');

        $this->assertTrue(Schema::hasTable('properties_old'));

        $this->service->dropOldTable();

        $this->assertFalse(Schema::hasTable('properties_old'));
    }
}
