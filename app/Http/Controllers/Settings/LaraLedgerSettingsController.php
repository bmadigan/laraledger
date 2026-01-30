<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use PrismPHP\Prism\Prism;

class LaraLedgerSettingsController extends Controller
{
    /**
     * Display the LaraLedger settings page.
     */
    public function index(): Response
    {
        $user = Auth::user();

        return Inertia::render('settings/LaraLedger', [
            'github' => [
                'connected' => $user->isGithubConnected(),
                'username' => $user->github_nickname,
                'scopes' => $user->github_token ? ['repo'] : [],
            ],
            'aiProvider' => [
                'provider' => Setting::get('ai_provider', 'anthropic'),
                'hasKey' => ! empty(Setting::get('ai_api_key')),
                'model' => Setting::get('ai_classification_model', config('laraledger.ai.classification.model')),
            ],
            'generationProvider' => [
                'provider' => Setting::get('ai_generation_provider', 'anthropic'),
                'hasKey' => ! empty(Setting::get('ai_generation_api_key')) || ! empty(Setting::get('ai_api_key')),
                'model' => Setting::get('ai_generation_model', config('laraledger.ai.generation.model')),
            ],
            'analysisDefaults' => [
                'depth' => Setting::get('default_analysis_depth', 'standard'),
                'noteStyle' => Setting::get('default_note_style', 'technical'),
                'heuristicThreshold' => (int) Setting::get('heuristic_threshold', config('laraledger.heuristics.confidence_threshold')),
            ],
            'availableModels' => $this->getAvailableModels(),
            'isDebugMode' => config('app.debug'),
        ]);
    }

    /**
     * Update AI provider settings.
     */
    public function updateAiProvider(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:anthropic,openai'],
            'api_key' => ['nullable', 'string'],
            'model' => ['required', 'string'],
        ]);

        Setting::set('ai_provider', $validated['provider']);
        Setting::set('ai_classification_model', $validated['model']);

        if (! empty($validated['api_key'])) {
            Setting::set('ai_api_key', $validated['api_key'], encrypt: true);
        }

        return back()->with('success', 'AI provider settings updated.');
    }

    /**
     * Update generation provider settings.
     */
    public function updateGenerationProvider(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:anthropic,openai'],
            'api_key' => ['nullable', 'string'],
            'model' => ['required', 'string'],
        ]);

        Setting::set('ai_generation_provider', $validated['provider']);
        Setting::set('ai_generation_model', $validated['model']);

        if (! empty($validated['api_key'])) {
            Setting::set('ai_generation_api_key', $validated['api_key'], encrypt: true);
        }

        return back()->with('success', 'Generation provider settings updated.');
    }

    /**
     * Update analysis defaults.
     */
    public function updateAnalysisDefaults(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'depth' => ['required', 'in:standard,deep'],
            'note_style' => ['required', 'in:technical,user_friendly,marketing'],
            'heuristic_threshold' => ['required', 'integer', 'min:50', 'max:100'],
        ]);

        Setting::set('default_analysis_depth', $validated['depth']);
        Setting::set('default_note_style', $validated['note_style']);
        Setting::set('heuristic_threshold', $validated['heuristic_threshold']);

        return back()->with('success', 'Analysis defaults updated.');
    }

    /**
     * Test AI provider connection.
     */
    public function testAiProvider(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:anthropic,openai'],
            'api_key' => ['required', 'string'],
        ]);

        try {
            // Build prism configuration dynamically
            $prism = Prism::text()
                ->using($validated['provider'], $validated['provider'] === 'anthropic' ? 'claude-3-5-haiku-20241022' : 'gpt-4o-mini')
                ->withPrompt('Say "Connection successful" in exactly those words.');

            // Set API key via config
            $configKey = $validated['provider'] === 'anthropic' ? 'prism.providers.anthropic.api_key' : 'prism.providers.openai.api_key';
            config([$configKey => $validated['api_key']]);

            $response = $prism->generate();

            return response()->json([
                'success' => true,
                'message' => 'Connection successful!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Export all user data.
     */
    public function exportData(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = Auth::user();

        $data = [
            'exported_at' => now()->toIso8601String(),
            'user' => [
                'email' => $user->email,
                'created_at' => $user->created_at,
            ],
            'repositories' => $user->repositories->map(function ($repo) {
                return [
                    'name' => $repo->name,
                    'full_name' => $repo->full_name,
                    'default_branch' => $repo->default_branch,
                    'created_at' => $repo->created_at,
                    'releases' => $repo->releases->map(function ($release) {
                        return [
                            'version' => $release->recommended_version,
                            'from_ref' => $release->from_ref,
                            'to_ref' => $release->to_ref,
                            'recommended_type' => $release->recommended_type,
                            'final_type' => $release->final_type,
                            'status' => $release->status,
                            'confidence' => $release->confidence,
                            'release_notes' => $release->release_notes,
                            'created_at' => $release->created_at,
                        ];
                    }),
                ];
            }),
            'settings' => Setting::all()->pluck('value', 'key'),
        ];

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT);
        }, 'laraledger-export-'.now()->format('Y-m-d').'.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Clear all analysis history.
     */
    public function clearHistory(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'string', 'in:DELETE ALL'],
        ]);

        $user = Auth::user();
        $repositoryIds = $user->repositories()->pluck('id');

        // Delete all releases and related data
        \App\Models\Release::whereIn('repository_id', $repositoryIds)->delete();

        return back()->with('success', 'All analysis history has been cleared.');
    }

    /**
     * Load demo data for testing purposes.
     * Only available in debug/development mode.
     */
    public function loadDemoData(): RedirectResponse
    {
        if (! config('app.debug')) {
            abort(403, 'Demo data loading is only available in debug mode.');
        }

        // Run the seeder with the authenticated user context
        $seeder = new \Database\Seeders\DemoDataSeeder;
        $seeder->run();

        return back()->with('success', 'Demo data loaded successfully! Check your repositories and releases.');
    }

    /**
     * Get available models for each provider.
     *
     * @return array<string, array<int, array{id: string, name: string}>>
     */
    private function getAvailableModels(): array
    {
        return [
            'anthropic' => [
                ['id' => 'claude-3-5-haiku-20241022', 'name' => 'Claude 3.5 Haiku (Fast, Cost-effective)'],
                ['id' => 'claude-sonnet-4-20250514', 'name' => 'Claude Sonnet 4 (Balanced)'],
                ['id' => 'claude-opus-4-20250514', 'name' => 'Claude Opus 4 (Most Capable)'],
            ],
            'openai' => [
                ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini (Fast, Cost-effective)'],
                ['id' => 'gpt-4o', 'name' => 'GPT-4o (Balanced)'],
                ['id' => 'gpt-4-turbo', 'name' => 'GPT-4 Turbo (Most Capable)'],
            ],
        ];
    }
}
