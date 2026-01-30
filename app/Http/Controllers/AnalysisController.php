<?php

namespace App\Http\Controllers;

use App\Models\Release;
use App\Models\Repository;
use App\Services\Analysis\AnalysisService;
use App\Services\GitHub\GitHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AnalysisController extends Controller
{
    /**
     * Show the new analysis form.
     */
    public function create(Repository $repository): Response
    {
        $this->authorize('view', $repository);

        $github = new GitHubService(Auth::user());

        // Fetch tags and branches for the selectors
        $tags = $github->getTags($repository->full_name);
        $branches = $github->getBranches($repository->full_name);

        // Get recent releases for the sidebar
        $recentReleases = $repository->releases()
            ->latest()
            ->limit(5)
            ->get(['id', 'from_ref', 'to_ref', 'recommended_version', 'recommended_type', 'created_at']);

        return Inertia::render('Analysis/Create', [
            'repository' => $repository,
            'tags' => $tags,
            'branches' => $branches,
            'recentReleases' => $recentReleases,
            'defaultBranch' => $repository->default_branch,
        ]);
    }

    /**
     * Preview the commits that will be analyzed.
     */
    public function preview(Request $request, Repository $repository): JsonResponse
    {
        $this->authorize('view', $repository);

        $validated = $request->validate([
            'from_ref' => ['required', 'string'],
            'to_ref' => ['required', 'string'],
        ]);

        $github = new GitHubService(Auth::user());

        try {
            $commits = $github->getCommitsBetween(
                $repository->full_name,
                $validated['from_ref'],
                $validated['to_ref']
            );

            // Get unique contributors
            $contributors = collect($commits)
                ->pluck('author.login')
                ->filter()
                ->unique()
                ->values()
                ->all();

            // Count files changed (approximation from commits)
            $filesChanged = collect($commits)
                ->pluck('files')
                ->flatten(1)
                ->filter()
                ->count();

            return response()->json([
                'success' => true,
                'commit_count' => count($commits),
                'files_changed' => $filesChanged,
                'contributors' => $contributors,
                'commits' => array_map(fn ($c) => [
                    'sha' => substr($c['sha'] ?? '', 0, 7),
                    'message' => strtok($c['commit']['message'] ?? '', "\n"),
                    'author' => $c['commit']['author']['name'] ?? 'Unknown',
                    'date' => $c['commit']['author']['date'] ?? null,
                ], array_slice($commits, 0, 20)), // Preview first 20
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Start a new analysis.
     */
    public function store(Request $request, Repository $repository, AnalysisService $analysisService): RedirectResponse
    {
        $this->authorize('update', $repository);

        $validated = $request->validate([
            'from_ref' => ['required', 'string'],
            'to_ref' => ['required', 'string'],
            'force_ai' => ['nullable', 'boolean'],
            'depth' => ['nullable', 'in:standard,deep'],
            'note_style' => ['nullable', 'in:technical,user_friendly,marketing'],
        ]);

        $options = [
            'force_ai' => $validated['force_ai'] ?? false,
            'depth' => $validated['depth'] ?? 'standard',
            'note_style' => $validated['note_style'] ?? 'technical',
        ];

        try {
            $release = $analysisService->analyze(
                $repository,
                $validated['from_ref'],
                $validated['to_ref'],
                $options
            );

            return redirect()->route('releases.show', ['repository' => $repository, 'release' => $release]);
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Analysis failed: '.$e->getMessage());
        }
    }

    /**
     * Show the release recommendation screen.
     */
    public function show(Repository $repository, Release $release): Response
    {
        $this->authorize('view', $repository);

        // Load analysis logs for stage display
        $release->load('analysisLogs');

        return Inertia::render('Analysis/Show', [
            'repository' => $repository,
            'release' => $release,
            'stages' => $release->analysisLogs->map(fn ($log) => [
                'stage' => $log->stage,
                'status' => $log->status,
                'duration_ms' => $log->duration_ms,
                'response' => $log->response,
            ]),
        ]);
    }

    /**
     * Accept the release recommendation.
     */
    public function accept(Request $request, Repository $repository, Release $release): RedirectResponse
    {
        $this->authorize('update', $repository);

        $release->update([
            'status' => Release::STATUS_ACCEPTED,
            'final_version' => $release->recommended_version,
            'final_type' => $release->recommended_type,
        ]);

        return redirect()->route('releases.show', ['repository' => $repository, 'release' => $release])
            ->with('success', 'Release recommendation accepted!');
    }

    /**
     * Adjust the release recommendation.
     */
    public function adjust(Request $request, Repository $repository, Release $release): RedirectResponse
    {
        $this->authorize('update', $repository);

        $validated = $request->validate([
            'final_version' => ['required', 'string', 'regex:/^v?\d+\.\d+\.\d+$/'],
            'final_type' => ['required', 'in:MAJOR,MINOR,PATCH'],
            'reason_category' => ['required', 'string'],
            'reason_details' => ['nullable', 'string', 'max:1000'],
        ]);

        $release->update([
            'status' => Release::STATUS_ADJUSTED,
            'final_version' => $validated['final_version'],
            'final_type' => $validated['final_type'],
        ]);

        // Create feedback record
        $release->feedback()->create([
            'original_version' => $release->recommended_version,
            'original_type' => $release->recommended_type,
            'user_version' => $validated['final_version'],
            'user_type' => $validated['final_type'],
            'reason_category' => $validated['reason_category'],
            'reason_details' => $validated['reason_details'] ?? null,
        ]);

        return redirect()->route('releases.show', ['repository' => $repository, 'release' => $release])
            ->with('success', 'Release recommendation adjusted. Thank you for your feedback!');
    }

    /**
     * Reject the release recommendation.
     */
    public function reject(Request $request, Repository $repository, Release $release): RedirectResponse
    {
        $this->authorize('update', $repository);

        $validated = $request->validate([
            'reason_category' => ['nullable', 'string'],
            'reason_details' => ['nullable', 'string', 'max:1000'],
        ]);

        $release->update([
            'status' => Release::STATUS_REJECTED,
        ]);

        // Create feedback record if reason provided
        if (! empty($validated['reason_category'])) {
            $release->feedback()->create([
                'original_version' => $release->recommended_version,
                'original_type' => $release->recommended_type,
                'reason_category' => $validated['reason_category'],
                'reason_details' => $validated['reason_details'] ?? null,
            ]);
        }

        return redirect()->route('repositories.show', $repository)
            ->with('info', 'Release analysis rejected.');
    }

    /**
     * Regenerate release notes with a different style.
     */
    public function regenerateNotes(Request $request, Repository $repository, Release $release): JsonResponse
    {
        $this->authorize('update', $repository);

        $validated = $request->validate([
            'style' => ['required', 'in:technical,user_friendly,marketing'],
        ]);

        $noteGenerator = app(\App\Services\Analysis\NoteGenerator::class);

        try {
            $notes = $noteGenerator
                ->setStyle($validated['style'])
                ->generate(
                    $release->recommended_version,
                    $release->recommended_type,
                    $release->changes ?? [],
                    $validated['style']
                );

            $release->update(['release_notes' => $notes]);

            return response()->json([
                'success' => true,
                'notes' => $notes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
