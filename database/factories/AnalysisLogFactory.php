<?php

namespace Database\Factories;

use App\Models\AnalysisLog;
use App\Models\Release;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AnalysisLog>
 */
class AnalysisLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'release_id' => Release::factory(),
            'stage' => fake()->randomElement([
                AnalysisLog::STAGE_FETCH_COMMITS,
                AnalysisLog::STAGE_HEURISTIC_ANALYSIS,
                AnalysisLog::STAGE_AI_CLASSIFICATION,
                AnalysisLog::STAGE_NOTE_GENERATION,
                AnalysisLog::STAGE_FINALIZE,
            ]),
            'duration_ms' => fake()->numberBetween(100, 5000),
            'tokens_used' => fake()->optional(0.5)->numberBetween(100, 2000),
            'model' => fake()->optional(0.5)->randomElement(['claude-3-5-haiku-20241022', 'claude-3-5-sonnet-20241022', 'gpt-4o-mini']),
            'request' => null,
            'response' => null,
            'status' => AnalysisLog::STATUS_SUCCESS,
            'error_message' => null,
        ];
    }

    /**
     * Set the log as an error.
     */
    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnalysisLog::STATUS_ERROR,
            'error_message' => fake()->sentence(),
        ]);
    }

    /**
     * Set the log as skipped.
     */
    public function skipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnalysisLog::STATUS_SKIPPED,
            'duration_ms' => 0,
        ]);
    }

    /**
     * Set the stage to fetch commits.
     */
    public function fetchCommits(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => AnalysisLog::STAGE_FETCH_COMMITS,
            'tokens_used' => null,
            'model' => null,
        ]);
    }

    /**
     * Set the stage to heuristic analysis.
     */
    public function heuristicAnalysis(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => AnalysisLog::STAGE_HEURISTIC_ANALYSIS,
            'tokens_used' => null,
            'model' => null,
        ]);
    }

    /**
     * Set the stage to AI classification.
     */
    public function aiClassification(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => AnalysisLog::STAGE_AI_CLASSIFICATION,
            'tokens_used' => fake()->numberBetween(500, 2000),
            'model' => fake()->randomElement(['claude-3-5-haiku-20241022', 'gpt-4o-mini']),
        ]);
    }

    /**
     * Set the stage to note generation.
     */
    public function noteGeneration(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => AnalysisLog::STAGE_NOTE_GENERATION,
            'tokens_used' => fake()->numberBetween(1000, 4000),
            'model' => fake()->randomElement(['claude-3-5-sonnet-20241022', 'gpt-4o']),
        ]);
    }
}
