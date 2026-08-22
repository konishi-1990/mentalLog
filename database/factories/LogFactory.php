<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\DayTypes;
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
            'sleep_hours' => fake()->optional()->randomElement([4.0, 5.5, 6.0, 6.5, 7.0, 7.5, 8.0]),
            'sleep_quality' => fake()->optional()->numberBetween(0, 10),
            'carryover' => fake()->optional()->numberBetween(0, 10),
            'controllability' => fake()->optional()->numberBetween(0, 10),
            'day_type' => fake()->optional()->randomElement(DayTypes::codes()),
            'hardest_text' => fake()->optional()->sentence(),
            'summary_text' => fake()->optional()->sentence(),
        ];
    }
}
