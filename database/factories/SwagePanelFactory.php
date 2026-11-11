<?php

namespace Database\Factories;

use App\Models\SwagePanel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SwagePanel>
 */
class SwagePanelFactory extends Factory
{
    protected $model = SwagePanel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'url' => 'https://'.fake()->unique()->domainWord().'.test',
            'token' => fake()->sha256(),
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => ['enabled' => false]);
    }
}
