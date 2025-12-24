<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostcodeLookupTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Use existing user or create one for testing
        $this->user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('password')]
        );
    }

    public function test_it_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/postcodes/SW1A1AA');

        $response->assertStatus(401);
    }

    public function test_it_returns_postcode_data_with_correct_structure(): void
    {
        Sanctum::actingAs($this->user);

        // Use a known postcode that should exist in test database
        $response = $this->getJson('/api/v1/postcodes/SW1A1AA');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'postcode',
                    'coordinates' => [
                        'wgs84' => ['latitude', 'longitude'],
                        'os_grid' => ['easting', 'northing'],
                    ],
                    'geography' => [
                        'ward',
                        'county_electoral_division',
                        'parish',
                        'local_authority_district',
                        'constituency',
                        'region',
                        'police_force_area',
                    ],
                    'property_count',
                ],
                'meta' => [
                    'api_version',
                    'timestamp',
                ],
            ]);
    }

    public function test_it_includes_uprns_when_requested(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/postcodes/SW1A1AA?include=uprns');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'uprns',
                ],
            ]);
    }

    public function test_it_excludes_uprns_by_default(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/postcodes/SW1A1AA');

        $response->assertStatus(200)
            ->assertJsonMissing(['uprns']);
    }

    public function test_it_returns_404_for_nonexistent_postcode(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/postcodes/ZZ991ZZ');

        $response->assertStatus(404)
            ->assertJson([
                'error' => [
                    'code' => 'POSTCODE_NOT_FOUND',
                ],
            ]);
    }

    public function test_it_normalizes_postcode_format(): void
    {
        Sanctum::actingAs($this->user);

        // Test lowercase without space
        $response1 = $this->getJson('/api/v1/postcodes/sw1a1aa');
        $response1->assertStatus(200);

        // Test uppercase without space
        $response2 = $this->getJson('/api/v1/postcodes/SW1A1AA');
        $response2->assertStatus(200);

        // Test with space
        $response3 = $this->getJson('/api/v1/postcodes/SW1A%201AA');
        $response3->assertStatus(200);
    }

    public function test_it_validates_invalid_postcode_format(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/postcodes/INVALID');

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'INVALID_POSTCODE',
                ],
            ]);
    }

    public function test_it_validates_invalid_include_parameter(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/postcodes/SW1A1AA?include=invalid');

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                ],
            ]);
    }

    public function test_it_returns_correct_geography_data_structure(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/postcodes/SW1A1AA');

        $response->assertStatus(200);

        $geography = $response->json('data.geography');

        // Each geography item should have code and name when present
        foreach ($geography as $item) {
            if ($item !== null) {
                $this->assertArrayHasKey('code', $item);
                $this->assertArrayHasKey('name', $item);
            }
        }
    }

    public function test_it_returns_numeric_coordinates(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/postcodes/SW1A1AA');

        $response->assertStatus(200);

        $coordinates = $response->json('data.coordinates');

        $this->assertIsNumeric($coordinates['wgs84']['latitude']);
        $this->assertIsNumeric($coordinates['wgs84']['longitude']);
        $this->assertIsNumeric($coordinates['os_grid']['easting']);
        $this->assertIsNumeric($coordinates['os_grid']['northing']);
    }
}
