<?php

namespace Database\Factories;

use App\Models\Release;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Release>
 */
class ReleaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement([Release::TYPE_MAJOR, Release::TYPE_MINOR, Release::TYPE_PATCH]);
        $major = fake()->numberBetween(0, 5);
        $minor = fake()->numberBetween(0, 20);
        $patch = fake()->numberBetween(0, 30);
        $version = "v{$major}.{$minor}.{$patch}";
        $confidence = fake()->numberBetween(60, 100);

        return [
            'repository_id' => Repository::factory(),
            'version' => $version,
            'from_ref' => 'v'.($major > 0 ? $major - 1 : 0).'.'.fake()->numberBetween(0, 10).'.0',
            'to_ref' => 'main',
            'recommended_version' => $version,
            'recommended_type' => $type,
            'final_version' => null,
            'final_type' => null,
            'confidence' => $confidence,
            'status' => Release::STATUS_PENDING,
            'release_notes' => fake()->optional(0.6)->paragraphs(3, true),
            'commits' => $this->generateCommits(),
            'changes' => $this->generateChanges(),
            'heuristic_reasoning' => [
                'conventional_commits_found' => fake()->numberBetween(0, 10),
                'breaking_keywords_found' => fake()->numberBetween(0, 3),
                'file_weight_score' => fake()->randomFloat(2, 0, 1),
            ],
            'ai_reasoning' => null,
            'analysis_source' => fake()->randomElement([Release::SOURCE_HEURISTIC, Release::SOURCE_AI, Release::SOURCE_COMBINED]),
            'cost_usd' => fake()->randomFloat(6, 0, 0.1),
            'duration_ms' => fake()->numberBetween(500, 10000),
        ];
    }

    /**
     * Generate fake commits array.
     *
     * @return array<int, array{sha: string, message: string, author: string, date: string}>
     */
    private function generateCommits(): array
    {
        $count = fake()->numberBetween(3, 15);
        $commits = [];

        for ($i = 0; $i < $count; $i++) {
            $commits[] = [
                'sha' => fake()->sha1(),
                'message' => fake()->randomElement([
                    'feat: '.fake()->sentence(4),
                    'fix: '.fake()->sentence(4),
                    'chore: '.fake()->sentence(4),
                    'docs: '.fake()->sentence(4),
                    fake()->sentence(6),
                ]),
                'author' => fake()->userName(),
                'date' => fake()->dateTimeThisMonth()->format('Y-m-d H:i:s'),
            ];
        }

        return $commits;
    }

    /**
     * Generate fake changes array.
     *
     * @return array{breaking: array<int, string>, features: array<int, string>, fixes: array<int, string>}
     */
    private function generateChanges(): array
    {
        return [
            'breaking' => fake()->optional(0.2)->randomElements(
                [fake()->sentence(5), fake()->sentence(5)],
                fake()->numberBetween(0, 2)
            ) ?? [],
            'features' => fake()->randomElements(
                array_map(fn () => fake()->sentence(5), range(1, 5)),
                fake()->numberBetween(1, 3)
            ),
            'fixes' => fake()->randomElements(
                array_map(fn () => fake()->sentence(5), range(1, 5)),
                fake()->numberBetween(0, 4)
            ),
        ];
    }

    /**
     * Set the release as accepted.
     */
    public function accepted(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Release::STATUS_ACCEPTED,
                'final_version' => $attributes['recommended_version'],
                'final_type' => $attributes['recommended_type'],
            ];
        });
    }

    /**
     * Set the release as adjusted.
     */
    public function adjusted(): static
    {
        return $this->state(function (array $attributes) {
            $newType = fake()->randomElement(array_diff(
                [Release::TYPE_MAJOR, Release::TYPE_MINOR, Release::TYPE_PATCH],
                [$attributes['recommended_type']]
            ));

            return [
                'status' => Release::STATUS_ADJUSTED,
                'final_version' => $this->bumpVersion($attributes['recommended_version'], $newType),
                'final_type' => $newType,
            ];
        });
    }

    /**
     * Set the release as rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Release::STATUS_REJECTED,
            'final_version' => null,
            'final_type' => null,
        ]);
    }

    /**
     * Set high confidence.
     */
    public function highConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence' => fake()->numberBetween(85, 100),
        ]);
    }

    /**
     * Set low confidence.
     */
    public function lowConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence' => fake()->numberBetween(50, 70),
        ]);
    }

    /**
     * Set as major version bump.
     */
    public function major(): static
    {
        return $this->state(fn (array $attributes) => [
            'recommended_type' => Release::TYPE_MAJOR,
        ]);
    }

    /**
     * Set as minor version bump.
     */
    public function minor(): static
    {
        return $this->state(fn (array $attributes) => [
            'recommended_type' => Release::TYPE_MINOR,
        ]);
    }

    /**
     * Set as patch version bump.
     */
    public function patch(): static
    {
        return $this->state(fn (array $attributes) => [
            'recommended_type' => Release::TYPE_PATCH,
        ]);
    }

    /**
     * Calculate a new version based on type.
     */
    private function bumpVersion(string $version, string $type): string
    {
        preg_match('/v?(\d+)\.(\d+)\.(\d+)/', $version, $matches);
        $major = (int) ($matches[1] ?? 0);
        $minor = (int) ($matches[2] ?? 0);
        $patch = (int) ($matches[3] ?? 0);

        return match ($type) {
            Release::TYPE_MAJOR => 'v'.($major + 1).'.0.0',
            Release::TYPE_MINOR => "v{$major}.".($minor + 1).'.0',
            Release::TYPE_PATCH => "v{$major}.{$minor}.".($patch + 1),
            default => $version,
        };
    }
}
