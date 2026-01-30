<?php

namespace Database\Seeders;

use App\Models\AnalysisLog;
use App\Models\Feedback;
use App\Models\Release;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed demo data for the authenticated user.
     */
    public function run(): void
    {
        $user = Auth::user();

        if (! $user) {
            $this->command?->error('No authenticated user. Please run this seeder through the application.');

            return;
        }

        $this->createDemoRepositories($user);
        $this->command?->info('Demo data created successfully!');
    }

    /**
     * Create demo repositories with releases.
     */
    private function createDemoRepositories(User $user): void
    {
        $demoRepos = [
            [
                'github_id' => 900000001,
                'name' => 'web-app',
                'full_name' => 'acme/web-app',
                'description' => 'Main web application for ACME Corp',
                'default_branch' => 'main',
                'is_private' => true,
            ],
            [
                'github_id' => 900000002,
                'name' => 'api-gateway',
                'full_name' => 'acme/api-gateway',
                'description' => 'API Gateway service for microservices architecture',
                'default_branch' => 'main',
                'is_private' => false,
            ],
            [
                'github_id' => 900000003,
                'name' => 'payment-sdk',
                'full_name' => 'acme/payment-sdk',
                'description' => 'Payment processing SDK for e-commerce',
                'default_branch' => 'develop',
                'is_private' => true,
            ],
        ];

        foreach ($demoRepos as $repoData) {
            $repository = $user->repositories()->updateOrCreate(
                ['github_id' => $repoData['github_id']],
                [
                    ...$repoData,
                    'last_synced_at' => now(),
                ]
            );

            $this->createReleasesForRepository($repository);
        }
    }

    /**
     * Create realistic releases for a repository.
     */
    private function createReleasesForRepository(Repository $repository): void
    {
        // Clear existing releases for this demo repo
        $repository->releases()->delete();

        $releaseTemplates = $this->getReleaseTemplates();

        foreach ($releaseTemplates as $index => $template) {
            $release = Release::create([
                'repository_id' => $repository->id,
                'from_ref' => $template['from_ref'],
                'to_ref' => $template['to_ref'],
                'recommended_version' => $template['version'],
                'recommended_type' => $template['type'],
                'final_version' => $template['final_version'] ?? null,
                'final_type' => $template['final_type'] ?? null,
                'confidence' => $template['confidence'],
                'status' => $template['status'],
                'release_notes' => $template['release_notes'],
                'commits' => $template['commits'],
                'changes' => $template['changes'],
                'heuristic_reasoning' => $template['heuristic_reasoning'],
                'ai_reasoning' => $template['ai_reasoning'] ?? null,
                'analysis_source' => $template['analysis_source'],
                'cost_usd' => $template['cost_usd'],
                'duration_ms' => $template['duration_ms'],
                'created_at' => now()->subDays(count($releaseTemplates) - $index),
            ]);

            $this->createAnalysisLogs($release, $template['analysis_source']);

            // Only create feedback for adjusted releases (which have final_version set)
            if ($template['status'] === Release::STATUS_ADJUSTED && isset($template['feedback_reason'])) {
                $this->createFeedback($release, $template);
            }
        }
    }

    /**
     * Get release templates with realistic data.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getReleaseTemplates(): array
    {
        return [
            // MAJOR release - accepted
            [
                'from_ref' => 'v1.5.0',
                'to_ref' => 'main',
                'version' => '2.0.0',
                'type' => Release::TYPE_MAJOR,
                'status' => Release::STATUS_ACCEPTED,
                'confidence' => 95,
                'release_notes' => "## Breaking Changes\n\n- Removed deprecated `legacyAuth()` method\n- Changed `UserRepository` interface signature\n- Minimum PHP version is now 8.2\n\n## New Features\n\n- Added OAuth 2.0 support\n- New admin dashboard\n\n## Bug Fixes\n\n- Fixed session timeout issues",
                'commits' => $this->generateCommits(['feat!: remove legacy authentication system', 'feat: add OAuth 2.0 support', 'feat: new admin dashboard', 'fix: session timeout handling', 'chore: upgrade to PHP 8.2']),
                'changes' => ['breaking' => ['Removed legacyAuth() method', 'Changed UserRepository interface'], 'features' => ['OAuth 2.0 support', 'Admin dashboard'], 'fixes' => ['Session timeout']],
                'heuristic_reasoning' => ['Found breaking change indicator: removed public method', 'Commit message contains "feat!:" prefix'],
                'analysis_source' => Release::SOURCE_HEURISTIC,
                'cost_usd' => 0,
                'duration_ms' => 1250,
            ],
            // MINOR release - accepted
            [
                'from_ref' => 'v2.0.0',
                'to_ref' => 'main',
                'version' => '2.1.0',
                'type' => Release::TYPE_MINOR,
                'status' => Release::STATUS_ACCEPTED,
                'confidence' => 88,
                'release_notes' => "## New Features\n\n- Added dark mode support\n- New notification system\n- Export to PDF functionality\n\n## Bug Fixes\n\n- Fixed timezone issues in reports",
                'commits' => $this->generateCommits(['feat: add dark mode toggle', 'feat: notification system', 'feat: PDF export', 'fix: timezone in reports']),
                'changes' => ['breaking' => [], 'features' => ['Dark mode', 'Notifications', 'PDF export'], 'fixes' => ['Timezone in reports']],
                'heuristic_reasoning' => ['Multiple feat commits detected', 'No breaking change indicators'],
                'analysis_source' => Release::SOURCE_HEURISTIC,
                'cost_usd' => 0,
                'duration_ms' => 980,
            ],
            // PATCH release - accepted
            [
                'from_ref' => 'v2.1.0',
                'to_ref' => 'main',
                'version' => '2.1.1',
                'type' => Release::TYPE_PATCH,
                'status' => Release::STATUS_ACCEPTED,
                'confidence' => 92,
                'release_notes' => "## Bug Fixes\n\n- Fixed critical security vulnerability in file uploads\n- Resolved memory leak in queue workers\n- Fixed pagination on mobile devices",
                'commits' => $this->generateCommits(['fix: security vulnerability in uploads', 'fix: memory leak in workers', 'fix: mobile pagination']),
                'changes' => ['breaking' => [], 'features' => [], 'fixes' => ['Security vulnerability', 'Memory leak', 'Mobile pagination']],
                'heuristic_reasoning' => ['All commits are fix type', 'No features or breaking changes'],
                'analysis_source' => Release::SOURCE_HEURISTIC,
                'cost_usd' => 0,
                'duration_ms' => 750,
            ],
            // MINOR release - adjusted to MAJOR (breaking change missed)
            [
                'from_ref' => 'v2.1.1',
                'to_ref' => 'main',
                'version' => '2.2.0',
                'type' => Release::TYPE_MINOR,
                'final_version' => '3.0.0',
                'final_type' => Release::TYPE_MAJOR,
                'status' => Release::STATUS_ADJUSTED,
                'confidence' => 72,
                'release_notes' => "## New Features\n\n- Complete API redesign\n- GraphQL support\n\n## Changes\n\n- Updated response format",
                'commits' => $this->generateCommits(['feat: API redesign', 'feat: GraphQL support', 'refactor: response format']),
                'changes' => ['breaking' => [], 'features' => ['API redesign', 'GraphQL'], 'fixes' => []],
                'heuristic_reasoning' => ['Feature commits detected', 'Refactor may contain breaking changes - confidence reduced'],
                'ai_reasoning' => ['API redesign typically involves breaking changes', 'Response format change affects existing integrations'],
                'analysis_source' => Release::SOURCE_COMBINED,
                'cost_usd' => 0.0023,
                'duration_ms' => 3420,
                'feedback_reason' => Feedback::REASON_BREAKING_MISSED,
                'feedback_details' => 'The API redesign changed the response structure which broke existing integrations.',
            ],
            // PATCH release - accepted with AI
            [
                'from_ref' => 'v3.0.0',
                'to_ref' => 'main',
                'version' => '3.0.1',
                'type' => Release::TYPE_PATCH,
                'status' => Release::STATUS_ACCEPTED,
                'confidence' => 78,
                'release_notes' => "## Bug Fixes\n\n- Fixed edge case in GraphQL resolver\n- Corrected type definitions",
                'commits' => $this->generateCommits(['fix: GraphQL resolver edge case', 'fix: type definitions']),
                'changes' => ['breaking' => [], 'features' => [], 'fixes' => ['GraphQL resolver', 'Type definitions']],
                'heuristic_reasoning' => ['Fix commits only', 'Low confidence due to recent major changes'],
                'ai_reasoning' => ['Changes are isolated bug fixes', 'No API surface changes detected'],
                'analysis_source' => Release::SOURCE_AI,
                'cost_usd' => 0.0018,
                'duration_ms' => 2890,
            ],
            // MINOR release - rejected (no feedback for rejected releases without versions)
            [
                'from_ref' => 'v3.0.1',
                'to_ref' => 'main',
                'version' => '3.1.0',
                'type' => Release::TYPE_MINOR,
                'status' => Release::STATUS_REJECTED,
                'confidence' => 65,
                'release_notes' => "## Features\n\n- Added caching layer\n\n## Fixes\n\n- Various bug fixes",
                'commits' => $this->generateCommits(['feat: caching layer', 'fix: various bugs', 'chore: dependencies']),
                'changes' => ['breaking' => [], 'features' => ['Caching'], 'fixes' => ['Various']],
                'heuristic_reasoning' => ['Feature commit detected', 'Generic commit messages reduce confidence'],
                'analysis_source' => Release::SOURCE_HEURISTIC,
                'cost_usd' => 0,
                'duration_ms' => 1100,
                // No feedback for rejected releases - they were simply not used
            ],
            // PATCH release - accepted
            [
                'from_ref' => 'v3.0.1',
                'to_ref' => 'main',
                'version' => '3.0.2',
                'type' => Release::TYPE_PATCH,
                'status' => Release::STATUS_ACCEPTED,
                'confidence' => 94,
                'release_notes' => "## Bug Fixes\n\n- Fixed rate limiting bypass\n- Improved error messages",
                'commits' => $this->generateCommits(['fix: rate limiting bypass', 'fix: improve error messages']),
                'changes' => ['breaking' => [], 'features' => [], 'fixes' => ['Rate limiting', 'Error messages']],
                'heuristic_reasoning' => ['All fix commits', 'High confidence pattern'],
                'analysis_source' => Release::SOURCE_HEURISTIC,
                'cost_usd' => 0,
                'duration_ms' => 820,
            ],
            // MINOR release - adjusted (feature miscategorized)
            [
                'from_ref' => 'v3.0.2',
                'to_ref' => 'main',
                'version' => '3.0.3',
                'type' => Release::TYPE_PATCH,
                'final_version' => '3.1.0',
                'final_type' => Release::TYPE_MINOR,
                'status' => Release::STATUS_ADJUSTED,
                'confidence' => 68,
                'release_notes' => "## Changes\n\n- Improved search functionality\n- Better filtering options",
                'commits' => $this->generateCommits(['improve: search functionality', 'improve: filtering options']),
                'changes' => ['breaking' => [], 'features' => [], 'fixes' => ['Search', 'Filtering']],
                'heuristic_reasoning' => ['Improve prefix often indicates fixes', 'No explicit feat commits'],
                'ai_reasoning' => ['Search improvements add new capabilities', 'Could be considered new features'],
                'analysis_source' => Release::SOURCE_COMBINED,
                'cost_usd' => 0.0021,
                'duration_ms' => 2650,
                'feedback_reason' => Feedback::REASON_FIX_MISCATEGORIZED,
                'feedback_details' => 'The search improvements added new filter types which are new features, not fixes.',
            ],
            // MAJOR release - accepted with high confidence
            [
                'from_ref' => 'v3.1.0',
                'to_ref' => 'main',
                'version' => '4.0.0',
                'type' => Release::TYPE_MAJOR,
                'status' => Release::STATUS_ACCEPTED,
                'confidence' => 98,
                'release_notes' => "## Breaking Changes\n\n- Dropped PHP 8.1 support\n- Removed deprecated endpoints\n- New authentication flow required\n\n## Features\n\n- Multi-tenancy support\n- Real-time collaboration\n\n## Improvements\n\n- 50% faster response times",
                'commits' => $this->generateCommits(['feat!: multi-tenancy support', 'feat!: new auth flow', 'feat: real-time collaboration', 'perf: optimize queries', 'chore: drop PHP 8.1']),
                'changes' => ['breaking' => ['PHP 8.1 dropped', 'New auth flow', 'Removed endpoints'], 'features' => ['Multi-tenancy', 'Real-time'], 'fixes' => []],
                'heuristic_reasoning' => ['Multiple feat! commits', 'Clear breaking change indicators', 'PHP version drop is breaking'],
                'analysis_source' => Release::SOURCE_HEURISTIC,
                'cost_usd' => 0,
                'duration_ms' => 1450,
            ],
            // PATCH - pending (most recent)
            [
                'from_ref' => 'v4.0.0',
                'to_ref' => 'main',
                'version' => '4.0.1',
                'type' => Release::TYPE_PATCH,
                'status' => Release::STATUS_PENDING,
                'confidence' => 85,
                'release_notes' => "## Bug Fixes\n\n- Fixed tenant isolation issue\n- Corrected WebSocket reconnection logic",
                'commits' => $this->generateCommits(['fix: tenant isolation', 'fix: websocket reconnection']),
                'changes' => ['breaking' => [], 'features' => [], 'fixes' => ['Tenant isolation', 'WebSocket reconnection']],
                'heuristic_reasoning' => ['Fix commits only', 'No breaking or feature indicators'],
                'analysis_source' => Release::SOURCE_HEURISTIC,
                'cost_usd' => 0,
                'duration_ms' => 920,
            ],
        ];
    }

    /**
     * Generate commit data from messages.
     *
     * @param  array<int, string>  $messages
     * @return array<int, array{sha: string, message: string, author: string, date: string}>
     */
    private function generateCommits(array $messages): array
    {
        $authors = ['John Smith', 'Jane Doe', 'Alex Johnson', 'Sam Wilson'];

        return array_map(function ($message, $index) use ($authors) {
            return [
                'sha' => substr(md5($message.$index), 0, 7),
                'message' => $message,
                'author' => $authors[$index % count($authors)],
                'date' => now()->subDays(rand(1, 30))->toIso8601String(),
            ];
        }, $messages, array_keys($messages));
    }

    /**
     * Create analysis logs for a release.
     */
    private function createAnalysisLogs(Release $release, string $source): void
    {
        // Fetch commits stage
        AnalysisLog::create([
            'release_id' => $release->id,
            'stage' => AnalysisLog::STAGE_FETCH_COMMITS,
            'duration_ms' => rand(200, 800),
            'status' => AnalysisLog::STATUS_SUCCESS,
        ]);

        // Heuristic analysis stage
        AnalysisLog::create([
            'release_id' => $release->id,
            'stage' => AnalysisLog::STAGE_HEURISTIC_ANALYSIS,
            'duration_ms' => rand(100, 500),
            'status' => AnalysisLog::STATUS_SUCCESS,
        ]);

        // AI classification stage (if used)
        if (in_array($source, [Release::SOURCE_AI, Release::SOURCE_COMBINED])) {
            AnalysisLog::create([
                'release_id' => $release->id,
                'stage' => AnalysisLog::STAGE_AI_CLASSIFICATION,
                'duration_ms' => rand(1500, 3000),
                'tokens_used' => rand(500, 2000),
                'model' => 'claude-3-5-haiku-20241022',
                'status' => AnalysisLog::STATUS_SUCCESS,
            ]);
        } else {
            AnalysisLog::create([
                'release_id' => $release->id,
                'stage' => AnalysisLog::STAGE_AI_CLASSIFICATION,
                'duration_ms' => 0,
                'status' => AnalysisLog::STATUS_SKIPPED,
            ]);
        }

        // Note generation stage
        AnalysisLog::create([
            'release_id' => $release->id,
            'stage' => AnalysisLog::STAGE_NOTE_GENERATION,
            'duration_ms' => rand(800, 2000),
            'tokens_used' => rand(300, 1000),
            'model' => 'claude-3-5-haiku-20241022',
            'status' => AnalysisLog::STATUS_SUCCESS,
        ]);

        // Finalize stage
        AnalysisLog::create([
            'release_id' => $release->id,
            'stage' => AnalysisLog::STAGE_FINALIZE,
            'duration_ms' => rand(50, 150),
            'status' => AnalysisLog::STATUS_SUCCESS,
        ]);
    }

    /**
     * Create feedback for adjusted/rejected releases.
     *
     * @param  array<string, mixed>  $template
     */
    private function createFeedback(Release $release, array $template): void
    {
        Feedback::create([
            'release_id' => $release->id,
            'reason_category' => $template['feedback_reason'] ?? Feedback::REASON_OTHER,
            'reason_details' => $template['feedback_details'] ?? 'No details provided.',
            'original_version' => $release->recommended_version,
            'original_type' => $release->recommended_type,
            'user_version' => $release->final_version,
            'user_type' => $release->final_type,
        ]);
    }
}
