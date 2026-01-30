<?php

use App\Models\Setting;
use App\Models\User;

test('guests are redirected to the login page', function () {
    // Create a user so the home page doesn't redirect to setup
    User::factory()->create();

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users without setup complete are redirected to setup', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('setup.index'));
});

test('authenticated users with setup complete can visit the dashboard', function () {
    $user = User::factory()->withGithub()->create();
    Setting::set('setup_completed_at', now()->toISOString());
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
