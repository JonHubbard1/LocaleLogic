<?php

namespace Tests\Feature\Commands;

use App\Models\Council;
use App\Models\Councillor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportCouncillorsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Council::where('gss_code', 'like', '%ZZZ%')->delete();
        Councillor::where('ward_gss_code', 'like', '%ZZZ%')->delete();
    }

    public function test_command_rejects_invalid_source_option(): void
    {
        Council::factory()->create([
            'gss_code' => 'E06ZZZ001',
            'uses_modern_gov' => true,
            'modern_gov_base_url' => 'https://example.com',
        ]);

        $this->artisan('councillors:import', [
            'gssCode' => 'E06ZZZ001',
            '--source' => 'invalid',
        ])
            ->expectsOutputToContain("Invalid source 'invalid'")
            ->assertFailed();
    }

    public function test_command_with_auto_source_falls_back_to_modern_gov(): void
    {
        Http::fake([
            '*' => Http::response('Not Found', 404),
        ]);

        Council::factory()->create([
            'gss_code' => 'E06ZZZ002',
            'uses_modern_gov' => true,
            'modern_gov_base_url' => 'https://example.com',
        ]);

        $this->artisan('councillors:import', [
            'gssCode' => 'E06ZZZ002',
            '--source' => 'auto',
        ])
            ->expectsOutputToContain('Inserted: 0')
            ->assertSuccessful();
    }

    public function test_command_imports_modern_gov(): void
    {
        Http::fake([
            '*' => Http::response('Not Found', 404),
        ]);

        Council::factory()->create([
            'gss_code' => 'E06ZZZ003',
            'uses_modern_gov' => true,
            'modern_gov_base_url' => 'https://example.com',
        ]);

        $this->artisan('councillors:import', [
            'gssCode' => 'E06ZZZ003',
            '--source' => 'modern_gov',
        ])
            ->assertSuccessful();
    }

    public function test_command_rejects_democracy_club_source(): void
    {
        Council::factory()->create([
            'gss_code' => 'E06ZZZ004',
            'uses_democracy_club' => true,
            'democracy_club_org_id' => 'org:2',
        ]);

        $this->artisan('councillors:import', [
            'gssCode' => 'E06ZZZ004',
            '--source' => 'democracy_club',
        ])
            ->expectsOutputToContain('Democracy Club import is currently disabled')
            ->assertFailed();
    }
}
