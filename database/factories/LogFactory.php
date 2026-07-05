<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Log>
 */
class LogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'logged_on' => fake()->unique()->dateTimeBetween('-60 days', 'today')->format('Y-m-d'),
            'stress' => fake()->numberBetween(0, 10),
            'stamina' => fake()->numberBetween(0, 10),
            'mental_capacity' => fake()->numberBetween(0, 10),
            'hardest_text' => fake()->optional()->sentence(),
            'summary_text' => fake()->optional()->sentence(),
        ];
    }
}
