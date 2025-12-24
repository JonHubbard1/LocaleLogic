<?php

namespace Tests\Unit\Models;

use App\Models\Region;
use App\Models\County;
use App\Models\LocalAuthorityDistrict;
use App\Models\Ward;
use App\Models\CountyElectoralDivision;
use App\Models\Parish;
use App\Models\Constituency;
use App\Models\PoliceForceArea;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

/**
 * Lookup Table Models Test
 *
 * Tests critical model behaviors for geography lookup tables.
 * Focuses on primary key configuration, table names, and key relationships.
 */
class LookupTableModelsTest extends TestCase
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

        // Create all tables for tests
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

        // Create counties table
        $schema->create('counties', function (Blueprint $table) {
            $table->char('cty25cd', 9)->primary();
            $table->string('cty25nm', 100);
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

        // Create county_electoral_divisions table
        $schema->create('county_electoral_divisions', function (Blueprint $table) {
            $table->char('ced25cd', 9)->primary();
            $table->string('ced25nm', 100);
            $table->char('cty25cd', 9);
            $table->timestamps();
        });

        // Create parishes table
        $schema->create('parishes', function (Blueprint $table) {
            $table->char('parncp25cd', 9)->primary();
            $table->string('parncp25nm', 100);
            $table->string('parncp25nmw', 100)->nullable();
            $table->char('lad25cd', 9);
            $table->timestamps();
        });

        // Create constituencies table
        $schema->create('constituencies', function (Blueprint $table) {
            $table->char('pcon24cd', 9)->primary();
            $table->string('pcon24nm', 100);
            $table->timestamps();
        });

        // Create police_force_areas table
        $schema->create('police_force_areas', function (Blueprint $table) {
            $table->char('pfa23cd', 9)->primary();
            $table->string('pfa23nm', 100);
            $table->timestamps();
        });
    }

    public function tearDown(): void
    {
        // Clean up data after each test using delete instead of truncate for SQLite compatibility
        Capsule::table('wards')->delete();
        Capsule::table('parishes')->delete();
        Capsule::table('county_electoral_divisions')->delete();
        Capsule::table('local_authority_districts')->delete();
        Capsule::table('regions')->delete();
        Capsule::table('counties')->delete();
        Capsule::table('constituencies')->delete();
        Capsule::table('police_force_areas')->delete();
    }

    /**
     * Test Region model uses correct primary key and table.
     */
    public function test_region_model_has_correct_configuration()
    {
        $region = new Region();

        $this->assertEquals('regions', $region->getTable());
        $this->assertEquals('rgn25cd', $region->getKeyName());
        $this->assertEquals('string', $region->getKeyType());
        $this->assertFalse($region->getIncrementing());
    }

    /**
     * Test LAD can establish relationship to Region.
     */
    public function test_lad_belongs_to_region_relationship()
    {
        $region = Region::create([
            'rgn25cd' => 'E12000001',
            'rgn25nm' => 'North East',
        ]);

        $lad = LocalAuthorityDistrict::create([
            'lad25cd' => 'E06000047',
            'lad25nm' => 'County Durham',
            'lad25nmw' => null,
            'rgn25cd' => 'E12000001',
        ]);

        $this->assertInstanceOf(Region::class, $lad->region);
        $this->assertEquals('E12000001', $lad->region->rgn25cd);
        $this->assertEquals('North East', $lad->region->rgn25nm);
    }

    /**
     * Test Ward belongs to LAD relationship.
     */
    public function test_ward_belongs_to_lad_relationship()
    {
        $region = Region::create([
            'rgn25cd' => 'E12000001',
            'rgn25nm' => 'North East',
        ]);

        $lad = LocalAuthorityDistrict::create([
            'lad25cd' => 'E06000047',
            'lad25nm' => 'County Durham',
            'lad25nmw' => null,
            'rgn25cd' => 'E12000001',
        ]);

        $ward = Ward::create([
            'wd25cd' => 'E05013429',
            'wd25nm' => 'Aycliffe North',
            'lad25cd' => 'E06000047',
        ]);

        $this->assertInstanceOf(LocalAuthorityDistrict::class, $ward->localAuthorityDistrict);
        $this->assertEquals('E06000047', $ward->localAuthorityDistrict->lad25cd);
        $this->assertEquals('County Durham', $ward->localAuthorityDistrict->lad25nm);
    }

    /**
     * Test LAD hasMany Wards relationship.
     */
    public function test_lad_has_many_wards_relationship()
    {
        $region = Region::create([
            'rgn25cd' => 'E12000001',
            'rgn25nm' => 'North East',
        ]);

        $lad = LocalAuthorityDistrict::create([
            'lad25cd' => 'E06000047',
            'lad25nm' => 'County Durham',
            'lad25nmw' => null,
            'rgn25cd' => 'E12000001',
        ]);

        Ward::create([
            'wd25cd' => 'E05013429',
            'wd25nm' => 'Aycliffe North',
            'lad25cd' => 'E06000047',
        ]);

        Ward::create([
            'wd25cd' => 'E05013430',
            'wd25nm' => 'Aycliffe West',
            'lad25cd' => 'E06000047',
        ]);

        $this->assertCount(2, $lad->wards);
        $this->assertInstanceOf(Ward::class, $lad->wards->first());
    }

    /**
     * Test CountyElectoralDivision belongs to County relationship.
     */
    public function test_ced_belongs_to_county_relationship()
    {
        $county = County::create([
            'cty25cd' => 'E10000006',
            'cty25nm' => 'Cumbria',
        ]);

        $ced = CountyElectoralDivision::create([
            'ced25cd' => 'E05013549',
            'ced25nm' => 'Alston & East Fellside',
            'cty25cd' => 'E10000006',
        ]);

        $this->assertInstanceOf(County::class, $ced->county);
        $this->assertEquals('E10000006', $ced->county->cty25cd);
        $this->assertEquals('Cumbria', $ced->county->cty25nm);
    }

    /**
     * Test Parish model supports Welsh language names.
     */
    public function test_parish_supports_welsh_names()
    {
        $region = Region::create([
            'rgn25cd' => 'W92000004',
            'rgn25nm' => 'Wales',
        ]);

        $lad = LocalAuthorityDistrict::create([
            'lad25cd' => 'W06000001',
            'lad25nm' => 'Isle of Anglesey',
            'lad25nmw' => 'Ynys Môn',
            'rgn25cd' => 'W92000004',
        ]);

        $parish = Parish::create([
            'parncp25cd' => 'W04000001',
            'parncp25nm' => 'Amlwch',
            'parncp25nmw' => 'Amlwch',
            'lad25cd' => 'W06000001',
        ]);

        $this->assertEquals('Amlwch', $parish->parncp25nm);
        $this->assertEquals('Amlwch', $parish->parncp25nmw);
        $this->assertNotNull($parish->parncp25nmw);
    }

    /**
     * Test Constituency and PoliceForceArea models have correct table names.
     */
    public function test_constituency_and_pfa_table_names()
    {
        $constituency = new Constituency();
        $pfa = new PoliceForceArea();

        $this->assertEquals('constituencies', $constituency->getTable());
        $this->assertEquals('pcon24cd', $constituency->getKeyName());

        $this->assertEquals('police_force_areas', $pfa->getTable());
        $this->assertEquals('pfa23cd', $pfa->getKeyName());
    }

    /**
     * Test all lookup models have timestamps enabled.
     */
    public function test_lookup_models_have_timestamps_enabled()
    {
        $this->assertTrue((new Region())->timestamps);
        $this->assertTrue((new County())->timestamps);
        $this->assertTrue((new LocalAuthorityDistrict())->timestamps);
        $this->assertTrue((new Ward())->timestamps);
        $this->assertTrue((new CountyElectoralDivision())->timestamps);
        $this->assertTrue((new Parish())->timestamps);
        $this->assertTrue((new Constituency())->timestamps);
        $this->assertTrue((new PoliceForceArea())->timestamps);
    }
}
