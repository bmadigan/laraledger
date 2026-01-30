<?php

use App\Models\Release;
use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    // Ensure setup is marked as complete
    Setting::set('setup_completed_at', now());
});

it('loads demo data when debug mode is enabled', function () {
    config(['app.debug' => true]);

    $user = User::factory()->setupCompleted()->create();

    $this->actingAs($user)
        ->post(route('laraledger.settings.demo-data'))
        ->assertRedirect();

    // Check that demo repositories were created
    expect($user->repositories()->count())->toBeGreaterThanOrEqual(3);

    // Check that releases were created
    expect(Release::whereIn('repository_id', $user->repositories()->pluck('id'))->count())
        ->toBeGreaterThan(0);
});

it('denies demo data loading when debug mode is disabled', function () {
    config(['app.debug' => false]);

    $user = User::factory()->setupCompleted()->create();

    $this->actingAs($user)
        ->post(route('laraledger.settings.demo-data'))
        ->assertForbidden();
});

it('requires authentication to load demo data', function () {
    config(['app.debug' => true]);

    $this->post(route('laraledger.settings.demo-data'))
        ->assertRedirect(route('login'));
});
