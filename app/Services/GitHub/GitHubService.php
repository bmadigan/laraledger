<?php

namespace App\Services\GitHub;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GitHubService
{
    private const API_BASE_URL = 'https://api.github.com';

    public function __construct(
        private readonly User $user
    ) {}

    /**
     * Get the authenticated HTTP client for GitHub API.
     */
    private function client(): PendingRequest
    {
        $token = $this->user->github_token ? decrypt($this->user->github_token) : null;

        return Http::withHeaders([
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ])->withToken($token);
    }

    /**
     * Get the authenticated user's GitHub profile.
     *
     * @return array{id: int, login: string, name: string|null, avatar_url: string}|null
     */
    public function getAuthenticatedUser(): ?array
    {
        $response = $this->client()->get(self::API_BASE_URL.'/user');

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Get repositories accessible to the authenticated user.
     *
     * @return array<int, array{id: int, name: string, full_name: string, description: string|null, private: bool, default_branch: string}>
     */
    public function getRepositories(int $perPage = 100, int $page = 1): array
    {
        $response = $this->client()->get(self::API_BASE_URL.'/user/repos', [
            'per_page' => $perPage,
            'page' => $page,
            'sort' => 'updated',
            'direction' => 'desc',
        ]);

        if ($response->failed()) {
            return [];
        }

        return $response->json();
    }

    /**
     * Get all repositories (handles pagination).
     *
     * @return array<int, array{id: int, name: string, full_name: string, description: string|null, private: bool, default_branch: string}>
     */
    public function getAllRepositories(): array
    {
        $allRepos = [];
        $page = 1;
        $perPage = 100;

        do {
            $repos = $this->getRepositories($perPage, $page);
            $allRepos = array_merge($allRepos, $repos);
            $page++;
        } while (count($repos) === $perPage);

        return $allRepos;
    }

    /**
     * Get a specific repository by full name (owner/repo).
     *
     * @return array{id: int, name: string, full_name: string, description: string|null, private: bool, default_branch: string}|null
     */
    public function getRepository(string $fullName): ?array
    {
        $response = $this->client()->get(self::API_BASE_URL."/repos/{$fullName}");

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Get branches for a repository.
     *
     * @return array<int, array{name: string, protected: bool}>
     */
    public function getBranches(string $fullName, int $perPage = 100): array
    {
        $response = $this->client()->get(self::API_BASE_URL."/repos/{$fullName}/branches", [
            'per_page' => $perPage,
        ]);

        if ($response->failed()) {
            return [];
        }

        return $response->json();
    }

    /**
     * Get tags for a repository.
     *
     * @return array<int, array{name: string, commit: array{sha: string}}>
     */
    public function getTags(string $fullName, int $perPage = 100): array
    {
        $response = $this->client()->get(self::API_BASE_URL."/repos/{$fullName}/tags", [
            'per_page' => $perPage,
        ]);

        if ($response->failed()) {
            return [];
        }

        return $response->json();
    }

    /**
     * Get commits between two refs.
     *
     * @return array<int, array{sha: string, commit: array{message: string, author: array{name: string, date: string}}, author: array{login: string}|null}>
     */
    public function getCommitsBetween(string $fullName, string $base, string $head): array
    {
        $response = $this->client()->get(self::API_BASE_URL."/repos/{$fullName}/compare/{$base}...{$head}");

        if ($response->failed()) {
            return [];
        }

        return $response->json('commits') ?? [];
    }

    /**
     * Get a specific commit by SHA.
     *
     * @return array{sha: string, commit: array{message: string}, files: array<int, array{filename: string, status: string, additions: int, deletions: int, patch: string|null}>}|null
     */
    public function getCommit(string $fullName, string $sha): ?array
    {
        $response = $this->client()->get(self::API_BASE_URL."/repos/{$fullName}/commits/{$sha}");

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Get pull requests associated with a commit.
     *
     * @return array<int, array{number: int, title: string, labels: array<int, array{name: string}>}>
     */
    public function getPullRequestsForCommit(string $fullName, string $sha): array
    {
        $response = $this->client()->get(self::API_BASE_URL."/repos/{$fullName}/commits/{$sha}/pulls");

        if ($response->failed()) {
            return [];
        }

        return $response->json();
    }

    /**
     * Check if the token is valid.
     */
    public function isTokenValid(): bool
    {
        return $this->getAuthenticatedUser() !== null;
    }
}
