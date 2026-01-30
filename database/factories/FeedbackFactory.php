<?php

namespace Database\Factories;

use App\Models\Feedback;
use App\Models\Release;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Feedback>
 */
class FeedbackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalType = fake()->randomElement([Release::TYPE_MAJOR, Release::TYPE_MINOR, Release::TYPE_PATCH]);
        $userType = fake()->randomElement(array_diff(
            [Release::TYPE_MAJOR, Release::TYPE_MINOR, Release::TYPE_PATCH],
            [$originalType]
        ));

        return [
            'release_id' => Release::factory(),
            'reason_category' => fake()->randomElement([
                Feedback::REASON_BREAKING_MISSED,
                Feedback::REASON_BREAKING_FALSE_POSITIVE,
                Feedback::REASON_FEATURE_MISCATEGORIZED,
                Feedback::REASON_FIX_MISCATEGORIZED,
                Feedback::REASON_CONFIDENCE_HIGH,
                Feedback::REASON_CONFIDENCE_LOW,
                Feedback::REASON_OTHER,
            ]),
            'reason_details' => fake()->optional(0.6)->paragraph(),
            'original_version' => 'v1.0.0',
            'original_type' => $originalType,
            'user_version' => 'v1.1.0',
            'user_type' => $userType,
            'specific_commit_sha' => fake()->optional(0.3)->sha1(),
        ];
    }

    /**
     * Feedback for a missed breaking change.
     */
    public function breakingMissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'reason_category' => Feedback::REASON_BREAKING_MISSED,
            'original_type' => Release::TYPE_MINOR,
            'user_type' => Release::TYPE_MAJOR,
        ]);
    }

    /**
     * Feedback for a false positive breaking change.
     */
    public function breakingFalsePositive(): static
    {
        return $this->state(fn (array $attributes) => [
            'reason_category' => Feedback::REASON_BREAKING_FALSE_POSITIVE,
            'original_type' => Release::TYPE_MAJOR,
            'user_type' => Release::TYPE_MINOR,
        ]);
    }
}
