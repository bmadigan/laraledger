<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Services\GitHub\GitHubService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;

class SetupWizardController extends Controller
{
    public const STEPS = [
        'welcome' => 1,
        'account' => 2,
        'github' => 3,
        'ai-provider' => 4,
        'repository' => 5,
        'complete' => 6,
    ];

    public function index(): Response|RedirectResponse
    {
        // If user exists and setup is complete, redirect to dashboard
        if ($this->isSetupComplete()) {
            return redirect()->route('dashboard');
        }

        // Determine current step based on what's been configured
        $currentStep = $this->determineCurrentStep();

        return redirect()->route('setup.step', ['step' => $currentStep]);
    }

    public function show(string $step): Response|RedirectResponse
    {
        // Validate step
        if (! array_key_exists($step, self::STEPS)) {
            return redirect()->route('setup.index');
        }

        // If setup is complete and not on completion step, redirect to dashboard
        if ($this->isSetupComplete() && $step !== 'complete') {
            return redirect()->route('dashboard');
        }

        // Check if user can access this step (must complete previous steps)
        $canAccess = $this->canAccessStep($step);
        if (! $canAccess) {
            return redirect()->route('setup.step', ['step' => $this->determineCurrentStep()]);
        }

        $data = [
            'currentStep' => $step,
            'stepNumber' => self::STEPS[$step],
            'totalSteps' => count(self::STEPS),
            'completedSteps' => $this->getCompletedSteps(),
        ];

        // Add step-specific data
        $data = array_merge($data, $this->getStepData($step));

        return Inertia::render('Setup/Wizard', $data);
    }

    public function createAccount(Request $request): RedirectResponse
    {
        // Don't allow if account already exists
        if (User::exists()) {
            return redirect()->route('setup.step', ['step' => 'github']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        Auth::login($user);

        return redirect()->route('setup.step', ['step' => 'github']);
    }

    public function redirectToGitHub(): RedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(['repo', 'read:user'])
            ->redirectUrl(route('setup.github.callback'))
            ->redirect();
    }

    public function handleGitHubCallback(): RedirectResponse
    {
        try {
            $githubUser = Socialite::driver('github')
                ->redirectUrl(route('setup.github.callback'))
                ->user();
            $user = Auth::user();

            if (! $user) {
                return redirect()->route('setup.step', ['step' => 'github'])
                    ->with('error', 'You must be logged in to connect GitHub.');
            }

            $user->update([
                'github_id' => $githubUser->getId(),
                'github_username' => $githubUser->getNickname(),
                'github_token' => encrypt($githubUser->token),
                'github_refresh_token' => $githubUser->refreshToken ? encrypt($githubUser->refreshToken) : null,
                'github_token_expires_at' => $githubUser->expiresIn
                    ? now()->addSeconds($githubUser->expiresIn)
                    : null,
            ]);

            return redirect()->route('setup.step', ['step' => 'ai-provider']);
        } catch (\Exception $e) {
            return redirect()->route('setup.step', ['step' => 'github'])
                ->with('error', 'Failed to connect GitHub: '.$e->getMessage());
        }
    }

    public function saveAiProvider(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:anthropic,openai'],
            'api_key' => ['nullable', 'string', 'min:10'],
            'skip' => ['nullable', 'boolean'],
        ]);

        if (! ($validated['skip'] ?? false) && ! empty($validated['api_key'])) {
            $keyName = $validated['provider'].'_api_key';
            Setting::set($keyName, $validated['api_key'], encrypt: true);
            Setting::set('default_ai_provider', $validated['provider']);
        }

