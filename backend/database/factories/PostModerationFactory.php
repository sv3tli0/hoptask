<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostModeration>
 */
class PostModerationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => \App\Models\Post::factory(),
            'approved' => $this->faker->boolean(70), // 70% chance of approval
            'categories' => null, // Will be populated by Gemini service
            'severity' => $this->faker->randomElement(['low', 'medium', 'high']),
            'confidence' => $this->faker->randomFloat(2, 0.5, 1.0),
            'reason' => $this->faker->optional(0.3)->sentence(),
            'error' => $this->faker->boolean(10), // 10% chance of error
        ];
    }
}
