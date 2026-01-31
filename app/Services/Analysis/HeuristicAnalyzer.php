<?php

namespace App\Services\Analysis;

use App\Models\Release;

class HeuristicAnalyzer
{
    /**
     * File path patterns and their weights for version impact.
     * Higher weight = more likely to indicate breaking/major changes.
     */
    protected array $pathWeights = [
        // API and public interfaces - high impact
        'routes/api' => 1.0,
        'app/Http/Controllers/Api' => 1.0,
        'app/Http/Resources' => 0.8,
        'app/Http/Requests' => 0.7,

        // Core application logic - medium-high impact
        'app/Models' => 0.8,
        'app/Services' => 0.7,
        'app/Actions' => 0.7,
        'app/Contracts' => 0.9,
        'app/Interfaces' => 0.9,

        // Database changes - high impact
        'database/migrations' => 0.9,
        'database/schema' => 0.9,

        // Configuration - medium impact
        'config/' => 0.6,

        // Documentation - low impact
        'docs/' => 0.1,
        'README' => 0.1,
        'CHANGELOG' => 0.05,

        // Tests - low impact for versioning
        'tests/' => 0.2,
        'phpunit' => 0.1,

        // Development tooling - minimal impact
        '.github/' => 0.05,
        '.gitignore' => 0.0,
        'composer.lock' => 0.1,
        'package-lock.json' => 0.1,
    ];

    /**
     * Conventional commit prefixes and their version impact.
     */
    protected array $commitPrefixes = [
        // Breaking changes
        'feat!' => ['type' => Release::TYPE_MAJOR, 'category' => 'breaking', 'weight' => 1.0],
        'fix!' => ['type' => Release::TYPE_MAJOR, 'category' => 'breaking', 'weight' => 1.0],
        'refactor!' => ['type' => Release::TYPE_MAJOR, 'category' => 'breaking', 'weight' => 1.0],
        'perf!' => ['type' => Release::TYPE_MAJOR, 'category' => 'breaking', 'weight' => 1.0],
        'chore!' => ['type' => Release::TYPE_MAJOR, 'category' => 'breaking', 'weight' => 1.0],

        // Features
        'feat' => ['type' => Release::TYPE_MINOR, 'category' => 'features', 'weight' => 0.8],
        'feature' => ['type' => Release::TYPE_MINOR, 'category' => 'features', 'weight' => 0.8],

        // Fixes
        'fix' => ['type' => Release::TYPE_PATCH, 'category' => 'fixes', 'weight' => 0.8],
        'bugfix' => ['type' => Release::TYPE_PATCH, 'category' => 'fixes', 'weight' => 0.8],
        'hotfix' => ['type' => Release::TYPE_PATCH, 'category' => 'fixes', 'weight' => 0.9],

        // Other changes
        'docs' => ['type' => Release::TYPE_PATCH, 'category' => 'other', 'weight' => 0.3],
        'style' => ['type' => Release::TYPE_PATCH, 'category' => 'other', 'weight' => 0.2],
        'refactor' => ['type' => Release::TYPE_PATCH, 'category' => 'other', 'weight' => 0.5],
        'perf' => ['type' => Release::TYPE_PATCH, 'category' => 'features', 'weight' => 0.6],
        'test' => ['type' => Release::TYPE_PATCH, 'category' => 'other', 'weight' => 0.2],
        'tests' => ['type' => Release::TYPE_PATCH, 'category' => 'other', 'weight' => 0.2],
        'chore' => ['type' => Release::TYPE_PATCH, 'category' => 'other', 'weight' => 0.2],
        'ci' => ['type' => Release::TYPE_PATCH, 'category' => 'other', 'weight' => 0.1],
        'build' => ['type' => Release::TYPE_PATCH, 'category' => 'other', 'weight' => 0.2],
        'revert' => ['type' => Release::TYPE_PATCH, 'category' => 'fixes', 'weight' => 0.6],
    ];

    /**
     * Breaking change indicators in commit messages.
     */
    protected array $breakingIndicators = [
        'BREAKING CHANGE:',
        'BREAKING-CHANGE:',
        'BREAKING_CHANGE:',
        'breaking change:',
        'breaking:',
        'BC:',
    ];

