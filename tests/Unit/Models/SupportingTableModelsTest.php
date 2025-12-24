<?php

namespace Tests\Unit\Models;

use App\Models\BoundaryCache;
use App\Models\DataVersion;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

/**
 * Supporting Table Models Test
 *
 * Tests critical model behaviors for boundary cache and data version tracking tables.
 * Focuses on unique constraints, status values, and datetime casting.
 */
class SupportingTableModelsTest extends TestCase
{
    protected static $capsule;

    public static function setUpBeforeClass(): void
    {
        // Set up database connection
        self::$capsule = new Capsule;
        self::$capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        self::$capsule->setAsGlobal();
        self::$capsule->bootEloquent();

        // Create tables for tests
        self::createTables();
    }

    protected static function createTables(): void
    {
        $schema = Capsule::schema();

        // Create boundary_caches table
        $schema->create('boundary_caches', function (Blueprint $table) {
            $table->id();
            $table->string('geography_type', 20);
            $table->char('geography_code', 9);
            $table->string('boundary_resolution', 10)->default('BFC');
            $table->text('geojson');
            $table->timestamp('fetched_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->timestamps();

            $table->unique(['geography_type', 'geography_code', 'boundary_resolution'], 'idx_boundary_unique');
            $table->index('expires_at');
        });

        // Create data_versions table
        $schema->create('data_versions', function (Blueprint $table) {
            $table->id();
            $table->string('dataset', 20);
            $table->integer('epoch');
            $table->date('release_date');
            $table->timestamp('imported_at');
            $table->integer('record_count')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->string('status', 20)->default('current');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['dataset', 'epoch']);
        });
    }

    public function tearDown(): void
    {
        // Clean up data after each test
        Capsule::table('boundary_caches')->delete();
        Capsule::table('data_versions')->delete();
    }

    /**
     * Test BoundaryCache model has correct configuration and datetime casting.
     */
    public function test_boundary_cache_model_configuration()
    {
        $cache = new BoundaryCache();

        $this->assertEquals('boundary_caches', $cache->getTable());
        $this->assertTrue($cache->timestamps);
        $this->assertArrayHasKey('fetched_at', $cache->getCasts());
        $this->assertArrayHasKey('expires_at', $cache->getCasts());
    }

    /**
     * Test BoundaryCache unique constraint prevents duplicate entries.
     */
    public function test_boundary_cache_unique_constraint()
    {
        $geojson = '{"type":"Polygon","coordinates":[[[0,0],[1,0],[1,1],[0,1],[0,0]]]}';

        BoundaryCache::create([
            'geography_type' => 'ward',
            'geography_code' => 'E05013429',
            'boundary_resolution' => 'BFC',
            'geojson' => $geojson,
            'fetched_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Attempt to insert duplicate entry with same geography_type, code, and resolution
        BoundaryCache::create([
            'geography_type' => 'ward',
            'geography_code' => 'E05013429',
            'boundary_resolution' => 'BFC',
            'geojson' => $geojson,
            'fetched_at' => now(),
        ]);
    }

    /**
     * Test BoundaryCache can store different resolutions of same geography.
     */
    public function test_boundary_cache_allows_different_resolutions()
    {
        $geojson = '{"type":"Polygon","coordinates":[[[0,0],[1,0],[1,1],[0,1],[0,0]]]}';

        BoundaryCache::create([
            'geography_type' => 'ward',
            'geography_code' => 'E05013429',
            'boundary_resolution' => 'BFC',
            'geojson' => $geojson,
            'fetched_at' => now(),
        ]);

        BoundaryCache::create([
            'geography_type' => 'ward',
            'geography_code' => 'E05013429',
            'boundary_resolution' => 'BGC',
            'geojson' => $geojson,
            'fetched_at' => now(),
        ]);

        $this->assertEquals(2, BoundaryCache::where('geography_code', 'E05013429')->count());
    }

    /**
     * Test DataVersion model has correct configuration and datetime casting.
     */
    public function test_data_version_model_configuration()
    {
        $version = new DataVersion();

        $this->assertEquals('data_versions', $version->getTable());
        $this->assertTrue($version->timestamps);
        $this->assertArrayHasKey('release_date', $version->getCasts());
        $this->assertArrayHasKey('imported_at', $version->getCasts());
    }

    /**
     * Test DataVersion unique constraint prevents duplicate dataset epochs.
     */
    public function test_data_version_unique_constraint()
    {
        DataVersion::create([
            'dataset' => 'ONSUD',
            'epoch' => 110,
            'release_date' => '2025-12-01',
            'imported_at' => now(),
            'status' => 'current',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Attempt to insert duplicate entry with same dataset and epoch
        DataVersion::create([
            'dataset' => 'ONSUD',
            'epoch' => 110,
            'release_date' => '2025-12-15',
            'imported_at' => now(),
            'status' => 'archived',
        ]);
    }

    /**
     * Test DataVersion status field accepts expected values.
     */
    public function test_data_version_status_values()
    {
        $statuses = ['importing', 'current', 'archived', 'failed'];

        foreach ($statuses as $index => $status) {
            $version = DataVersion::create([
                'dataset' => 'ONSUD',
                'epoch' => 100 + $index,
                'release_date' => '2025-12-01',
                'imported_at' => now(),
                'status' => $status,
            ]);

            $this->assertEquals($status, $version->status);
        }

        $this->assertEquals(4, DataVersion::count());
    }

    /**
     * Test DataVersion supports tracking multiple datasets.
     */
    public function test_data_version_supports_multiple_datasets()
    {
        DataVersion::create([
            'dataset' => 'ONSUD',
            'epoch' => 110,
            'release_date' => '2025-12-01',
            'imported_at' => now(),
            'status' => 'current',
        ]);

        DataVersion::create([
            'dataset' => 'Ward Lookup',
            'epoch' => 1,
            'release_date' => '2025-12-01',
            'imported_at' => now(),
            'status' => 'current',
        ]);

        $this->assertEquals(1, DataVersion::where('dataset', 'ONSUD')->count());
        $this->assertEquals(1, DataVersion::where('dataset', 'Ward Lookup')->count());
        $this->assertEquals(2, DataVersion::count());
    }
}
