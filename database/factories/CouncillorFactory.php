<?php

namespace Database\Factories;

use App\Models\Councillor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Councillor>
 */
class CouncillorFactory extends Factory
{
    protected $model = Councillor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $parties = ['Conservative', 'Labour', 'Liberal Democrat', 'Green', 'Independent', 'SNP', 'Plaid Cymru', 'UKIP'];
        $prefixes = ['E05', 'E08', 'W05', 'S13'];
        $wardPrefix = $this->faker->randomElement($prefixes);
        $wardGssCode = $wardPrefix . str_pad((string) $this->faker->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT);
        $councilPrefix = $this->faker->randomElement(['E06', 'E07', 'E09', 'E10', 'S12', 'W06', 'N09']);
        $councilGssCode = $councilPrefix . str_pad((string) $this->faker->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT);

        return [
            'council_gss_code' => $councilGssCode,
            'ward_gss_code' => $wardGssCode,
            'name' => $this->faker->name,
            'party' => $this->faker->randomElement($parties),
            'email' => $this->faker->optional()->email,
            'phone' => $this->faker->optional()->phoneNumber,
            'photo_url' => $this->faker->optional()->imageUrl,
            'profile_url' => $this->faker->optional()->url,
            'source' => $this->faker->randomElement(['democracy_club', 'modern_gov', 'manual']),
            'scraped_at' => now()->subDays($this->faker->numberBetween(1, 30)),
        ];
    }

    /**
     * Set the source to Democracy Club.
     */
    public function fromDemocracyClub(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'democracy_club',
        ]);
    }

    /**
     * Set the source to ModernGov.
     */
    public function fromModernGov(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'modern_gov',
        ]);
    }
}
