<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use App\Services\GitHub\GitHubService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RepositoryController extends Controller
{
    /**
     * Display a listing of connected repositories.
     */
    public function index(): Response
    {
        $repositories = Auth::user()
            ->repositories()
            ->withCount('releases')
            ->orderBy('last_synced_at', 'desc')
            ->get();

        return Inertia::render('Repositories/Index', [
            'repositories' => $repositories,
        ]);
    }

    /**
     * Show the form for connecting a new repository.
     */
    public function create(): Response|RedirectResponse
    {
        $user = Auth::user();

        if (! $user->isGithubConnected()) {
            return redirect()->route('github.redirect')
                ->with('error', 'Please connect your GitHub account first.');
        }

        $githubService = new GitHubService($user);
        $githubRepos = $githubService->getAllRepositories();

        // Get IDs of already connected repos
        $connectedIds = $user->repositories()->pluck('github_id')->toArray();

        // Filter out already connected repos
        $availableRepos = array_filter($githubRepos, function ($repo) use ($connectedIds) {
            return ! in_array($repo['id'], $connectedIds);
        });

        return Inertia::render('Repositories/Create', [
            'availableRepositories' => array_values($availableRepos),
        ]);
    }

    /**
     * Store a newly connected repository.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // If adding by full_name only (public repo), fetch details from GitHub
        if ($request->has('full_name') && ! $request->has('github_id')) {
            return $this->storePublicRepository($request, $user);
        }

        $validated = $request->validate([
            'github_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_branch' => ['required', 'string', 'max:255'],
            'is_private' => ['required', 'boolean'],
        ]);

        // Check if already connected
        if ($user->repositories()->where('github_id', $validated['github_id'])->exists()) {
            return back()->with('error', 'This repository is already connected.');
        }

        $repository = $user->repositories()->create([
            ...$validated,
            'last_synced_at' => now(),
        ]);

        // Sync tags and branches
        $this->syncRepositoryData($repository);

        return redirect()->route('repositories.show', $repository)
            ->with('success', 'Repository connected successfully!');
    }

    /**
     * Store a public repository by fetching details from GitHub.
     */
    private function storePublicRepository(Request $request, \App\Models\User $user): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.-]+\/[a-zA-Z0-9_.-]+$/'],
        ]);

        $githubService = app(GitHubService::class, ['user' => $user]);
        $repoData = $githubService->getRepository($validated['full_name']);

        if (! $repoData) {
            return back()->with('error', 'Repository not found or not accessible. Make sure it exists and is public.');
        }

        // Check if already connected
        if ($user->repositories()->where('github_id', $repoData['id'])->exists()) {
            return back()->with('error', 'This repository is already connected.');
        }

        $repository = $user->repositories()->create([
            'github_id' => $repoData['id'],
            'name' => $repoData['name'],
            'full_name' => $repoData['full_name'],
            'description' => $repoData['description'] ?? null,
            'default_branch' => $repoData['default_branch'],
            'is_private' => $repoData['private'],
            'last_synced_at' => now(),
        ]);

        return redirect()->route('repositories.show', $repository)
            ->with('success', 'Repository connected successfully!');
    }

    /**
     * Display the specified repository.
     */
    public function show(Repository $repository): Response
    {
        $this->authorize('view', $repository);

        $repository->loadCount('releases');
        $repository->load(['releases' => function ($query) {
            $query->latest()->limit(10);
        }]);

        return Inertia::render('Repositories/Show', [
            'repository' => $repository,
        ]);
    }

    /**
     * Show the form for editing repository settings.
     */
    public function edit(Repository $repository): Response
    {
        $this->authorize('update', $repository);

        $user = Auth::user();
        $githubService = new GitHubService($user);
        $branches = $githubService->getBranches($repository->full_name);

        return Inertia::render('Repositories/Edit', [
            'repository' => $repository,
            'branches' => $branches,
        ]);
    }

    /**
     * Update the specified repository settings.
     */
    public function update(Request $request, Repository $repository): RedirectResponse
    {
        $this->authorize('update', $repository);

        $validated = $request->validate([
            'default_branch' => ['required', 'string', 'max:255'],
            'tag_pattern' => ['required', 'string', 'max:255'],
            'ignored_paths' => ['nullable', 'array'],
            'ignored_paths.*' => ['string', 'max:255'],
        ]);

        $repository->update($validated);

        return redirect()->route('repositories.show', $repository)
            ->with('success', 'Repository settings updated.');
    }

    /**
     * Remove the specified repository.
     */
    public function destroy(Repository $repository): RedirectResponse
    {
        $this->authorize('delete', $repository);

        $repository->delete();

        return redirect()->route('repositories.index')
            ->with('success', 'Repository disconnected successfully.');
    }

    /**
     * Sync repository data (tags, branches) from GitHub.
     */
    public function sync(Repository $repository): RedirectResponse
    {
        $this->authorize('update', $repository);

        $this->syncRepositoryData($repository);

        return back()->with('success', 'Repository synced successfully.');
    }

    /**
     * Sync repository metadata from GitHub.
     */
    private function syncRepositoryData(Repository $repository): void
    {
        $user = $repository->user;
        $githubService = new GitHubService($user);

        // Get latest repo info
        $repoData = $githubService->getRepository($repository->full_name);

        if ($repoData) {
            $repository->update([
                'description' => $repoData['description'] ?? null,
                'default_branch' => $repoData['default_branch'],
                'is_private' => $repoData['private'],
                'last_synced_at' => now(),
            ]);
        }
    }
}