        return redirect()->route('setup.step', ['step' => 'repository']);
    }

    public function testAiProvider(Request $request)
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:anthropic,openai'],
            'api_key' => ['required', 'string', 'min:10'],
        ]);

        $isValid = false;
        $message = '';
        $models = [];

        try {
            if ($validated['provider'] === 'anthropic') {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'x-api-key' => $validated['api_key'],
                    'anthropic-version' => '2023-06-01',
                ])->get('https://api.anthropic.com/v1/models');

                if ($response->successful()) {
                    $isValid = true;
                    $models = ['claude-sonnet-4-20250514', 'claude-3-5-haiku-20241022'];
                    $message = 'API key is valid!';
                } else {
                    $message = 'Invalid API key or API error.';
                }
            } elseif ($validated['provider'] === 'openai') {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer '.$validated['api_key'],
                ])->get('https://api.openai.com/v1/models');

                if ($response->successful()) {
                    $isValid = true;
                    $models = ['gpt-4o', 'gpt-4o-mini'];
                    $message = 'API key is valid!';
                } else {
                    $message = 'Invalid API key or API error.';
                }
            }
        } catch (\Exception $e) {
            $message = 'Connection error: '.$e->getMessage();
        }

        return response()->json([
            'valid' => $isValid,
            'message' => $message,
            'models' => $models,
        ]);
    }

    public function saveRepository(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'github_id' => ['nullable', 'integer'],
            'skip' => ['nullable', 'boolean'],
        ]);

        if (! ($validated['skip'] ?? false) && ! empty($validated['github_id'])) {
            $user = Auth::user();
            $github = new GitHubService($user);

            // Get available repositories and find the selected one
            $repos = $github->getAllRepositories();
            $selectedRepo = collect($repos)->firstWhere('id', $validated['github_id']);

            if ($selectedRepo) {
                $user->repositories()->updateOrCreate(
                    ['github_id' => $selectedRepo['id']],
                    [
                        'name' => $selectedRepo['name'],
                        'full_name' => $selectedRepo['full_name'],
                        'description' => $selectedRepo['description'] ?? null,
                        'default_branch' => $selectedRepo['default_branch'] ?? 'main',
                        'is_private' => $selectedRepo['private'] ?? false,
                    ]
                );
            }
        }

        // Mark setup as complete
        Setting::set('setup_completed_at', now()->toISOString());

        return redirect()->route('setup.step', ['step' => 'complete']);
    }

    public function complete(): RedirectResponse
    {
        return redirect()->route('dashboard');
    }

    protected function isSetupComplete(): bool
    {
        // Setup is complete if we have a user and GitHub is connected
        $user = Auth::user() ?? User::first();

        if (! $user) {
            return false;
        }

        return $user->isGithubConnected() && Setting::has('setup_completed_at');
    }

    protected function determineCurrentStep(): string
    {
        // No user exists - start at welcome
        if (! User::exists()) {
            return 'welcome';
        }

        $user = Auth::user() ?? User::first();

        // No GitHub connected
        if (! $user || ! $user->isGithubConnected()) {
            // If user exists but not logged in, they need to login first
            if (! Auth::check()) {
                return 'welcome';
            }

            return 'github';
        }

        // No AI provider configured
        if (! Setting::has('anthropic_api_key') && ! Setting::has('openai_api_key') && ! Setting::has('setup_completed_at')) {
            return 'ai-provider';
        }

        // No repositories added and setup not complete
        if ($user->repositories()->count() === 0 && ! Setting::has('setup_completed_at')) {
            return 'repository';
        }

        return 'complete';
    }

    protected function canAccessStep(string $step): bool
    {
        $user = Auth::user() ?? User::first();

        return match ($step) {
            'welcome' => true,
            'account' => ! User::exists(),
            'github' => User::exists() && Auth::check(),
            'ai-provider' => $user && $user->isGithubConnected(),
            'repository' => $user && $user->isGithubConnected(),
            'complete' => Setting::has('setup_completed_at'),
            default => false,
        };
    }

    protected function getCompletedSteps(): array
    {
        $completed = [];
        $user = Auth::user() ?? User::first();

        // Welcome is always complete once viewed
        $completed[] = 'welcome';

        // Account is complete if user exists
        if (User::exists()) {
            $completed[] = 'account';
        }

        // GitHub is complete if connected
        if ($user && $user->isGithubConnected()) {
            $completed[] = 'github';
        }

        // AI Provider is complete if configured or skipped
        if (Setting::has('anthropic_api_key') || Setting::has('openai_api_key') || Setting::has('setup_completed_at')) {
            $completed[] = 'ai-provider';
        }

        // Repository is complete if at least one exists or setup is complete
        if (($user && $user->repositories()->count() > 0) || Setting::has('setup_completed_at')) {
            $completed[] = 'repository';
        }

        // Complete step is done when setup_completed_at is set
        if (Setting::has('setup_completed_at')) {
            $completed[] = 'complete';
        }

        return $completed;
    }

    protected function getStepData(string $step): array
    {
        $user = Auth::user();

        return match ($step) {
            'github' => [
                'connected' => $user?->isGithubConnected() ?? false,
                'username' => $user?->github_username,
            ],
            'ai-provider' => [
                'hasAnthropicKey' => Setting::has('anthropic_api_key') || config('services.anthropic.api_key'),
                'hasOpenaiKey' => Setting::has('openai_api_key') || config('services.openai.api_key'),
                'defaultProvider' => Setting::get('default_ai_provider'),
            ],
            'repository' => [
                'repositories' => $user?->isGithubConnected()
                    ? (new GitHubService($user))->getAllRepositories()
                    : [],
                'connectedRepositories' => $user?->repositories()->pluck('github_id')->toArray() ?? [],
            ],
            'complete' => [
                'hasRepositories' => $user?->repositories()->count() > 0,
                'hasAiProvider' => Setting::has('anthropic_api_key') || Setting::has('openai_api_key'),
            ],
            default => [],
        };
    }
}
