<?php

namespace Database\Factories;

use App\Models\Council;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Council>
 */
class CouncilFactory extends Factory
{
    protected $model = Council::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['unitary', 'district', 'london_borough', 'county', 'metropolitan', 'scottish', 'welsh', 'ni'];
        $nations = ['england', 'scotland', 'wales', 'northern_ireland'];
        $prefixes = ['E06', 'E07', 'E08', 'E09', 'E10', 'S12', 'W06', 'N09'];
        $prefix = $this->faker->randomElement($prefixes);
        $gssCode = $prefix . str_pad((string) $this->faker->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT);

        return [
            'gss_code' => $gssCode,
            'name' => $this->faker->city . ' Council',
            'name_welsh' => str_starts_with($prefix, 'W') ? 'Cyngor ' . $this->faker->city : null,
            'council_type' => Council::inferTypeFromGssCode($gssCode),
            'nation' => Council::inferNationFromGssCode($gssCode),
            'region' => $this->faker->randomElement(['South East', 'North West', 'London', 'Scotland', 'Wales', 'West Midlands']),
            'uses_modern_gov' => null,
            'modern_gov_base_url' => null,
            'democracy_url' => null,
            'website_url' => $this->faker->url,
            'source' => 'factory',
            'scraped_at' => null,
        ];
    }

    /**
     * Indicate that the council uses ModernGov.
     */
    public function modernGov(): static
    {
        return $this->state(fn (array $attributes) => [
            'uses_modern_gov' => true,
            'modern_gov_base_url' => 'https://democracy.' . str_replace(' ', '-', strtolower($attributes['name'])) . '.gov.uk',
        ]);
    }

    /**
     * Indicate that the council does not use ModernGov.
     */
    public function notModernGov(): static
    {
        return $this->state(fn (array $attributes) => [
            'uses_modern_gov' => false,
        ]);
    }
}
