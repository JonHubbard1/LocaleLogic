<?php

namespace Tests\Unit\Services;

use App\Models\Council;
use App\Services\DemocracyClubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemocracyClubServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Council::where('gss_code', 'like', '%ZZZ%')->delete();
    }

    public function test_find_council_organization_returns_id_on_exact_match(): void
    {
        Http::fake([
            'candidates.democracyclub.org.uk/api/v0.9/organizations/*' => Http::response([
                'results' => [
                    [
                        'id' => 'party:53',
                        'name' => 'Testshire Council',
                        'classification' => 'Party',
                    ],
                ],
            ], 200),
        ]);

        $council = Council::factory()->create([
            'name' => 'Testshire Council',
            'gss_code' => 'E06ZZZ001',
        ]);

        $service = new DemocracyClubService();
        $result = $service->findCouncilOrganization($council);

        $this->assertSame('party:53', $result);
    }

    public function test_find_council_organization_returns_null_when_no_match(): void
    {
        Http::fake([
            'candidates.democracyclub.org.uk/api/v0.9/organizations/*' => Http::response([
                'results' => [],
            ], 200),
        ]);

        $council = Council::factory()->create([
            'name' => 'Unknownshire Council',
            'gss_code' => 'E06ZZZ002',
        ]);

        $service = new DemocracyClubService();
        $result = $service->findCouncilOrganization($council);

        $this->assertNull($result);
    }

    public function test_fetch_elected_councillors_returns_empty_when_no_wards(): void
    {
        Http::fake([
            'candidates.democracyclub.org.uk/api/v0.9/memberships/*' => Http::response([
                'results' => [],
                'next' => null,
            ], 200),
        ]);

        $council = Council::factory()->create([
            'name' => 'Testshire Council',
            'gss_code' => 'E06ZZZ003',
        ]);

        $service = new DemocracyClubService();
        $result = $service->fetchElectedCouncillors($council);

        $this->assertEmpty($result);
    }

    public function test_fetch_elected_councillors_maps_post_gss_codes_to_wards(): void
    {
        \Illuminate\Support\Facades\DB::table('ward_hierarchy_lookups')->insert([
            'wd_code' => 'E05ZZZ001',
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

        Http::fake([
            'candidates.democracyclub.org.uk/api/v0.9/memberships/*' => Http::response([
                'results' => [
                    [
                        'id' => 1,
                        'elected' => true,
                        'person' => [
                            'id' => 123,
                            'name' => 'Jane Smith',
                            'url' => 'http://example.com/person/123',
                        ],
                        'on_behalf_of' => [
                            'id' => 'party:1',
                            'name' => 'Labour Party',
                        ],
                        'post' => [
                            'id' => 'gss:E05ZZZ001',
                            'label' => 'Test Ward',
                        ],
                    ],
                ],
                'next' => null,
            ], 200),
            'candidates.democracyclub.org.uk/api/v0.9/persons/123/' => Http::response([
                'id' => 123,
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'contact_details' => [
                    ['type' => 'email', 'value' => 'jane@example.com'],
                ],
            ], 200),
        ]);

        $council = Council::factory()->create([
            'name' => 'Testshire Council',
            'gss_code' => 'E06ZZZ004',
        ]);

        $service = new DemocracyClubService();
        $result = $service->fetchElectedCouncillors($council);

        $this->assertCount(1, $result);
        $this->assertSame('Jane Smith', $result[0]['name']);
        $this->assertSame('E05ZZZ001', $result[0]['ward_gss_code']);
        $this->assertSame('Labour Party', $result[0]['party']);
        $this->assertSame('democracy_club', $result[0]['source']);
    }
}
