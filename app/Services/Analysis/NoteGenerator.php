<?php

namespace App\Services\Analysis;

use App\Models\Setting;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;

class NoteGenerator
{
    public const STYLE_TECHNICAL = 'technical';

    public const STYLE_USER_FRIENDLY = 'user_friendly';

    public const STYLE_MARKETING = 'marketing';

    protected string $provider;

    protected string $model;

    protected string $style;

    public function __construct()
    {
        $this->provider = Setting::get('default_ai_provider')
            ?? config('laraledger.ai.generation.provider', 'anthropic');

        $this->model = Setting::get('ai_generation_model')
            ?? config('laraledger.ai.generation.model', 'claude-sonnet-4-20250514');

        $this->style = config('laraledger.analysis.default_note_style', self::STYLE_TECHNICAL);
    }

    /**
     * Generate release notes from analysis results.
     *
     * @param  string  $version  The version being released (e.g., "v2.1.0")
     * @param  string  $versionType  MAJOR, MINOR, or PATCH
     * @param  array  $changes  Categorized changes from analysis
     * @param  string|null  $style  Note style: technical, user_friendly, or marketing
     * @return string Generated markdown release notes
     */
    public function generate(string $version, string $versionType, array $changes, ?string $style = null): string
    {
        $style = $style ?? $this->style;
        $prompt = $this->buildGenerationPrompt($version, $versionType, $changes, $style);

        try {
            $response = Prism::text()
                ->using($this->getProvider(), $this->model)
                ->withSystemPrompt($this->getSystemPrompt($style))
                ->withPrompt($prompt)
                ->asText();

            return $this->formatResponse($response->text, $version, $versionType, $changes);
        } catch (\Exception $e) {
            // Fall back to template-based generation
            return $this->generateFallback($version, $versionType, $changes);
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
     * Get the system prompt based on style.
     */
    protected function getSystemPrompt(string $style): string
    {
        $basePrompt = "You are a technical writer creating release notes for a software project.\n";
        $basePrompt .= "Your task is to write clear, informative release notes in Markdown format.\n\n";
        $basePrompt .= "Always structure the notes with these sections (if applicable):\n";
        $basePrompt .= "- A brief summary paragraph\n";
        $basePrompt .= "- Breaking Changes (if any) - most important, list first\n";
        $basePrompt .= "- New Features (if any)\n";
        $basePrompt .= "- Bug Fixes (if any)\n";
        $basePrompt .= "- Other Changes (if any)\n\n";
        $basePrompt .= "Use bullet points for individual changes.\n";
        $basePrompt .= "Include commit SHAs in parentheses when provided.\n";

        $styleInstructions = $this->getStyleInstructions($style);

        return $basePrompt.$styleInstructions;
    }

    /**
     * Get style-specific instructions for the prompt.
     */
    protected function getStyleInstructions(string $style): string
    {
        if ($style === self::STYLE_USER_FRIENDLY) {
            return "\nSTYLE: User-Friendly\n"
                ."- Use simple, non-technical language\n"
                ."- Focus on the benefits to users\n"
                ."- Avoid jargon and implementation details\n"
                ."- Use \"you\" and \"your\" to address the reader\n"
                ."- Be warm and conversational\n";
        }

        if ($style === self::STYLE_MARKETING) {
            return "\nSTYLE: Marketing\n"
                ."- Lead with the most exciting features\n"
                ."- Use enthusiastic but professional language\n"
                ."- Highlight benefits over technical details\n"
                ."- Include action-oriented language\n"
                ."- Make the release sound valuable and exciting\n";
        }

        // Default: technical
        return "\nSTYLE: Technical\n"
            ."- Be precise and detailed\n"
            ."- Include technical specifics\n"
            ."- Reference classes, methods, or APIs when relevant\n"
            ."- Use developer-focused language\n"
            ."- Be concise but comprehensive\n";
    }

    /**
     * Build the generation prompt.
     */
    protected function buildGenerationPrompt(string $version, string $versionType, array $changes, string $style): string
    {
        $prompt = "Generate release notes for version {$version} ({$versionType} release).\n\n";

        if (! empty($changes['breaking'])) {
            $prompt .= "BREAKING CHANGES:\n";
            foreach ($changes['breaking'] as $change) {
                $sha = ! empty($change['sha']) ? " ({$change['sha']})" : '';
                $desc = $change['description'] ?? $change['message'] ?? $change;
                $prompt .= "- {$desc}{$sha}\n";
            }
            $prompt .= "\n";
        }

        if (! empty($changes['features'])) {
            $prompt .= "NEW FEATURES:\n";
            foreach ($changes['features'] as $change) {
                $sha = ! empty($change['sha']) ? " ({$change['sha']})" : '';
                $desc = $change['description'] ?? $change['message'] ?? $change;
                $prompt .= "- {$desc}{$sha}\n";
            }
            $prompt .= "\n";
        }

        if (! empty($changes['fixes'])) {
            $prompt .= "BUG FIXES:\n";
            foreach ($changes['fixes'] as $change) {
                $sha = ! empty($change['sha']) ? " ({$change['sha']})" : '';
                $desc = $change['description'] ?? $change['message'] ?? $change;
                $prompt .= "- {$desc}{$sha}\n";
            }
            $prompt .= "\n";
        }

        if (! empty($changes['other'])) {
            $prompt .= "OTHER CHANGES:\n";
            foreach ($changes['other'] as $change) {
                $sha = ! empty($change['sha']) ? " ({$change['sha']})" : '';
                $desc = $change['description'] ?? $change['message'] ?? $change;
                $prompt .= "- {$desc}{$sha}\n";
            }
            $prompt .= "\n";
        }

        $prompt .= 'Generate well-formatted Markdown release notes for this release.';

        return $prompt;
    }

    /**
     * Format the AI response, adding header if missing.
     */
    protected function formatResponse(string $response, string $version, string $versionType, array $changes): string
    {
        $response = trim($response);

        // Add version header if not present
        if (! str_starts_with($response, '#')) {
            $date = now()->format('Y-m-d');
            $response = "# {$version}\n\n**Release Date:** {$date}\n\n{$response}";
        }

        return $response;
    }

    /**
     * Generate fallback release notes without AI.
     */
    public function generateFallback(string $version, string $versionType, array $changes): string
    {
        $date = now()->format('Y-m-d');
        $notes = "# {$version}\n\n";
        $notes .= "**Release Date:** {$date}\n\n";

        // Summary based on version type
        $notes .= match ($versionType) {
            'MAJOR' => "This is a major release with breaking changes. Please review the breaking changes section carefully before upgrading.\n\n",
            'MINOR' => "This release includes new features and improvements.\n\n",
            default => "This release includes bug fixes and minor improvements.\n\n",
        };

        if (! empty($changes['breaking'])) {
            $notes .= "## Breaking Changes\n\n";
            foreach ($changes['breaking'] as $change) {
                $sha = ! empty($change['sha']) ? " (`{$change['sha']}`)" : '';
                $desc = $change['description'] ?? $change['message'] ?? $change;
                $notes .= "- {$desc}{$sha}\n";
            }
            $notes .= "\n";
        }

        if (! empty($changes['features'])) {
            $notes .= "## New Features\n\n";
            foreach ($changes['features'] as $change) {
                $sha = ! empty($change['sha']) ? " (`{$change['sha']}`)" : '';
                $desc = $change['description'] ?? $change['message'] ?? $change;
                $notes .= "- {$desc}{$sha}\n";
            }
            $notes .= "\n";
        }

        if (! empty($changes['fixes'])) {
            $notes .= "## Bug Fixes\n\n";
            foreach ($changes['fixes'] as $change) {
                $sha = ! empty($change['sha']) ? " (`{$change['sha']}`)" : '';
                $desc = $change['description'] ?? $change['message'] ?? $change;
                $notes .= "- {$desc}{$sha}\n";
            }
            $notes .= "\n";
        }

        if (! empty($changes['other'])) {
            $notes .= "## Other Changes\n\n";
            foreach ($changes['other'] as $change) {
                $sha = ! empty($change['sha']) ? " (`{$change['sha']}`)" : '';
                $desc = $change['description'] ?? $change['message'] ?? $change;
                $notes .= "- {$desc}{$sha}\n";
            }
            $notes .= "\n";
        }

        // If no categorized changes
        if (empty($changes['breaking']) && empty($changes['features']) && empty($changes['fixes']) && empty($changes['other'])) {
            $notes .= "No detailed changes available for this release.\n";
        }

        return $notes;
    }

    /**
     * Set the note generation style.
     */
    public function setStyle(string $style): self
    {
        if (in_array($style, [self::STYLE_TECHNICAL, self::STYLE_USER_FRIENDLY, self::STYLE_MARKETING])) {
            $this->style = $style;
        }

        return $this;
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
