<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user = Auth::user();

        // System status
        $systemStatus = [
            'github' => [
                'connected' => $user->isGithubConnected(),
                'username' => $user->github_username,
            ],
            'ai_provider' => [
                'configured' => Setting::has('anthropic_api_key') || Setting::has('openai_api_key'),
                'provider' => $this->getConfiguredProvider(),
            ],
            'database' => [
                'status' => 'healthy',
            ],
        ];

        // Repository quick list (top 5 by recent activity)
        $repositories = $user->repositories()
            ->withCount('releases')
            ->orderBy('last_synced_at', 'desc')
            ->limit(5)
            ->get();

        // Recent releases
        $recentReleases = Release::whereIn('repository_id', $user->repositories()->pluck('id'))
            ->with('repository:id,name,full_name')
            ->latest()
            ->limit(10)
            ->get();

        // Accuracy summary (last 30 days)
        $thirtyDaysAgo = now()->subDays(30);
        $recentReleasesForStats = Release::whereIn('repository_id', $user->repositories()->pluck('id'))
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->whereIn('status', [Release::STATUS_ACCEPTED, Release::STATUS_ADJUSTED, Release::STATUS_REJECTED])
            ->get();

        $totalReleases = $recentReleasesForStats->count();
        $acceptedReleases = $recentReleasesForStats->where('status', Release::STATUS_ACCEPTED)->count();
        $acceptanceRate = $totalReleases > 0 ? round(($acceptedReleases / $totalReleases) * 100) : null;

        // Breaking change detection rate
        $majorReleases = $recentReleasesForStats->where('final_type', Release::TYPE_MAJOR);
        $correctlyPredictedMajor = $majorReleases->where('recommended_type', Release::TYPE_MAJOR)->count();
        $breakingDetectionRate = $majorReleases->count() > 0
            ? round(($correctlyPredictedMajor / $majorReleases->count()) * 100)
            : null;

        $accuracySummary = [
            'acceptance_rate' => $acceptanceRate,
            'breaking_detection_rate' => $breakingDetectionRate,
            'total_releases' => $totalReleases,
            'has_sufficient_data' => $totalReleases >= 5,
        ];

        return Inertia::render('Dashboard', [
            'systemStatus' => $systemStatus,
            'repositories' => $repositories,
            'recentReleases' => $recentReleases,
            'accuracySummary' => $accuracySummary,
        ]);
    }

    private function getConfiguredProvider(): ?string
    {
        if (Setting::has('anthropic_api_key') || config('services.anthropic.api_key')) {
            return 'Anthropic';
        }

        if (Setting::has('openai_api_key') || config('services.openai.api_key')) {
            return 'OpenAI';
        }

        return null;
    }
}