    /**
     * PR labels that indicate version type.
     */
    protected array $labelMappings = [
        // Breaking changes
        'breaking' => ['type' => Release::TYPE_MAJOR, 'category' => 'breaking', 'weight' => 1.0],
        'breaking-change' => ['type' => Release::TYPE_MAJOR, 'category' => 'breaking', 'weight' => 1.0],
        'breaking change' => ['type' => Release::TYPE_MAJOR, 'category' => 'breaking', 'weight' => 1.0],
        'major' => ['type' => Release::TYPE_MAJOR, 'category' => 'breaking', 'weight' => 0.9],
        'semver-major' => ['type' => Release::TYPE_MAJOR, 'category' => 'breaking', 'weight' => 1.0],

        // Features
        'feature' => ['type' => Release::TYPE_MINOR, 'category' => 'features', 'weight' => 0.9],
        'enhancement' => ['type' => Release::TYPE_MINOR, 'category' => 'features', 'weight' => 0.8],
        'minor' => ['type' => Release::TYPE_MINOR, 'category' => 'features', 'weight' => 0.9],
        'semver-minor' => ['type' => Release::TYPE_MINOR, 'category' => 'features', 'weight' => 1.0],

        // Fixes
        'bug' => ['type' => Release::TYPE_PATCH, 'category' => 'fixes', 'weight' => 0.9],
        'bugfix' => ['type' => Release::TYPE_PATCH, 'category' => 'fixes', 'weight' => 0.9],
        'fix' => ['type' => Release::TYPE_PATCH, 'category' => 'fixes', 'weight' => 0.9],
        'patch' => ['type' => Release::TYPE_PATCH, 'category' => 'fixes', 'weight' => 0.9],
        'semver-patch' => ['type' => Release::TYPE_PATCH, 'category' => 'fixes', 'weight' => 1.0],

        // Other
        'documentation' => ['type' => Release::TYPE_PATCH, 'category' => 'other', 'weight' => 0.5],
        'docs' => ['type' => Release::TYPE_PATCH, 'category' => 'other', 'weight' => 0.5],
        'chore' => ['type' => Release::TYPE_PATCH, 'category' => 'other', 'weight' => 0.3],
        'maintenance' => ['type' => Release::TYPE_PATCH, 'category' => 'other', 'weight' => 0.3],
    ];

    /**
     * Analyze commits and return version recommendation.
     *
     * @param  array  $commits  Array of commit data from GitHub API
     * @param  array  $ignoredPaths  Paths to ignore during analysis
     * @return array Analysis result with type, confidence, reasoning, and categorized changes
     */
    public function analyze(array $commits, array $ignoredPaths = []): array
    {
        $signals = [];
        $changes = [
            'breaking' => [],
            'features' => [],
            'fixes' => [],
            'other' => [],
        ];
        $allFiles = [];

        foreach ($commits as $commit) {
            $commitSignals = $this->analyzeCommit($commit, $ignoredPaths);
            $signals = array_merge($signals, $commitSignals['signals']);

            foreach ($commitSignals['changes'] as $category => $categoryChanges) {
                $changes[$category] = array_merge($changes[$category], $categoryChanges);
            }

            // Collect all files for aggregate analysis
            $files = $commit['files'] ?? [];
            foreach ($files as $file) {
                $filename = is_array($file) ? ($file['filename'] ?? $file['path'] ?? '') : $file;
                if ($filename && ! $this->isIgnoredPath($filename, $ignoredPaths)) {
                    $allFiles[] = $filename;
                }
            }
        }

        // If no conventional commit signals found, use file-based analysis as primary
        $hasConventionalSignals = collect($signals)->contains(fn ($s) => $s['source'] === 'conventional_commit');

        if (! $hasConventionalSignals && ! empty($allFiles)) {
            $fileBasedSignal = $this->analyzeAllFiles($allFiles, $commits);
            if ($fileBasedSignal) {
                $signals[] = $fileBasedSignal;

                // Add to appropriate change category
                $category = $fileBasedSignal['category'];
                foreach ($commits as $commit) {
                    $sha = $commit['sha'] ?? '';
                    $message = $commit['commit']['message'] ?? $commit['message'] ?? '';
                    if (! $this->hasChangeFromSha($changes[$category], $sha)) {
                        $changes[$category][] = [
                            'sha' => $sha,
                            'message' => $this->getCommitSummary($message),
                            'source' => 'file_analysis',
                        ];
                    }
                }
            }
        }

        // Calculate recommended version type and confidence
        $recommendation = $this->calculateRecommendation($signals);

        return [
            'recommended_type' => $recommendation['type'],
            'confidence' => $recommendation['confidence'],
            'reasoning' => $this->buildReasoning($signals, $recommendation, $allFiles),
            'changes' => $changes,
            'signals' => $signals,
            'source' => Release::SOURCE_HEURISTIC,
        ];
    }

