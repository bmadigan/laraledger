<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Repository>
 */
class RepositoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->slug(2);
        $owner = fake()->userName();

        return [
            'user_id' => User::factory(),
            'github_id' => fake()->unique()->numberBetween(1000000, 999999999),
            'name' => $name,
            'full_name' => "{$owner}/{$name}",
            'description' => fake()->optional(0.7)->sentence(),
            'default_branch' => fake()->randomElement(['main', 'master']),
            'tag_pattern' => 'v*.*.*',
            'ignored_paths' => null,
            'settings' => null,
            'is_private' => fake()->boolean(30),
            'last_synced_at' => fake()->optional(0.8)->dateTimeThisMonth(),
        ];
    }

    /**
     * Indicate that the repository is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => true,
        ]);
    }

    /**
     * Indicate that the repository is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => false,
        ]);
    }

    /**
     * Indicate that the repository has been synced recently.
     */
    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_synced_at' => now(),
        ]);
    }

    /**
     * Set ignored paths for analysis.
     */
    public function withIgnoredPaths(array $paths = ['tests/', 'docs/']): static
    {
        return $this->state(fn (array $attributes) => [
            'ignored_paths' => $paths,
        ]);
    }
}
