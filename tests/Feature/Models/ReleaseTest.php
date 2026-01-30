<?php

use App\Models\AnalysisLog;
use App\Models\Feedback;
use App\Models\Release;
use App\Models\Repository;

test('release belongs to repository', function () {
    $repository = Repository::factory()->create();
    $release = Release::factory()->create(['repository_id' => $repository->id]);

    expect($release->repository)->toBeInstanceOf(Repository::class)
        ->and($release->repository->id)->toBe($repository->id);
});

test('release has one feedback', function () {
    $release = Release::factory()->create();
    Feedback::factory()->create(['release_id' => $release->id]);

    expect($release->feedback)->toBeInstanceOf(Feedback::class);
});

test('release has many analysis logs', function () {
    $release = Release::factory()->create();
    AnalysisLog::factory()->count(3)->create(['release_id' => $release->id]);

    expect($release->analysisLogs)->toHaveCount(3);
});

test('release status helpers work correctly', function () {
    $pending = Release::factory()->create(['status' => Release::STATUS_PENDING]);
    $accepted = Release::factory()->accepted()->create();
    $adjusted = Release::factory()->adjusted()->create();
    $rejected = Release::factory()->rejected()->create();

    expect($pending->isPending())->toBeTrue()
        ->and($pending->isAccepted())->toBeFalse()
        ->and($accepted->isAccepted())->toBeTrue()
        ->and($adjusted->isAdjusted())->toBeTrue()
        ->and($rejected->isRejected())->toBeTrue();
});

test('release wasCorrect returns true for accepted releases', function () {
    $release = Release::factory()->accepted()->create();

    expect($release->wasCorrect())->toBeTrue();
});

test('release wasCorrect returns true for adjusted releases with same type', function () {
    $release = Release::factory()->create([
        'status' => Release::STATUS_ADJUSTED,
        'recommended_type' => Release::TYPE_MINOR,
        'final_type' => Release::TYPE_MINOR,
    ]);

    expect($release->wasCorrect())->toBeTrue();
});

test('release wasCorrect returns false for adjusted releases with different type', function () {
    $release = Release::factory()->create([
        'status' => Release::STATUS_ADJUSTED,
        'recommended_type' => Release::TYPE_MINOR,
        'final_type' => Release::TYPE_MAJOR,
    ]);

    expect($release->wasCorrect())->toBeFalse();
});

test('release change getters work correctly', function () {
    $changes = [
        'breaking' => ['Removed deprecated API'],
        'features' => ['Added new feature', 'Another feature'],
        'fixes' => ['Fixed bug'],
    ];

    $release = Release::factory()->create(['changes' => $changes]);
    $release->refresh();

    expect($release->changes)->toBe($changes)
        ->and($release->getBreakingChanges())->toHaveCount(1)
        ->and($release->getFeatures())->toHaveCount(2)
        ->and($release->getFixes())->toHaveCount(1);
});

test('release casts attributes correctly', function () {
    $release = Release::factory()->create();
    $release->refresh();

    expect($release->confidence)->toBeInt()
        ->and($release->commits)->toBeArray()
        ->and($release->changes)->toBeArray();
});

test('release factory type states work', function () {
    $major = Release::factory()->major()->create();
    $minor = Release::factory()->minor()->create();
    $patch = Release::factory()->patch()->create();

    expect($major->recommended_type)->toBe(Release::TYPE_MAJOR)
        ->and($minor->recommended_type)->toBe(Release::TYPE_MINOR)
        ->and($patch->recommended_type)->toBe(Release::TYPE_PATCH);
});

test('release factory confidence states work', function () {
    $high = Release::factory()->highConfidence()->create();
    $low = Release::factory()->lowConfidence()->create();

    expect($high->confidence)->toBeGreaterThanOrEqual(85)
        ->and($low->confidence)->toBeLessThanOrEqual(70);
});
