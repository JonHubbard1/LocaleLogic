<?php

namespace Tests\Feature\Services;

use App\Exceptions\InvalidImportException;
use App\Models\GeographyVersion;
use App\Services\GeographyVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeographyVersionServiceTest extends TestCase
{
    use RefreshDatabase;

    private GeographyVersionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GeographyVersionService();
    }

    public function test_allows_first_import_when_no_version_exists(): void
    {
        $result = $this->service->validateImport('lad', '25');

        $this->assertTrue($result);
    }

    public function test_allows_import_with_newer_year_code(): void
    {
        GeographyVersion::create([
            'geography_type' => 'lad',
            'year_code' => '25',
            'record_count' => 100,
            'status' => 'current',
            'release_date' => now(),
        ]);

        $result = $this->service->validateImport('lad', '26');

        $this->assertTrue($result);
    }

    public function test_allows_import_with_same_year_code(): void
    {
        GeographyVersion::create([
            'geography_type' => 'lad',
            'year_code' => '25',
            'record_count' => 100,
            'status' => 'current',
            'release_date' => now(),
        ]);

        $result = $this->service->validateImport('lad', '25');

        $this->assertTrue($result);
    }

    public function test_rejects_import_with_older_year_code(): void
    {
        GeographyVersion::create([
            'geography_type' => 'lad',
            'year_code' => '25',
            'record_count' => 100,
            'status' => 'current',
            'release_date' => now(),
        ]);

        $this->expectException(InvalidImportException::class);
        $this->expectExceptionMessage('Cannot import lad year 24 - current version is 25');

        $this->service->validateImport('lad', '24');
    }

    public function test_validates_different_geography_types_independently(): void
    {
        GeographyVersion::create([
            'geography_type' => 'lad',
            'year_code' => '25',
            'record_count' => 100,
            'status' => 'current',
            'release_date' => now(),
        ]);

        // ward has no version yet, should allow any year
        $result = $this->service->validateImport('ward', '24');

        $this->assertTrue($result);
    }

    public function test_records_first_import(): void
    {
        $this->service->recordImport('lad', '25', 150, 'LAD_JAN_2025.csv');

        $this->assertDatabaseHas('geography_versions', [
            'geography_type' => 'lad',
            'year_code' => '25',
            'record_count' => 150,
            'source_file' => 'LAD_JAN_2025.csv',
            'status' => 'current',
        ]);
    }

    public function test_records_import_without_source_file(): void
    {
        $this->service->recordImport('ward', '25', 200);

        $this->assertDatabaseHas('geography_versions', [
            'geography_type' => 'ward',
            'year_code' => '25',
            'record_count' => 200,
            'source_file' => null,
            'status' => 'current',
        ]);
    }

    public function test_archives_previous_version_when_recording_new_import(): void
    {
        $this->service->recordImport('lad', '25', 150);
        $this->service->recordImport('lad', '26', 160);

        $this->assertDatabaseHas('geography_versions', [
            'geography_type' => 'lad',
            'year_code' => '25',
            'status' => 'archived',
        ]);

        $this->assertDatabaseHas('geography_versions', [
            'geography_type' => 'lad',
            'year_code' => '26',
            'status' => 'current',
        ]);
    }

    public function test_only_archives_current_version_for_same_geography_type(): void
    {
        $this->service->recordImport('lad', '25', 150);
        $this->service->recordImport('ward', '25', 200);
        $this->service->recordImport('lad', '26', 160);

        // Ward version should still be current
        $this->assertDatabaseHas('geography_versions', [
            'geography_type' => 'ward',
            'year_code' => '25',
            'status' => 'current',
        ]);

        // LAD 25 should be archived
        $this->assertDatabaseHas('geography_versions', [
            'geography_type' => 'lad',
            'year_code' => '25',
            'status' => 'archived',
        ]);

        // LAD 26 should be current
        $this->assertDatabaseHas('geography_versions', [
            'geography_type' => 'lad',
            'year_code' => '26',
            'status' => 'current',
        ]);
    }

    public function test_sets_release_date_and_imported_at_timestamps(): void
    {
        $this->service->recordImport('lad', '25', 150);

        $version = GeographyVersion::where('geography_type', 'lad')
            ->where('year_code', '25')
            ->first();

        $this->assertNotNull($version->release_date);
        $this->assertNotNull($version->imported_at);
    }
}
