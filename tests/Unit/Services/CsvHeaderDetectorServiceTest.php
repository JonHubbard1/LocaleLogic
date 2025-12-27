<?php

namespace Tests\Unit\Services;

use App\Services\CsvHeaderDetectorService;
use PHPUnit\Framework\TestCase;

class CsvHeaderDetectorServiceTest extends TestCase
{
    private CsvHeaderDetectorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CsvHeaderDetectorService();
    }

    public function test_detects_lad_year_code(): void
    {
        $headers = ['LAD25CD', 'LAD25NM', 'LAD25NMW'];

        $result = $this->service->detectYearCodes($headers);

        $this->assertArrayHasKey('lad', $result);
        $this->assertEquals('25', $result['lad']);
    }

    public function test_detects_ward_year_code(): void
    {
        $headers = ['WD25CD', 'WD25NM', 'LAD25CD'];

        $result = $this->service->detectYearCodes($headers);

        $this->assertArrayHasKey('ward', $result);
        $this->assertEquals('25', $result['ward']);
    }

    public function test_detects_multiple_geography_types(): void
    {
        $headers = ['LAD25CD', 'LAD25NM', 'WD25CD', 'WD25NM', 'RGN25CD'];

        $result = $this->service->detectYearCodes($headers);

        $this->assertEquals([
            'lad' => '25',
            'ward' => '25',
            'region' => '25',
        ], $result);
    }

    public function test_detects_parish_year_code(): void
    {
        $headers = ['PARNCP25CD', 'PARNCP25NM'];

        $result = $this->service->detectYearCodes($headers);

        $this->assertArrayHasKey('parish', $result);
        $this->assertEquals('25', $result['parish']);
    }

    public function test_detects_ced_year_code(): void
    {
        $headers = ['CED25CD', 'CED25NM'];

        $result = $this->service->detectYearCodes($headers);

        $this->assertArrayHasKey('ced', $result);
        $this->assertEquals('25', $result['ced']);
    }

    public function test_detects_constituency_year_code(): void
    {
        $headers = ['PCON24CD', 'PCON24NM'];

        $result = $this->service->detectYearCodes($headers);

        $this->assertArrayHasKey('constituency', $result);
        $this->assertEquals('24', $result['constituency']);
    }

    public function test_detects_county_year_code(): void
    {
        $headers = ['CTY25CD', 'CTY25NM'];

        $result = $this->service->detectYearCodes($headers);

        $this->assertArrayHasKey('county', $result);
        $this->assertEquals('25', $result['county']);
    }

    public function test_detects_police_force_area_year_code(): void
    {
        $headers = ['PFA23CD', 'PFA23NM'];

        $result = $this->service->detectYearCodes($headers);

        $this->assertArrayHasKey('pfa', $result);
        $this->assertEquals('23', $result['pfa']);
    }

    public function test_detects_different_year_codes(): void
    {
        $headers = ['LAD26CD', 'WD27CD', 'RGN28CD'];

        $result = $this->service->detectYearCodes($headers);

        $this->assertEquals([
            'lad' => '26',
            'ward' => '27',
            'region' => '28',
        ], $result);
    }

    public function test_returns_empty_array_when_no_patterns_match(): void
    {
        $headers = ['NAME', 'EMAIL', 'PHONE'];

        $result = $this->service->detectYearCodes($headers);

        $this->assertEquals([], $result);
    }

    public function test_uses_last_occurrence_of_each_type(): void
    {
        $headers = ['LAD25CD', 'LAD26CD', 'LAD27CD'];

        $result = $this->service->detectYearCodes($headers);

        // The code currently uses the last match found
        $this->assertEquals(['lad' => '27'], $result);
    }

    public function test_builds_field_mapping_for_lad(): void
    {
        $headers = ['LAD25CD', 'LAD25NM', 'LAD25NMW'];

        $result = $this->service->buildFieldMapping($headers);

        $this->assertEquals(['lad' => 'LAD25CD'], $result);
    }

    public function test_builds_field_mapping_for_multiple_types(): void
    {
        $headers = ['LAD25CD', 'LAD25NM', 'WD25CD', 'WD25NM', 'RGN25CD'];

        $result = $this->service->buildFieldMapping($headers);

        $this->assertEquals([
            'lad' => 'LAD25CD',
            'ward' => 'WD25CD',
            'region' => 'RGN25CD',
        ], $result);
    }

    public function test_builds_field_mapping_only_for_code_fields(): void
    {
        $headers = ['LAD25CD', 'LAD25NM', 'LAD25NMW', 'OBJECTID'];

        $result = $this->service->buildFieldMapping($headers);

        // Should only map the CD field, not NM or NMW
        $this->assertEquals(['lad' => 'LAD25CD'], $result);
    }

    public function test_builds_field_mapping_returns_empty_when_no_match(): void
    {
        $headers = ['NAME', 'EMAIL', 'PHONE'];

        $result = $this->service->buildFieldMapping($headers);

        $this->assertEquals([], $result);
    }

    public function test_builds_field_mapping_uses_last_occurrence(): void
    {
        $headers = ['LAD25CD', 'LAD25NM', 'LAD25NMW', 'LAD26CD'];

        $result = $this->service->buildFieldMapping($headers);

        // The code currently uses the last match found
        $this->assertEquals(['lad' => 'LAD26CD'], $result);
    }
}
