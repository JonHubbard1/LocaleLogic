<?php

namespace Tests\Unit\Models;

use App\Models\Property;
use App\Models\Ward;
use App\Models\LocalAuthorityDistrict;
use App\Models\Region;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

/**
 * Property Model Test
 *
 * Tests critical model behaviors for the Property model.
 * Focuses on UPRN primary key, coordinate fields, and geography relationships.
 */
class PropertyModelTest extends TestCase
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

        // Create regions table
        $schema->create('regions', function (Blueprint $table) {
            $table->char('rgn25cd', 9)->primary();
            $table->string('rgn25nm', 50);
            $table->timestamps();
        });

        // Create local_authority_districts table
        $schema->create('local_authority_districts', function (Blueprint $table) {
            $table->char('lad25cd', 9)->primary();
            $table->string('lad25nm', 100);
            $table->string('lad25nmw', 100)->nullable();
            $table->char('rgn25cd', 9)->nullable();
            $table->timestamps();
        });

        // Create wards table
        $schema->create('wards', function (Blueprint $table) {
            $table->char('wd25cd', 9)->primary();
            $table->string('wd25nm', 100);
            $table->char('lad25cd', 9);
            $table->timestamps();
        });

        // Create properties table
        $schema->create('properties', function (Blueprint $table) {
            $table->bigInteger('uprn')->primary();
            $table->string('pcds', 8);
            $table->integer('gridgb1e');
            $table->integer('gridgb1n');
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->char('wd25cd', 9)->nullable();
            $table->char('ced25cd', 9)->nullable();
            $table->char('parncp25cd', 9)->nullable();
            $table->char('lad25cd', 9);
            $table->char('pcon24cd', 9)->nullable();
            $table->char('lsoa21cd', 9)->nullable();
            $table->char('msoa21cd', 9)->nullable();
            $table->char('rgn25cd', 9)->nullable();
            $table->char('ruc21ind', 9)->nullable();
            $table->char('pfa23cd', 9)->nullable();
        });
    }

    public function tearDown(): void
    {
        // Clean up data after each test
        Capsule::table('properties')->delete();
        Capsule::table('wards')->delete();
        Capsule::table('local_authority_districts')->delete();
        Capsule::table('regions')->delete();
    }

    /**
     * Test Property model uses UPRN as primary key.
     */
    public function test_property_uses_uprn_as_primary_key()
    {
        $property = new Property();

        $this->assertEquals('properties', $property->getTable());
        $this->assertEquals('uprn', $property->getKeyName());
        $this->assertEquals('int', $property->getKeyType());
        $this->assertFalse($property->getIncrementing());
    }

    /**
     * Test Property model has timestamps disabled.
     */
    public function test_property_has_timestamps_disabled()
    {
        $property = new Property();

        $this->assertFalse($property->timestamps);
    }

    /**
     * Test Property can be retrieved by UPRN.
     */
    public function test_property_can_be_retrieved_by_uprn()
    {
        $property = Property::create([
            'uprn' => 10012345678,
            'pcds' => 'NE1 4ST',
            'gridgb1e' => 425000,
            'gridgb1n' => 565000,
            'lat' => 54.978252,
            'lng' => -1.617780,
            'lad25cd' => 'E08000021',
        ]);

        $retrieved = Property::find(10012345678);

        $this->assertNotNull($retrieved);
        $this->assertEquals(10012345678, $retrieved->uprn);
        $this->assertEquals('NE1 4ST', $retrieved->pcds);
    }

    /**
     * Test Property coordinate fields are populated correctly.
     */
    public function test_property_coordinate_fields_populated()
    {
        $property = Property::create([
            'uprn' => 10012345679,
            'pcds' => 'SW1A 1AA',
            'gridgb1e' => 529090,
            'gridgb1n' => 179645,
            'lat' => 51.501009,
            'lng' => -0.141588,
            'lad25cd' => 'E09000033',
        ]);

        $this->assertEquals(529090, $property->gridgb1e);
        $this->assertEquals(179645, $property->gridgb1n);
        $this->assertEquals(51.501009, $property->lat);
        $this->assertEquals(-0.141588, $property->lng);
    }

    /**
     * Test Property belongs to Ward relationship.
     */
    public function test_property_belongs_to_ward_relationship()
    {
        $region = Region::create([
            'rgn25cd' => 'E12000001',
            'rgn25nm' => 'North East',
        ]);

        $lad = LocalAuthorityDistrict::create([
            'lad25cd' => 'E08000021',
            'lad25nm' => 'Newcastle upon Tyne',
            'lad25nmw' => null,
            'rgn25cd' => 'E12000001',
        ]);

        $ward = Ward::create([
            'wd25cd' => 'E05013429',
            'wd25nm' => 'Monument',
            'lad25cd' => 'E08000021',
        ]);

        $property = Property::create([
            'uprn' => 10012345680,
            'pcds' => 'NE1 4ST',
            'gridgb1e' => 425000,
            'gridgb1n' => 565000,
            'lat' => 54.978252,
            'lng' => -1.617780,
            'wd25cd' => 'E05013429',
            'lad25cd' => 'E08000021',
        ]);

        $this->assertInstanceOf(Ward::class, $property->ward);
        $this->assertEquals('E05013429', $property->ward->wd25cd);
        $this->assertEquals('Monument', $property->ward->wd25nm);
    }

    /**
     * Test Property belongs to LAD relationship.
     */
    public function test_property_belongs_to_lad_relationship()
    {
        $region = Region::create([
            'rgn25cd' => 'E12000001',
            'rgn25nm' => 'North East',
        ]);

        $lad = LocalAuthorityDistrict::create([
            'lad25cd' => 'E08000021',
            'lad25nm' => 'Newcastle upon Tyne',
            'lad25nmw' => null,
            'rgn25cd' => 'E12000001',
        ]);

        $property = Property::create([
            'uprn' => 10012345681,
            'pcds' => 'NE1 4ST',
            'gridgb1e' => 425000,
            'gridgb1n' => 565000,
            'lat' => 54.978252,
            'lng' => -1.617780,
            'lad25cd' => 'E08000021',
        ]);

        $this->assertInstanceOf(LocalAuthorityDistrict::class, $property->localAuthorityDistrict);
        $this->assertEquals('E08000021', $property->localAuthorityDistrict->lad25cd);
        $this->assertEquals('Newcastle upon Tyne', $property->localAuthorityDistrict->lad25nm);
    }
}