    /**
     * Analyze all files to determine version type when no conventional commits found.
     */
    protected function analyzeAllFiles(array $files, array $commits): ?array
    {
        $categories = [
            'docs_only' => true,
            'tests_only' => true,
            'has_migrations' => false,
            'has_api_changes' => false,
            'has_model_changes' => false,
            'has_config_changes' => false,
        ];

        $docPatterns = ['README', 'CHANGELOG', 'docs/', '.md', 'LICENSE'];
        $testPatterns = ['tests/', 'test/', 'phpunit', '.test.', '.spec.'];
        $migrationPatterns = ['database/migrations', 'migrations/'];
        $apiPatterns = ['routes/api', 'Controllers/Api', 'app/Http/Resources'];
        $modelPatterns = ['app/Models/', 'Model.php'];
        $configPatterns = ['config/', '.env'];

        foreach ($files as $file) {
            $isDoc = false;
            $isTest = false;

            foreach ($docPatterns as $pattern) {
                if (str_contains($file, $pattern)) {
                    $isDoc = true;
                    break;
                }
            }

            foreach ($testPatterns as $pattern) {
                if (str_contains($file, $pattern)) {
                    $isTest = true;
                    break;
                }
            }

            // If file is neither doc nor test, mark those as false
            if (! $isDoc) {
                $categories['docs_only'] = false;
            }
            if (! $isTest) {
                $categories['tests_only'] = false;
            }

            // Check for high-impact changes
            foreach ($migrationPatterns as $pattern) {
                if (str_contains($file, $pattern)) {
                    $categories['has_migrations'] = true;
                }
            }

            foreach ($apiPatterns as $pattern) {
                if (str_contains($file, $pattern)) {
                    $categories['has_api_changes'] = true;
                }
            }

            foreach ($modelPatterns as $pattern) {
                if (str_contains($file, $pattern)) {
                    $categories['has_model_changes'] = true;
                }
            }

            foreach ($configPatterns as $pattern) {
                if (str_contains($file, $pattern)) {
                    $categories['has_config_changes'] = true;
                }
            }
        }

        // Determine signal based on file categories
        if ($categories['docs_only']) {
            return [
                'source' => 'file_analysis',
                'type' => Release::TYPE_PATCH,
                'category' => 'other',
                'weight' => 0.9,
                'detail' => 'Documentation-only changes detected',
                'sha' => '',
            ];
        }

        if ($categories['tests_only']) {
            return [
                'source' => 'file_analysis',
                'type' => Release::TYPE_PATCH,
                'category' => 'other',
                'weight' => 0.85,
                'detail' => 'Test-only changes detected',
                'sha' => '',
            ];
        }

        if ($categories['has_migrations']) {
            return [
                'source' => 'file_analysis',
                'type' => Release::TYPE_MINOR,
                'category' => 'features',
                'weight' => 0.8,
                'detail' => 'Database migration changes detected - likely new features or schema updates',
                'sha' => '',
            ];
        }

        if ($categories['has_api_changes']) {
            return [
                'source' => 'file_analysis',
                'type' => Release::TYPE_MINOR,
                'category' => 'features',
                'weight' => 0.75,
                'detail' => 'API route or resource changes detected',
                'sha' => '',
            ];
        }

        if ($categories['has_model_changes']) {
            return [
                'source' => 'file_analysis',
                'type' => Release::TYPE_MINOR,
                'category' => 'features',
                'weight' => 0.7,
                'detail' => 'Model changes detected - likely new features',
                'sha' => '',
            ];
        }

        // Default: general code changes
        return [
            'source' => 'file_analysis',
            'type' => Release::TYPE_PATCH,
            'category' => 'fixes',
            'weight' => 0.6,
            'detail' => sprintf('General code changes across %d files', count($files)),
            'sha' => '',
        ];
    }

