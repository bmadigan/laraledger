<?php

namespace App\Services\Analysis;

use App\Models\AnalysisLog;
use App\Models\Release;
use App\Models\Repository;
use App\Models\Setting;
use App\Services\GitHub\GitHubService;

class AnalysisService
{
    protected HeuristicAnalyzer $heuristicAnalyzer;

    protected AIClassifier $aiClassifier;

    protected NoteGenerator $noteGenerator;

    protected ?Release $release = null;

    protected array $stages = [];

    public function __construct(
        HeuristicAnalyzer $heuristicAnalyzer,
        AIClassifier $aiClassifier,
        NoteGenerator $noteGenerator
    ) {
        $this->heuristicAnalyzer = $heuristicAnalyzer;
        $this->aiClassifier = $aiClassifier;
        $this->noteGenerator = $noteGenerator;
    }

    /**
     * Analyze a release and return the recommendation.
     *
     * @param  Repository  $repository  The repository to analyze
     * @param  string  $fromRef  The starting reference (tag/branch/commit)
     * @param  string  $toRef  The ending reference (tag/branch/commit)
     * @param  array  $options  Analysis options: force_ai, depth, note_style
     * @return Release The created release with recommendation
     */
    public function analyze(Repository $repository, string $fromRef, string $toRef, array $options = []): Release
    {
        $startTime = microtime(true);

        // Create the release record
        $this->release = $repository->releases()->create([
            'from_ref' => $fromRef,
            'to_ref' => $toRef,
            'status' => Release::STATUS_PENDING,
        ]);

        try {
            // Stage 1: Fetch commits
            $commits = $this->fetchCommits($repository, $fromRef, $toRef);

            // Stage 2: Heuristic analysis
            $heuristicResult = $this->runHeuristicAnalysis($commits, $repository->ignored_paths ?? []);

            // Stage 3: AI classification (if needed)
            $classificationResult = $this->runAIClassification($commits, $heuristicResult, $options);

            // Determine final recommendation
            $recommendation = $this->resolveRecommendation($heuristicResult, $classificationResult);

            // Calculate recommended version
            $recommendedVersion = $this->calculateVersion($repository, $fromRef, $recommendation['recommended_type']);

            // Stage 4: Generate release notes
            $noteStyle = $options['note_style'] ?? config('laraledger.analysis.default_note_style', 'technical');
            $releaseNotes = $this->generateReleaseNotes(
                $recommendedVersion,
                $recommendation['recommended_type'],
                $recommendation['changes'],
                $noteStyle
            );

            // Stage 5: Finalize
            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $this->finalizeRelease($recommendedVersion, $recommendation, $releaseNotes, $commits, $duration);

            return $this->release->fresh();
        } catch (\Exception $e) {
            // Log the error and update release status
            $this->release->update([
                'status' => Release::STATUS_PENDING,
                'reasoning' => ['error' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    /**
     * Stage 1: Fetch commits from GitHub.
     */
    protected function fetchCommits(Repository $repository, string $fromRef, string $toRef): array
    {
        $stageStart = microtime(true);

        $github = new GitHubService($repository->user);
        $commits = $github->getCommitsBetween($repository->full_name, $fromRef, $toRef);

        $this->logStage(AnalysisLog::STAGE_FETCH_COMMITS, $stageStart, [
            'commit_count' => count($commits),
            'from_ref' => $fromRef,
            'to_ref' => $toRef,
        ]);

        return $commits;
    }

    /**
     * Stage 2: Run heuristic analysis.
     */
    protected function runHeuristicAnalysis(array $commits, array $ignoredPaths): array
    {
        $stageStart = microtime(true);

        $result = $this->heuristicAnalyzer->analyze($commits, $ignoredPaths);

        $this->logStage(AnalysisLog::STAGE_HEURISTIC, $stageStart, [
            'recommended_type' => $result['recommended_type'],
            'confidence' => $result['confidence'],
            'signal_count' => count($result['signals'] ?? []),
        ]);

        return $result;
    }

    /**
     * Stage 3: Run AI classification if needed.
     */
    protected function runAIClassification(array $commits, array $heuristicResult, array $options): ?array
    {
        $confidenceThreshold = (int) (Setting::get('heuristic_confidence_threshold')
            ?? config('laraledger.heuristics.confidence_threshold', 85));

        $alwaysUseAI = ($options['force_ai'] ?? false)
            || (Setting::get('always_use_ai') ?? config('laraledger.heuristics.always_use_ai', false));

        // Skip AI if heuristic confidence is sufficient
        if (! $alwaysUseAI && $heuristicResult['confidence'] >= $confidenceThreshold) {
            $this->logStage(AnalysisLog::STAGE_AI_CLASSIFICATION, microtime(true), [
                'skipped' => true,
                'reason' => 'Heuristic confidence sufficient',
                'heuristic_confidence' => $heuristicResult['confidence'],
                'threshold' => $confidenceThreshold,
            ], AnalysisLog::STATUS_SKIPPED);

            return null;
        }

        // Check if AI is configured
        if (! $this->isAIConfigured()) {
            $this->logStage(AnalysisLog::STAGE_AI_CLASSIFICATION, microtime(true), [
                'skipped' => true,
                'reason' => 'AI provider not configured',
            ], AnalysisLog::STATUS_SKIPPED);

            return null;
        }

        $stageStart = microtime(true);

        try {
            $result = $this->aiClassifier->classify($commits, $heuristicResult);

            $this->logStage(AnalysisLog::STAGE_AI_CLASSIFICATION, $stageStart, [
                'recommended_type' => $result['recommended_type'],
                'confidence' => $result['confidence'],
                'provider' => Setting::get('default_ai_provider') ?? config('laraledger.ai.classification.provider'),
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logStage(AnalysisLog::STAGE_AI_CLASSIFICATION, $stageStart, [
                'error' => $e->getMessage(),
            ], AnalysisLog::STATUS_FAILED);

            return null;
        }
    }

    /**
     * Resolve final recommendation from heuristic and AI results.
     */
    protected function resolveRecommendation(array $heuristicResult, ?array $aiResult): array
    {
        // If no AI result, use heuristic
        if ($aiResult === null) {
            return $heuristicResult;
        }

        // If either detects MAJOR, recommend MAJOR (be conservative)
        if ($heuristicResult['recommended_type'] === Release::TYPE_MAJOR
            || $aiResult['recommended_type'] === Release::TYPE_MAJOR) {
            $primary = $heuristicResult['recommended_type'] === Release::TYPE_MAJOR
                ? $heuristicResult
                : $aiResult;

            return array_merge($primary, [
                'source' => Release::SOURCE_COMBINED,
                'heuristic_result' => $heuristicResult,
                'ai_result' => $aiResult,
            ]);
        }

        // Use the result with higher confidence
        if ($aiResult['confidence'] > $heuristicResult['confidence']) {
            return array_merge($aiResult, [
                'source' => Release::SOURCE_AI,
                'heuristic_result' => $heuristicResult,
            ]);
        }

        return array_merge($heuristicResult, [
            'source' => Release::SOURCE_HEURISTIC,
            'ai_result' => $aiResult,
        ]);
    }

    /**
     * Calculate the recommended version number.
     */
    protected function calculateVersion(Repository $repository, string $fromRef, string $type): string
    {
        // Try to extract version from the from ref (usually a tag)
        $currentVersion = $this->parseVersion($fromRef);

        if (! $currentVersion) {
            // Try to get the latest tag from the repository
            $latestTag = $repository->getLatestTag();
            $currentVersion = $latestTag ? $this->parseVersion($latestTag) : ['major' => 0, 'minor' => 0, 'patch' => 0];
        }

        // Increment based on type
        return match ($type) {
            Release::TYPE_MAJOR => sprintf('v%d.0.0', $currentVersion['major'] + 1),
            Release::TYPE_MINOR => sprintf('v%d.%d.0', $currentVersion['major'], $currentVersion['minor'] + 1),
            default => sprintf('v%d.%d.%d', $currentVersion['major'], $currentVersion['minor'], $currentVersion['patch'] + 1),
        };
    }

    /**
     * Parse a version string into components.
     */
    protected function parseVersion(string $version): ?array
    {
        // Remove 'v' prefix if present
        $version = ltrim($version, 'vV');

        if (preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $matches)) {
            return [
                'major' => (int) $matches[1],
                'minor' => (int) $matches[2],
                'patch' => (int) $matches[3],
            ];
        }

        return null;
    }

    /**
     * Stage 4: Generate release notes.
     */
    protected function generateReleaseNotes(string $version, string $type, array $changes, string $style): string
    {
        $stageStart = microtime(true);

        // Check if AI is configured for generation
        if (! $this->isAIConfigured()) {
            $notes = $this->noteGenerator->generateFallback($version, $type, $changes);

            $this->logStage(AnalysisLog::STAGE_GENERATE_NOTES, $stageStart, [
                'method' => 'fallback',
                'reason' => 'AI provider not configured',
            ]);

            return $notes;
        }

        try {
            $notes = $this->noteGenerator
                ->setStyle($style)
                ->generate($version, $type, $changes, $style);

            $this->logStage(AnalysisLog::STAGE_GENERATE_NOTES, $stageStart, [
                'method' => 'ai',
                'style' => $style,
                'provider' => Setting::get('default_ai_provider') ?? config('laraledger.ai.generation.provider'),
            ]);

            return $notes;
        } catch (\Exception $e) {
            // Fall back to template generation
            $notes = $this->noteGenerator->generateFallback($version, $type, $changes);

            $this->logStage(AnalysisLog::STAGE_GENERATE_NOTES, $stageStart, [
                'method' => 'fallback',
                'error' => $e->getMessage(),
            ]);

            return $notes;
        }
    }

    /**
     * Stage 5: Finalize the release record.
     */
    protected function finalizeRelease(string $version, array $recommendation, string $notes, array $commits, int $duration): void
    {
        $stageStart = microtime(true);

        // Merge changes from heuristic and AI results
        $changes = $recommendation['changes'] ?? [];

        $this->release->update([
            'recommended_version' => $version,
            'recommended_type' => $recommendation['recommended_type'],
            'confidence' => $recommendation['confidence'],
            'release_notes' => $notes,
            'commits' => array_map(fn ($c) => [
                'sha' => $c['sha'] ?? '',
                'message' => $c['commit']['message'] ?? $c['message'] ?? '',
            ], array_slice($commits, 0, 100)), // Store first 100 commits
            'changes' => $changes,
            'reasoning' => $recommendation['reasoning'] ?? [],
            'duration_ms' => $duration,
            'status' => Release::STATUS_PENDING,
        ]);

        $this->logStage(AnalysisLog::STAGE_FINALIZE, $stageStart, [
            'version' => $version,
            'type' => $recommendation['recommended_type'],
            'confidence' => $recommendation['confidence'],
            'total_duration_ms' => $duration,
        ]);
    }

    /**
     * Log an analysis stage.
     */
    protected function logStage(string $stage, float $stageStart, array $response = [], string $status = AnalysisLog::STATUS_SUCCESS): void
    {
        if (! $this->release) {
            return;
        }

        $duration = (int) ((microtime(true) - $stageStart) * 1000);

        $this->release->analysisLogs()->create([
            'stage' => $stage,
            'status' => $status,
            'duration_ms' => $duration,
            'response' => $response,
        ]);

        $this->stages[] = [
            'stage' => $stage,
            'status' => $status,
            'duration_ms' => $duration,
        ];
    }

    /**
     * Check if AI provider is configured.
     */
    protected function isAIConfigured(): bool
    {
        return Setting::has('anthropic_api_key')
            || Setting::has('openai_api_key')
            || config('services.anthropic.api_key')
            || config('prism.providers.anthropic.api_key')
            || config('prism.providers.openai.api_key');
    }

    /**
     * Get the analysis stages for the current release.
     */
    public function getStages(): array
    {
        return $this->stages;
    }
}
