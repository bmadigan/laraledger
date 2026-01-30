<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Vite;

beforeEach(function () {
    Vite::useScriptTagAttributes([]);
    Vite::useStyleTagAttributes([]);
});

test('setup index redirects to welcome step when no user exists', function () {
    $response = $this->get(route('setup.index'));

    $response->assertRedirect(route('setup.step', ['step' => 'welcome']));
});

test('welcome step can be viewed', function () {
    Vite::useBuildDirectory('build');

    $response = $this->get(route('setup.step', ['step' => 'welcome']));

    $response->assertOk();
})->skip('Requires Vite manifest - run npm run build');

test('account step requires no existing user', function () {
    User::factory()->create();

    $response = $this->get(route('setup.step', ['step' => 'account']));

    // Should redirect because user exists
    $response->assertRedirect();
});

test('can create account through setup wizard', function () {
    $response = $this->post(route('setup.account.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('setup.step', ['step' => 'github']));
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
    ]);
});

test('github step requires authenticated user', function () {
    User::factory()->create();

    $response = $this->get(route('setup.step', ['step' => 'github']));

    // Should redirect because not logged in
    $response->assertRedirect();
});

test('ai-provider step requires github connected', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('setup.step', ['step' => 'ai-provider']));

    // Should redirect because github not connected
    $response->assertRedirect();
});

test('can save ai provider settings', function () {
    $user = User::factory()->withGithub()->create();
    $this->actingAs($user);

    $response = $this->post(route('setup.ai-provider.store'), [
        'provider' => 'anthropic',
        'api_key' => 'sk-ant-test-key-123456',
    ]);

    $response->assertRedirect(route('setup.step', ['step' => 'repository']));
    $this->assertTrue(Setting::has('anthropic_api_key'));
    $this->assertEquals('anthropic', Setting::get('default_ai_provider'));
});

test('can skip ai provider', function () {
    $user = User::factory()->withGithub()->create();
    $this->actingAs($user);

    $response = $this->post(route('setup.ai-provider.store'), [
        'provider' => 'anthropic',
        'api_key' => '',
        'skip' => true,
    ]);

    $response->assertRedirect(route('setup.step', ['step' => 'repository']));
    $this->assertFalse(Setting::has('anthropic_api_key'));
});

test('completing setup marks it as complete', function () {
    $user = User::factory()->withGithub()->create();
    $this->actingAs($user);

    $response = $this->post(route('setup.repository.store'), [
        'skip' => true,
    ]);

    $response->assertRedirect(route('setup.step', ['step' => 'complete']));
    $this->assertTrue(Setting::has('setup_completed_at'));
});

test('complete step redirects to dashboard when going to complete action', function () {
    $user = User::factory()->withGithub()->create();
    Setting::set('setup_completed_at', now()->toISOString());
    $this->actingAs($user);

    $response = $this->post(route('setup.complete'));

    $response->assertRedirect(route('dashboard'));
});

test('setup redirects to dashboard when already complete', function () {
    $user = User::factory()->withGithub()->create();
    Setting::set('setup_completed_at', now()->toISOString());
    $this->actingAs($user);

    $response = $this->get(route('setup.index'));

    $response->assertRedirect(route('dashboard'));
});
