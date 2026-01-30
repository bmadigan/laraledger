<?php

namespace App\Http\Controllers;

use App\Models\Release;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    /**
     * Display the accuracy dashboard.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $repositoryIds = $user->repositories()->pluck('id');

        // Date range filter
        $range = $request->input('range', '30');
        $startDate = match ($range) {
            '7' => now()->subDays(7),
            '30' => now()->subDays(30),
            '90' => now()->subDays(90),
            '365' => now()->subYear(),
            'all' => null,
            default => now()->subDays(30),
        };

        // Repository filter
        $repositoryId = $request->input('repository_id');

        // Base query
        $baseQuery = Release::query()
            ->whereIn('repository_id', $repositoryIds)
            ->whereIn('status', [Release::STATUS_ACCEPTED, Release::STATUS_ADJUSTED, Release::STATUS_REJECTED]);

        if ($startDate) {
            $baseQuery->where('created_at', '>=', $startDate);
        }
        if ($repositoryId) {
            $baseQuery->where('repository_id', $repositoryId);
        }

        // Calculate metrics
        $totalReleases = (clone $baseQuery)->count();
        $acceptedCount = (clone $baseQuery)->where('status', Release::STATUS_ACCEPTED)->count();
        $adjustedCount = (clone $baseQuery)->where('status', Release::STATUS_ADJUSTED)->count();
        $rejectedCount = (clone $baseQuery)->where('status', Release::STATUS_REJECTED)->count();

        // Acceptance rate
        $acceptanceRate = $totalReleases > 0 ? round(($acceptedCount / $totalReleases) * 100, 1) : 0;

        // Breaking change detection rate
        $actualMajorReleases = (clone $baseQuery)
            ->where(function ($q) {
                $q->where('final_type', Release::TYPE_MAJOR)
                    ->orWhere(function ($q2) {
                        $q2->whereNull('final_type')
                            ->where('recommended_type', Release::TYPE_MAJOR);
                    });
            })
            ->count();

        $predictedMajorReleases = (clone $baseQuery)
            ->where('recommended_type', Release::TYPE_MAJOR)
            ->where(function ($q) {
                $q->where('final_type', Release::TYPE_MAJOR)
                    ->orWhere('status', Release::STATUS_ACCEPTED);
            })
            ->count();

        $breakingDetectionRate = $actualMajorReleases > 0
            ? round(($predictedMajorReleases / $actualMajorReleases) * 100, 1)
            : 100;

        // Average confidence
        $avgConfidence = round((clone $baseQuery)->avg('confidence') ?? 0);

        // Total cost
        $totalCost = (clone $baseQuery)->sum('cost_usd') ?? 0;
        $avgCostPerRelease = $totalReleases > 0 ? round($totalCost / $totalReleases, 4) : 0;

        // Acceptance rate over time (for chart)
        $acceptanceOverTime = $this->getAcceptanceOverTime($baseQuery, $startDate, $range);

        // Confidence calibration (expected vs actual accuracy by bucket)
        $confidenceCalibration = $this->getConfidenceCalibration($baseQuery);

        // Version type distribution
        $versionDistribution = $this->getVersionDistribution($baseQuery);

        // AI vs Heuristic usage
        $analysisSourceUsage = $this->getAnalysisSourceUsage($baseQuery);

        // Cost over time
        $costOverTime = $this->getCostOverTime($baseQuery, $startDate, $range);

        // Latency metrics
        $latencyMetrics = $this->getLatencyMetrics($baseQuery);

        // Get repositories for filter
        $repositories = $user->repositories()
            ->select('id', 'name')
            ->withCount('releases')
            ->having('releases_count', '>', 0)
            ->get();

        return Inertia::render('Analytics/Dashboard', [
            'metrics' => [
                'totalReleases' => $totalReleases,
                'acceptanceRate' => $acceptanceRate,
                'breakingDetectionRate' => $breakingDetectionRate,
                'avgConfidence' => $avgConfidence,
                'totalCost' => round($totalCost, 2),
                'avgCostPerRelease' => $avgCostPerRelease,
                'accepted' => $acceptedCount,
                'adjusted' => $adjustedCount,
                'rejected' => $rejectedCount,
            ],
            'charts' => [
                'acceptanceOverTime' => $acceptanceOverTime,
                'confidenceCalibration' => $confidenceCalibration,
                'versionDistribution' => $versionDistribution,
                'analysisSourceUsage' => $analysisSourceUsage,
                'costOverTime' => $costOverTime,
            ],
            'latencyMetrics' => $latencyMetrics,
            'repositories' => $repositories,
            'filters' => [
                'range' => $range,
                'repository_id' => $repositoryId,
            ],
        ]);
    }

    /**
     * Get acceptance rate over time for chart.
     *
     * @return array<int, array{date: string, rate: float, count: int}>
     */
    private function getAcceptanceOverTime($baseQuery, ?Carbon $startDate, string $range): array
    {
        $groupBy = in_array($range, ['7', '30']) ? 'day' : 'week';
        $format = $groupBy === 'day' ? '%Y-%m-%d' : '%Y-%u';
        $displayFormat = $groupBy === 'day' ? 'M d' : 'M d';

        $data = (clone $baseQuery)
            ->select(
                DB::raw("strftime('{$format}', created_at) as period"),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted")
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $data->map(function ($item) {
            return [
                'date' => $item->period,
                'rate' => $item->total > 0 ? round(($item->accepted / $item->total) * 100, 1) : 0,
                'count' => $item->total,
            ];
        })->values()->all();
    }

    /**
     * Get confidence calibration data.
     *
     * @return array<int, array{bucket: string, expected: int, actual: float}>
     */
    private function getConfidenceCalibration($baseQuery): array
    {
        $buckets = [
            ['min' => 50, 'max' => 60, 'label' => '50-60%', 'expected' => 55],
            ['min' => 60, 'max' => 70, 'label' => '60-70%', 'expected' => 65],
            ['min' => 70, 'max' => 80, 'label' => '70-80%', 'expected' => 75],
            ['min' => 80, 'max' => 90, 'label' => '80-90%', 'expected' => 85],
            ['min' => 90, 'max' => 100, 'label' => '90-100%', 'expected' => 95],
        ];

        $result = [];

        foreach ($buckets as $bucket) {
            $bucketQuery = (clone $baseQuery)
                ->where('confidence', '>=', $bucket['min'])
                ->where('confidence', '<', $bucket['max'] === 100 ? 101 : $bucket['max']);

            $total = $bucketQuery->count();
            $correct = (clone $bucketQuery)
                ->where(function ($q) {
                    $q->where('status', Release::STATUS_ACCEPTED)
                        ->orWhere(function ($q2) {
                            $q2->where('status', Release::STATUS_ADJUSTED)
                                ->whereColumn('recommended_type', 'final_type');
                        });
                })
                ->count();

            $result[] = [
                'bucket' => $bucket['label'],
                'expected' => $bucket['expected'],
                'actual' => $total > 0 ? round(($correct / $total) * 100, 1) : 0,
                'count' => $total,
            ];
        }

        return $result;
    }

    /**
     * Get version type distribution.
     *
     * @return array<int, array{type: string, count: int, percentage: float}>
     */
    private function getVersionDistribution($baseQuery): array
    {
        $total = (clone $baseQuery)->count();

        $types = [Release::TYPE_MAJOR, Release::TYPE_MINOR, Release::TYPE_PATCH];
        $result = [];

        foreach ($types as $type) {
            $count = (clone $baseQuery)->where('recommended_type', $type)->count();
            $result[] = [
                'type' => $type,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        }

        return $result;
    }

    /**
     * Get AI vs Heuristic usage.
     *
     * @return array{heuristic: int, ai: int, combined: int}
     */
    private function getAnalysisSourceUsage($baseQuery): array
    {
        return [
            'heuristic' => (clone $baseQuery)->where('analysis_source', Release::SOURCE_HEURISTIC)->count(),
            'ai' => (clone $baseQuery)->where('analysis_source', Release::SOURCE_AI)->count(),
            'combined' => (clone $baseQuery)->where('analysis_source', Release::SOURCE_COMBINED)->count(),
        ];
    }

    /**
     * Get cost over time.
     *
     * @return array<int, array{date: string, cost: float}>
     */
    private function getCostOverTime($baseQuery, ?Carbon $startDate, string $range): array
    {
        $groupBy = in_array($range, ['7', '30']) ? 'day' : 'week';
        $format = $groupBy === 'day' ? '%Y-%m-%d' : '%Y-%u';

        $data = (clone $baseQuery)
            ->select(
                DB::raw("strftime('{$format}', created_at) as period"),
                DB::raw('SUM(COALESCE(cost_usd, 0)) as total_cost'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $data->map(function ($item) {
            return [
                'date' => $item->period,
                'cost' => round($item->total_cost, 4),
                'count' => $item->count,
            ];
        })->values()->all();
    }

    /**
     * Get latency metrics.
     *
     * @return array{avg: float, p95: float, min: float, max: float}
     */
    private function getLatencyMetrics($baseQuery): array
    {
        $durations = (clone $baseQuery)
            ->whereNotNull('duration_ms')
            ->pluck('duration_ms')
            ->sort()
            ->values();

        if ($durations->isEmpty()) {
            return ['avg' => 0, 'p95' => 0, 'min' => 0, 'max' => 0];
        }

        $count = $durations->count();
        $p95Index = (int) floor($count * 0.95);

        return [
            'avg' => round($durations->avg() / 1000, 2),
            'p95' => round(($durations[$p95Index] ?? $durations->last()) / 1000, 2),
            'min' => round($durations->min() / 1000, 2),
            'max' => round($durations->max() / 1000, 2),
        ];
    }

    /**
     * Display missed predictions for review.
     */
    public function missedPredictions(Request $request): Response
    {
        $user = Auth::user();
        $repositoryIds = $user->repositories()->pluck('id');

        $query = Release::query()
            ->whereIn('repository_id', $repositoryIds)
            ->where(function ($q) {
                // Adjusted releases
                $q->where('status', Release::STATUS_ADJUSTED)
                    // Rejected releases
                    ->orWhere('status', Release::STATUS_REJECTED);
            })
            ->with(['repository:id,name', 'feedback']);

        // Filter by repository
        if ($repositoryId = $request->input('repository_id')) {
            $query->where('repository_id', $repositoryId);
        }

        // Filter by date range
        if ($from = $request->input('from_date')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to_date')) {
            $query->whereDate('created_at', '<=', $to);
        }

        // Sort by confidence desc to show high-confidence misses first
        $query->orderBy('confidence', 'desc')->orderBy('created_at', 'desc');

        $predictions = $query->paginate(20)->withQueryString();

        // Aggregate reason categories
        $reasonCategories = Release::query()
            ->whereIn('repository_id', $repositoryIds)
            ->where('status', Release::STATUS_ADJUSTED)
            ->join('feedback', 'releases.id', '=', 'feedback.release_id')
            ->select('feedback.reason_category', DB::raw('COUNT(*) as count'))
            ->groupBy('feedback.reason_category')
            ->orderBy('count', 'desc')
            ->get();

        // Get repositories for filter
        $repositories = $user->repositories()
            ->select('id', 'name')
            ->withCount('releases')
            ->having('releases_count', '>', 0)
            ->get();

        return Inertia::render('Analytics/MissedPredictions', [
            'predictions' => $predictions,
            'reasonCategories' => $reasonCategories,
            'repositories' => $repositories,
            'filters' => [
                'repository_id' => $request->input('repository_id'),
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
            ],
        ]);
    }
}
