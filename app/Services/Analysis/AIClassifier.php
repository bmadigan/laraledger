<?php

namespace App\Services\Analysis;

use App\Models\Release;
use App\Models\Setting;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;

class AIClassifier
{
    protected string $provider;

    protected string $model;

    public function __construct()
    {
        $this->provider = Setting::get('default_ai_provider')
            ?? config('laraledger.ai.classification.provider', 'anthropic');

        $this->model = Setting::get('ai_classification_model')
            ?? config('laraledger.ai.classification.model', 'claude-3-5-haiku-20241022');
    }

    /**
     * Classify changes using AI to determine version type.
     *
     * @param  array  $commits  Array of commit data
     * @param  array  $heuristicResult  Result from heuristic analysis for context
     * @return array Classification result with type, confidence, reasoning, and changes
     */
    public function classify(array $commits, array $heuristicResult = []): array
    {
        $prompt = $this->buildClassificationPrompt($commits, $heuristicResult);

        try {
            $response = Prism::text()
                ->using($this->getProvider(), $this->model)
                ->withSystemPrompt($this->getSystemPrompt())
                ->withPrompt($prompt)
                ->asText();

            $result = json_decode($response->text, true);

            if (! $result) {
                throw new \RuntimeException('Failed to parse AI response as JSON');
            }

            return $this->normalizeResult($result);
        } catch (\Exception $e) {
            // Return fallback result on error
            return [
                'recommended_type' => $heuristicResult['recommended_type'] ?? Release::TYPE_PATCH,
                'confidence' => $heuristicResult['confidence'] ?? 50,
                'reasoning' => ['AI classification failed: '.$e->getMessage()],
                'changes' => $heuristicResult['changes'] ?? [],
                'source' => Release::SOURCE_HEURISTIC,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get the Prism provider enum.
     */
    protected function getProvider(): Provider
    {
        return match ($this->provider) {
            'anthropic' => Provider::Anthropic,
            'openai' => Provider::OpenAI,
            'ollama' => Provider::Ollama,
            'mistral' => Provider::Mistral,
            'groq' => Provider::Groq,
            'gemini' => Provider::Gemini,
            default => Provider::Anthropic,
        };
    }

    /**
     * Get the system prompt for classification.
     */
    protected function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a semantic versioning expert analyzing code changes to recommend version bumps.

Your task is to analyze the provided commits and determine the appropriate version type:
- MAJOR: Breaking changes that require consumers to modify their code
- MINOR: New features that are backwards-compatible
- PATCH: Bug fixes and minor improvements that don't add features

Key indicators of breaking changes:
- Removed public methods, classes, or interfaces
- Changed method signatures (parameters, return types)
- Renamed public APIs
- Database schema changes that drop columns or tables
- Changed configuration keys or formats
- Modified behavior of existing features in incompatible ways

Key indicators of features (MINOR):
- New public methods, classes, or endpoints
- New configuration options
- New optional parameters
- New functionality that doesn't break existing code

Key indicators of fixes (PATCH):
- Bug fixes
- Performance improvements
- Documentation updates
- Dependency updates (unless they change behavior)
- Internal refactoring without API changes

When in doubt, be conservative:
- If there's ANY possibility of a breaking change, recommend MAJOR
- If it adds functionality but might break edge cases, recommend MINOR

Always respond with valid JSON in this exact format:
{
  "recommended_type": "MAJOR" | "MINOR" | "PATCH",
  "confidence": 0-100,
  "reasoning": ["reason 1", "reason 2"],
  "changes": {
    "breaking": [{"description": "...", "sha": "..."}],
    "features": [{"description": "...", "sha": "..."}],
    "fixes": [{"description": "...", "sha": "..."}],
    "other": [{"description": "...", "sha": "..."}]
  }
}
PROMPT;
    }

    /**
     * Build the classification prompt from commits and heuristic context.
     */
    protected function buildClassificationPrompt(array $commits, array $heuristicResult = []): string
    {
        $commitSummaries = [];
        foreach ($commits as $commit) {
            $sha = substr($commit['sha'] ?? '', 0, 7);
            $message = $commit['commit']['message'] ?? $commit['message'] ?? 'No message';
            $files = $commit['files'] ?? [];
            $fileCount = count($files);
            $fileList = implode(', ', array_slice(array_map(fn ($f) => is_array($f) ? ($f['filename'] ?? '') : $f, $files), 0, 5));

            $commitSummaries[] = "- [{$sha}] {$message} ({$fileCount} files: {$fileList})";
        }

        $commitText = implode("\n", $commitSummaries);

        $prompt = "Analyze these commits and recommend a semantic version type:\n\n";
        $prompt .= "COMMITS:\n{$commitText}\n\n";

        if (! empty($heuristicResult)) {
            $prompt .= "HEURISTIC ANALYSIS (for context):\n";
            $prompt .= "- Recommended: {$heuristicResult['recommended_type']}\n";
            $prompt .= "- Confidence: {$heuristicResult['confidence']}%\n";
            $prompt .= '- Reasoning: '.implode('; ', $heuristicResult['reasoning'] ?? [])."\n\n";
        }

        $prompt .= 'Provide your classification as JSON.';

        return $prompt;
    }

    /**
     * Normalize the AI response to match our expected format.
     */
    protected function normalizeResult(array $result): array
    {
        $type = strtoupper($result['recommended_type'] ?? 'PATCH');
        if (! in_array($type, [Release::TYPE_MAJOR, Release::TYPE_MINOR, Release::TYPE_PATCH])) {
            $type = Release::TYPE_PATCH;
        }

        return [
            'recommended_type' => $type,
            'confidence' => (int) ($result['confidence'] ?? 70),
            'reasoning' => $result['reasoning'] ?? [],
            'changes' => [
                'breaking' => $this->normalizeChanges($result['changes']['breaking'] ?? []),
                'features' => $this->normalizeChanges($result['changes']['features'] ?? []),
                'fixes' => $this->normalizeChanges($result['changes']['fixes'] ?? []),
                'other' => $this->normalizeChanges($result['changes']['other'] ?? []),
            ],
            'source' => Release::SOURCE_AI,
        ];
    }

    /**
     * Normalize change arrays to consistent format.
     */
    protected function normalizeChanges(array $changes): array
    {
        return array_map(function ($change) {
            if (is_string($change)) {
                return ['description' => $change, 'sha' => ''];
            }

            return [
                'description' => $change['description'] ?? $change['message'] ?? '',
                'sha' => $change['sha'] ?? '',
            ];
        }, $changes);
    }

    /**
     * Set a custom provider for testing or override.
     */
    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Set a custom model for testing or override.
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }
}