    /**
     * Analyze a single commit and extract signals.
     */
    protected function analyzeCommit(array $commit, array $ignoredPaths = []): array
    {
        $message = $commit['commit']['message'] ?? $commit['message'] ?? '';
        $sha = $commit['sha'] ?? '';
        $files = $commit['files'] ?? [];
        $labels = $commit['labels'] ?? [];

        $signals = [];
        $changes = [
            'breaking' => [],
            'features' => [],
            'fixes' => [],
            'other' => [],
        ];

        // Parse conventional commit prefix
        $prefixResult = $this->parseConventionalCommit($message);
        if ($prefixResult) {
            $signals[] = [
                'source' => 'conventional_commit',
                'type' => $prefixResult['type'],
                'category' => $prefixResult['category'],
                'weight' => $prefixResult['weight'],
                'detail' => "Commit prefix: {$prefixResult['prefix']}",
                'sha' => $sha,
            ];

            $changes[$prefixResult['category']][] = [
                'sha' => $sha,
                'message' => $this->getCommitSummary($message),
                'source' => 'conventional_commit',
            ];
        }

        // Check for breaking change indicators in message body
        if ($this->hasBreakingIndicator($message)) {
            $signals[] = [
                'source' => 'breaking_indicator',
                'type' => Release::TYPE_MAJOR,
                'category' => 'breaking',
                'weight' => 1.0,
                'detail' => 'Breaking change indicator found in commit message',
                'sha' => $sha,
            ];

            $changes['breaking'][] = [
                'sha' => $sha,
                'message' => $this->getCommitSummary($message),
                'source' => 'breaking_indicator',
            ];
        }

        // Analyze PR labels
        foreach ($labels as $label) {
            $labelName = is_array($label) ? ($label['name'] ?? '') : $label;
            $labelResult = $this->analyzePRLabel($labelName);
            if ($labelResult) {
                $signals[] = [
                    'source' => 'pr_label',
                    'type' => $labelResult['type'],
                    'category' => $labelResult['category'],
                    'weight' => $labelResult['weight'],
                    'detail' => "PR label: {$labelName}",
                    'sha' => $sha,
                ];

                if (! $this->hasChangeFromSha($changes[$labelResult['category']], $sha)) {
                    $changes[$labelResult['category']][] = [
                        'sha' => $sha,
                        'message' => $this->getCommitSummary($message),
                        'source' => 'pr_label',
                    ];
                }
            }
        }

        // Analyze file paths
        $fileSignals = $this->analyzeFilePaths($files, $ignoredPaths);
        if ($fileSignals['highImpactFiles'] > 0) {
            // If we have high-impact file changes and no clear commit message signal,
            // this might indicate a more significant change
            $signals[] = [
                'source' => 'file_paths',
                'type' => $fileSignals['suggestedType'],
                'category' => $fileSignals['category'],
                'weight' => $fileSignals['weight'],
                'detail' => "High-impact files modified: {$fileSignals['highImpactFiles']}",
                'sha' => $sha,
            ];
        }

        return [
            'signals' => $signals,
            'changes' => $changes,
        ];
    }

