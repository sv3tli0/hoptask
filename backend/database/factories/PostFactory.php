<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content' => fake()->paragraph(),
            'status' => fake()->randomElement(Arr::pluck(PostStatus::cases(), 'value')),
            'moderation_reason' => fake()->optional()->sentence(10),
        ];
    }
}
