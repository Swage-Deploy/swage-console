<?php

namespace Database\Factories;

use App\Models\ServerReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerReading>
 */
class ServerReadingFactory extends Factory
{
    protected $model = ServerReading::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'main',
            'server_id' => fake()->numberBetween(1, 10),
            'cpu_percent' => fake()->randomFloat(1, 0, 100),
            'memory_percent' => fake()->randomFloat(1, 0, 100),
            'disk_percent' => fake()->randomFloat(1, 0, 100),
            'swap_percent' => fake()->randomFloat(1, 0, 20),
            'load_1' => fake()->randomFloat(2, 0, 4),
            'read_at' => now()->subMinutes(fake()->numberBetween(0, 1440))->startOfMinute(),
        ];
    }
}
