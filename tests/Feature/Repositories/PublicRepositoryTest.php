<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Ensure setup is marked as complete
    Setting::set('setup_completed_at', now());
});

it('can add a public repository by full name', function () {
    $user = User::factory()->setupCompleted()->create();

    // Fake the GitHub API response
    Http::fake([
        'api.github.com/repos/laravel/framework' => Http::response([
            'id' => 1234567,
            'name' => 'framework',
            'full_name' => 'laravel/framework',
            'description' => 'The Laravel Framework.',
            'default_branch' => 'master',
            'private' => false,
        ]),
    ]);

    $response = $this->actingAs($user)
        ->post(route('repositories.store'), [
            'full_name' => 'laravel/framework',
        ]);

    $response->assertRedirect();
    expect($user->repositories()->where('full_name', 'laravel/framework')->exists())->toBeTrue();
});

it('validates full name format for public repository', function () {
    $user = User::factory()->setupCompleted()->create();

    $response = $this->actingAs($user)
        ->from(route('repositories.create'))
        ->post(route('repositories.store'), [
            'full_name' => 'invalid-format',
        ]);

    $response->assertRedirect(route('repositories.create'));
    $response->assertSessionHasErrors('full_name');
});

it('returns error for non-existent repository', function () {
    $user = User::factory()->setupCompleted()->create();

    // Fake the GitHub API response - 404 not found
    Http::fake([
        'api.github.com/repos/nonexistent/repo' => Http::response([], 404),
    ]);

    $response = $this->actingAs($user)
        ->from(route('repositories.create'))
        ->post(route('repositories.store'), [
            'full_name' => 'nonexistent/repo',
        ]);

    $response->assertRedirect(route('repositories.create'));
    $response->assertSessionHas('error');
});

it('prevents adding duplicate public repository', function () {
    $user = User::factory()->setupCompleted()->create();

    // Create an existing repository
    $user->repositories()->create([
        'github_id' => 1234567,
        'name' => 'framework',
        'full_name' => 'laravel/framework',
        'default_branch' => 'master',
        'is_private' => false,
    ]);

    // Fake the GitHub API response
    Http::fake([
        'api.github.com/repos/laravel/framework' => Http::response([
            'id' => 1234567,
            'name' => 'framework',
            'full_name' => 'laravel/framework',
            'description' => 'The Laravel Framework.',
            'default_branch' => 'master',
            'private' => false,
        ]),
    ]);

    $response = $this->actingAs($user)
        ->from(route('repositories.create'))
        ->post(route('repositories.store'), [
            'full_name' => 'laravel/framework',
        ]);

    $response->assertRedirect(route('repositories.create'));
    $response->assertSessionHas('error', 'This repository is already connected.');
});
