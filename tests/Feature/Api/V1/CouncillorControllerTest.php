<?php

namespace Tests\Feature\Api\V1;

use App\Models\Council;
use App\Models\Councillor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CouncillorControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('password')]
        );
        // Clean up any leftover test councils from previous runs
        Council::where('gss_code', 'like', '%ZZZ%')->delete();
        Councillor::where('ward_gss_code', 'like', '%ZZZ%')->delete();
        DB::table('ward_hierarchy_lookups')->where('wd_code', 'like', '%ZZZ%')->delete();
    }

    public function test_it_requires_authentication_for_councils_list(): void
    {
        $response = $this->getJson('/api/v1/councils/all');
        $response->assertStatus(401);
    }

    public function test_it_returns_all_councils(): void
    {
        Sanctum::actingAs($this->user);

        $council = Council::factory()->create([
            'name' => 'Testshire Council',
            'gss_code' => 'E06ZZZ999',
            'council_type' => 'unitary',
            'nation' => 'england',
        ]);

        $response = $this->getJson('/api/v1/councils/all');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'gss_code',
                        'name',
                        'type',
                        'nation',
                        'uses_modern_gov',
                    ],
                ],
                'meta' => ['count'],
            ]);

        $response->assertJsonFragment([
            'gss_code' => 'E06ZZZ999',
            'name' => 'Testshire Council',
        ]);
    }

    public function test_it_filters_councils_by_nation(): void
    {
        Sanctum::actingAs($this->user);

        Council::factory()->create(['nation' => 'england', 'gss_code' => 'E06ZZZ001']);
        Council::factory()->create(['nation' => 'scotland', 'gss_code' => 'S12ZZZ001']);

        $response = $this->getJson('/api/v1/councils/all?nation=scotland');
        $response->assertStatus(200)
            ->assertJsonFragment(['gss_code' => 'S12ZZZ001']);

        $this->assertGreaterThanOrEqual(1, $response->json('meta.count'));
    }

    public function test_it_returns_single_council_details(): void
    {
        Sanctum::actingAs($this->user);

        $council = Council::factory()->create([
            'gss_code' => 'E06ZZZ002',
            'name' => 'Testshire Council',
            'uses_modern_gov' => true,
        ]);

        $response = $this->getJson('/api/v1/councils/all/E06ZZZ002');
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'gss_code' => 'E06ZZZ002',
                    'name' => 'Testshire Council',
                    'uses_modern_gov' => true,
                    'councillor_count' => 0,
                ],
            ]);
    }

    public function test_it_returns_404_for_missing_council(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/councils/all/ZZ9999999');
        $response->assertStatus(404)
            ->assertJson([
                'error' => [
                    'code' => 'COUNCIL_NOT_FOUND',
                ],
            ]);
    }

    public function test_it_returns_councillors_for_a_council(): void
    {
        Sanctum::actingAs($this->user);

        $council = Council::factory()->create(['gss_code' => 'E06ZZZ003']);
        $councillor = Councillor::factory()->create([
            'council_gss_code' => 'E06ZZZ003',
            'ward_gss_code' => 'E05ZZZ001',
            'name' => 'John Smith',
            'party' => 'Conservative',
        ]);

        $response = $this->getJson('/api/v1/councils/all/E06ZZZ003/councillors');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'name',
                        'party',
                        'ward',
                    ],
                ],
                'meta' => [
                    'council_code',
                    'council_name',
                    'count',
                ],
            ]);

        $response->assertJsonFragment([
            'name' => 'John Smith',
            'party' => 'Conservative',
        ]);
    }

    public function test_it_returns_councillors_for_a_ward(): void
    {
        Sanctum::actingAs($this->user);

        DB::table('ward_hierarchy_lookups')->insert([
            'wd_code' => 'E05ZZZ002',
            'wd_name' => 'Test Ward',
            'lad_code' => 'E06ZZZ004',
            'lad_name' => 'Testshire',
            'cty_code' => 'E10ZZZ001',
            'cty_name' => 'Test County',
            'version_date' => '2025-01-01',
            'source' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $council = Council::factory()->create(['gss_code' => 'E06ZZZ004']);
        $councillor = Councillor::factory()->create([
            'council_gss_code' => 'E06ZZZ004',
            'ward_gss_code' => 'E05ZZZ002',
            'name' => 'Jane Doe',
        ]);

        $response = $this->getJson('/api/v1/wards/E05ZZZ002/councillors');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'name',
                        'party',
                        'council',
                    ],
                ],
                'meta' => [
                    'ward_code',
                    'ward_name',
                    'count',
                ],
            ]);

        $response->assertJsonFragment([
            'name' => 'Jane Doe',
            'ward_name' => 'Test Ward',
        ]);
    }

    public function test_it_returns_404_for_missing_ward(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/wards/ZZ9999999/councillors');
        $response->assertStatus(404)
            ->assertJson([
                'error' => [
                    'code' => 'WARD_NOT_FOUND',
                ],
            ]);
    }
}