    /**
     * Parse conventional commit prefix from message.
     */
    protected function parseConventionalCommit(string $message): ?array
    {
        // Match conventional commit pattern: type(scope)!: message or type!: message or type: message
        $pattern = '/^(\w+)(?:\([^)]+\))?(!)?\s*:/';

        if (preg_match($pattern, $message, $matches)) {
            $prefix = strtolower($matches[1]);
            $isBreaking = ! empty($matches[2]);

            // Check for breaking variant first
            if ($isBreaking) {
                $breakingPrefix = $prefix.'!';
                if (isset($this->commitPrefixes[$breakingPrefix])) {
                    return array_merge($this->commitPrefixes[$breakingPrefix], ['prefix' => $breakingPrefix]);
                }

                // Default breaking change handling
                return [
                    'type' => Release::TYPE_MAJOR,
                    'category' => 'breaking',
                    'weight' => 1.0,
                    'prefix' => $breakingPrefix,
                ];
            }

            // Check regular prefix
            if (isset($this->commitPrefixes[$prefix])) {
                return array_merge($this->commitPrefixes[$prefix], ['prefix' => $prefix]);
            }
        }

        return null;
    }

    /**
     * Check if message contains breaking change indicators.
     */
    protected function hasBreakingIndicator(string $message): bool
    {
        foreach ($this->breakingIndicators as $indicator) {
            if (stripos($message, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Analyze a PR label and return its version impact.
     */
    protected function analyzePRLabel(string $label): ?array
    {
        $normalizedLabel = strtolower(trim($label));

        if (isset($this->labelMappings[$normalizedLabel])) {
            return $this->labelMappings[$normalizedLabel];
        }

        // Check for partial matches
        foreach ($this->labelMappings as $key => $value) {
            if (str_contains($normalizedLabel, $key)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Analyze file paths to determine change impact.
     */
    protected function analyzeFilePaths(array $files, array $ignoredPaths = []): array
    {
        $totalWeight = 0;
        $highImpactFiles = 0;
        $maxWeight = 0;

        foreach ($files as $file) {
            $filename = is_array($file) ? ($file['filename'] ?? $file['path'] ?? '') : $file;

            // Skip ignored paths
            if ($this->isIgnoredPath($filename, $ignoredPaths)) {
                continue;
            }

            $weight = $this->getPathWeight($filename);
            $totalWeight += $weight;

            if ($weight >= 0.7) {
                $highImpactFiles++;
            }

            $maxWeight = max($maxWeight, $weight);
        }

        // Determine suggested type based on file changes
        $suggestedType = Release::TYPE_PATCH;
        $category = 'other';

        if ($maxWeight >= 0.9) {
            // High-impact changes (migrations, API routes, contracts)
            $suggestedType = Release::TYPE_MINOR;
            $category = 'features';
        }

        return [
            'totalWeight' => $totalWeight,
            'highImpactFiles' => $highImpactFiles,
            'maxWeight' => $maxWeight,
            'suggestedType' => $suggestedType,
            'category' => $category,
            'weight' => min($maxWeight * 0.5, 0.5), // File paths are supplementary signals
        ];
    }

    /**
     * Check if a path should be ignored.
     */
    protected function isIgnoredPath(string $path, array $ignoredPaths): bool
    {
        foreach ($ignoredPaths as $ignoredPath) {
            if (str_starts_with($path, $ignoredPath) || fnmatch($ignoredPath, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the weight for a file path.
     */
    protected function getPathWeight(string $path): float
    {
        foreach ($this->pathWeights as $pattern => $weight) {
            if (str_contains($path, $pattern)) {
                return $weight;
            }
        }

        return 0.4; // Default weight for unmatched paths
    }

    /**
     * Calculate the recommended version type and confidence from signals.
     */
    protected function calculateRecommendation(array $signals): array
    {
        if (empty($signals)) {
            return [
                'type' => Release::TYPE_PATCH,
                'confidence' => 50, // Low confidence with no signals
            ];
        }

        // Count weighted votes for each type
        $votes = [
            Release::TYPE_MAJOR => 0,
            Release::TYPE_MINOR => 0,
            Release::TYPE_PATCH => 0,
        ];

        $totalWeight = 0;
        $strongSignals = 0;

        foreach ($signals as $signal) {
            $type = $signal['type'];
            $weight = $signal['weight'];

            $votes[$type] += $weight;
            $totalWeight += $weight;

            if ($weight >= 0.8) {
                $strongSignals++;
            }
        }

        // Determine winning type (MAJOR takes precedence, then MINOR if any features)
        if ($votes[Release::TYPE_MAJOR] > 0) {
            $recommendedType = Release::TYPE_MAJOR;
        } elseif ($votes[Release::TYPE_MINOR] > 0) {
            // Any feature signal means MINOR
            $recommendedType = Release::TYPE_MINOR;
        } else {
            $recommendedType = Release::TYPE_PATCH;
        }

        // Calculate confidence based on signal agreement and strength
        $winningVotes = $votes[$recommendedType];
        $voteShare = $totalWeight > 0 ? ($winningVotes / $totalWeight) : 0;

        // Base confidence from vote share (0-70%)
        $confidence = (int) round($voteShare * 70);

        // Bonus for strong signals (up to 20%)
        $strongSignalBonus = min($strongSignals * 5, 20);
        $confidence += $strongSignalBonus;

        // Bonus for multiple agreeing signals (up to 10%)
        $agreementBonus = min(count($signals) * 2, 10);
        $confidence += $agreementBonus;

        // Cap at 99% (never 100% certain)
        $confidence = min($confidence, 99);

        // Minimum confidence of 40% if we have any signals
        $confidence = max($confidence, 40);

        return [
            'type' => $recommendedType,
            'confidence' => $confidence,
            'votes' => $votes,
        ];
    }

    /**
     * Build human-readable reasoning from signals.
     */
    protected function buildReasoning(array $signals, array $recommendation, array $files = []): array
    {
        $reasoning = [];

        // Group signals by source
        $conventionalSignals = array_filter($signals, fn ($s) => $s['source'] === 'conventional_commit');
        $fileAnalysisSignals = array_filter($signals, fn ($s) => $s['source'] === 'file_analysis');

        // Group signals by type
        $breakingSignals = array_filter($signals, fn ($s) => $s['type'] === Release::TYPE_MAJOR);
        $featureSignals = array_filter($signals, fn ($s) => $s['type'] === Release::TYPE_MINOR);
        $patchSignals = array_filter($signals, fn ($s) => $s['type'] === Release::TYPE_PATCH);

        if (! empty($breakingSignals)) {
            $reasoning[] = sprintf(
                'Found %d breaking change indicator(s): %s',
                count($breakingSignals),
                implode(', ', array_map(fn ($s) => $s['detail'], array_slice($breakingSignals, 0, 3)))
            );
        }

        if (! empty($featureSignals)) {
            $details = array_map(fn ($s) => $s['detail'], $featureSignals);
            $reasoning[] = sprintf(
                'Found %d feature/enhancement signal(s): %s',
                count($featureSignals),
                implode(', ', array_slice($details, 0, 2))
            );
        }

        if (! empty($patchSignals)) {
            $details = array_map(fn ($s) => $s['detail'], $patchSignals);
            $reasoning[] = sprintf(
                'Found %d fix/patch signal(s): %s',
                count($patchSignals),
                implode(', ', array_slice($details, 0, 2))
            );
        }

        // Add file analysis context
        if (! empty($fileAnalysisSignals) && empty($conventionalSignals)) {
            $reasoning[] = 'No conventional commit prefixes found - using file-based analysis.';
        }

        if (! empty($files)) {
            $reasoning[] = sprintf('Analyzed %d file(s) across all commits.', count($files));
        }

        if (empty($signals)) {
            $reasoning[] = 'No clear version signals found. Defaulting to PATCH with low confidence.';
        }

        $reasoning[] = sprintf(
            'Confidence: %d%% (based on %d signal(s))',
            $recommendation['confidence'],
            count($signals)
        );

        return $reasoning;
    }

    /**
     * Get the first line summary of a commit message.
     */
    protected function getCommitSummary(string $message): string
    {
        $firstLine = strtok($message, "\n");

        return strlen($firstLine) > 100 ? substr($firstLine, 0, 97).'...' : $firstLine;
    }

    /**
     * Check if a change with the given SHA already exists in the changes array.
     */
    protected function hasChangeFromSha(array $changes, string $sha): bool
    {
        foreach ($changes as $change) {
            if (($change['sha'] ?? '') === $sha) {
                return true;
            }
        }

        return false;
    }
}
