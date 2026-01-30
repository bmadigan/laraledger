<?php

use App\Models\User;

test('home page redirects to setup when no user exists', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('setup.index'));
});

test('home page returns success when user exists', function () {
    User::factory()->create();

    $response = $this->get(route('home'));

    $response->assertOk();
});
