<?php

namespace Tests\Feature\Admin;

use App\Models\Council;
use App\Models\User;
use Tests\TestCase;

class CouncilManagerTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin User', 'password' => bcrypt('password')]
        );
        // Clean up any leftover test councils from previous runs
        Council::where('gss_code', 'like', '%ZZZ%')->delete();
    }

    public function test_council_manager_page_requires_auth(): void
    {
        $response = $this->get(route('admin.councils'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_council_manager(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('admin.councils'));
        $response->assertStatus(200)
            ->assertSee('Council Manager');
    }

    public function test_council_list_displays_councils(): void
    {
        $this->actingAs($this->user);

        Council::factory()->create([
            'name' => 'Testshire Council',
            'gss_code' => 'E06ZZZ100',
        ]);

        $response = $this->get(route('admin.councils', ['search' => 'Testshire']));
        $response->assertStatus(200)
            ->assertSee('Testshire Council')
            ->assertSee('E06ZZZ100');
    }

    public function test_search_filters_councils(): void
    {
        $this->actingAs($this->user);

        Council::factory()->create(['name' => 'Testshire Council', 'gss_code' => 'E06ZZZ101']);
        Council::factory()->create(['name' => 'York Council', 'gss_code' => 'E06ZZZ102']);

        $response = $this->get(route('admin.councils', ['search' => 'Testshire']));
        $response->assertStatus(200)
            ->assertSee('Testshire Council')
            ->assertDontSee('York Council');
    }

    public function test_nation_filter_works(): void
    {
        $this->actingAs($this->user);

        Council::factory()->create(['name' => 'Testshire', 'nation' => 'england', 'gss_code' => 'E06ZZZ103']);
        Council::factory()->create(['name' => 'Cardiff', 'nation' => 'wales', 'gss_code' => 'W06ZZZ103']);

        $response = $this->get(route('admin.councils', ['nationFilter' => 'wales']));
        $response->assertStatus(200)
            ->assertSee('Cardiff')
            ->assertDontSee('Testshire');
    }

    public function test_modern_gov_filter_works(): void
    {
        $this->actingAs($this->user);

        Council::factory()->create([
            'name' => 'Modern Council',
            'uses_modern_gov' => true,
            'gss_code' => 'E06ZZZ104',
        ]);
        Council::factory()->create([
            'name' => 'Old Council',
            'uses_modern_gov' => false,
            'gss_code' => 'E06ZZZ105',
        ]);

        $response = $this->get(route('admin.councils', ['modernGovFilter' => 'yes', 'search' => 'Modern']));
        $response->assertStatus(200)
            ->assertSee('Modern Council')
            ->assertDontSee('Old Council');
    }
}
