<?php

use App\Models\Release;
use App\Models\Repository;
use App\Models\User;

test('repository belongs to user', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create(['user_id' => $user->id]);

    expect($repository->user)->toBeInstanceOf(User::class)
        ->and($repository->user->id)->toBe($user->id);
});

test('repository has many releases', function () {
    $repository = Repository::factory()->create();
    Release::factory()->count(3)->create(['repository_id' => $repository->id]);

    expect($repository->releases)->toHaveCount(3)
        ->and($repository->releases->first())->toBeInstanceOf(Release::class);
});

test('repository can get latest release', function () {
    $repository = Repository::factory()->create();

    Release::factory()->create([
        'repository_id' => $repository->id,
        'version' => 'v1.0.0',
        'created_at' => now()->subDays(2),
    ]);

    $latestRelease = Release::factory()->create([
        'repository_id' => $repository->id,
        'version' => 'v1.1.0',
        'created_at' => now(),
    ]);

    expect($repository->getLatestRelease()->id)->toBe($latestRelease->id)
        ->and($repository->getLatestTag())->toBe('v1.1.0');
});

test('repository casts attributes correctly', function () {
    $repository = Repository::factory()->create([
        'ignored_paths' => ['tests/', 'docs/'],
        'settings' => ['auto_analyze' => true],
        'is_private' => true,
        'last_synced_at' => null,
    ]);

    $repository->refresh();

    expect($repository->ignored_paths)->toBeArray()
        ->and($repository->settings)->toBeArray()
        ->and($repository->is_private)->toBeBool()
        ->and($repository->last_synced_at)->toBeNull();
});

test('repository factory creates valid model', function () {
    $repository = Repository::factory()->create();

    expect($repository)->toBeInstanceOf(Repository::class)
        ->and($repository->github_id)->toBeInt()
        ->and($repository->name)->toBeString()
        ->and($repository->full_name)->toContain('/');
});

test('repository factory private state works', function () {
    $repository = Repository::factory()->private()->create();

    expect($repository->is_private)->toBeTrue();
});

test('repository factory public state works', function () {
    $repository = Repository::factory()->public()->create();

    expect($repository->is_private)->toBeFalse();
});
