<?php

namespace App\Http\Controllers;

use App\Models\Release;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReleaseController extends Controller
{
    /**
     * Display a listing of all releases across repositories.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $query = Release::query()
            ->whereIn('repository_id', $user->repositories()->pluck('id'))
            ->with('repository:id,name,full_name');

        // Search by repository name or version
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('recommended_version', 'like', "%{$search}%")
                    ->orWhere('final_version', 'like', "%{$search}%")
                    ->orWhereHas('repository', function ($rq) use ($search) {
                        $rq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by repository
        if ($repositoryId = $request->input('repository_id')) {
            $query->where('repository_id', $repositoryId);
        }

        // Filter by version type
        if ($types = $request->input('types')) {
            $typeArray = is_array($types) ? $types : explode(',', $types);
            $query->whereIn('recommended_type', $typeArray);
        }

        // Filter by status
        if ($statuses = $request->input('statuses')) {
            $statusArray = is_array($statuses) ? $statuses : explode(',', $statuses);
            $query->whereIn('status', $statusArray);
        }

        // Filter by date range
        if ($from = $request->input('from_date')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to_date')) {
            $query->whereDate('created_at', '<=', $to);
        }

        // Filter by confidence range
        if ($minConfidence = $request->input('min_confidence')) {
            $query->where('confidence', '>=', (int) $minConfidence);
        }
        if ($maxConfidence = $request->input('max_confidence')) {
            $query->where('confidence', '<=', (int) $maxConfidence);
        }

        // Sorting
        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $allowedSorts = ['created_at', 'recommended_version', 'confidence', 'status'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        // Calculate stats for current filter
        $statsQuery = (clone $query)->toBase();
        $stats = [
            'total' => $statsQuery->count(),
            'accepted' => (clone $query)->where('status', Release::STATUS_ACCEPTED)->count(),
            'adjusted' => (clone $query)->where('status', Release::STATUS_ADJUSTED)->count(),
            'rejected' => (clone $query)->where('status', Release::STATUS_REJECTED)->count(),
            'avg_confidence' => round((clone $query)->avg('confidence') ?? 0),
        ];

        // Paginate results
        $releases = $query->paginate(25)->withQueryString();

        // Get repositories for filter dropdown
        $repositories = $user->repositories()
            ->select('id', 'name')
            ->withCount('releases')
            ->having('releases_count', '>', 0)
            ->get();

        return Inertia::render('Releases/Index', [
            'releases' => $releases,
            'repositories' => $repositories,
            'stats' => $stats,
            'filters' => [
                'search' => $request->input('search'),
                'repository_id' => $request->input('repository_id'),
                'types' => $request->input('types'),
                'statuses' => $request->input('statuses'),
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'min_confidence' => $request->input('min_confidence'),
                'max_confidence' => $request->input('max_confidence'),
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
        ]);
    }

    /**
     * Export releases as CSV or JSON.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = Auth::user();
        $format = $request->input('format', 'csv');

        $query = Release::query()
            ->whereIn('repository_id', $user->repositories()->pluck('id'))
            ->with('repository:id,name,full_name');

        // Apply same filters as index
        if ($repositoryId = $request->input('repository_id')) {
            $query->where('repository_id', $repositoryId);
        }
        if ($types = $request->input('types')) {
            $typeArray = is_array($types) ? $types : explode(',', $types);
            $query->whereIn('recommended_type', $typeArray);
        }
        if ($statuses = $request->input('statuses')) {
            $statusArray = is_array($statuses) ? $statuses : explode(',', $statuses);
            $query->whereIn('status', $statusArray);
        }
        if ($from = $request->input('from_date')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to_date')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $releases = $query->orderBy('created_at', 'desc')->get();

        $filename = 'laraledger-releases-'.now()->format('Y-m-d');

        if ($format === 'json') {
            return response()->streamDownload(function () use ($releases) {
                echo $releases->toJson(JSON_PRETTY_PRINT);
            }, $filename.'.json', [
                'Content-Type' => 'application/json',
            ]);
        }

        // CSV format
        return response()->streamDownload(function () use ($releases) {
            $handle = fopen('php://output', 'w');

            // Header row
            fputcsv($handle, [
                'Date',
                'Repository',
                'From Ref',
                'To Ref',
                'Recommended Version',
                'Recommended Type',
                'Final Version',
                'Final Type',
                'Confidence',
                'Status',
                'Cost (USD)',
                'Duration (ms)',
            ]);

            // Data rows
            foreach ($releases as $release) {
                fputcsv($handle, [
                    $release->created_at->format('Y-m-d H:i:s'),
                    $release->repository->name ?? '',
                    $release->from_ref,
                    $release->to_ref,
                    $release->recommended_version,
                    $release->recommended_type,
                    $release->final_version ?? '',
                    $release->final_type ?? '',
                    $release->confidence,
                    $release->status,
                    $release->cost_usd ?? '0',
                    $release->duration_ms ?? '0',
                ]);
            }

            fclose($handle);
        }, $filename.'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
