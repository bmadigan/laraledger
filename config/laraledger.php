<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which AI providers and models to use for classification
    | and release note generation. Users can override these via the
    | Settings UI, which stores values in the database.
    |
    */

    'ai' => [
        // Primary provider for classification tasks
        'classification' => [
            'provider' => env('LARALEDGER_CLASSIFICATION_PROVIDER', 'anthropic'),
            'model' => env('LARALEDGER_CLASSIFICATION_MODEL', 'claude-3-5-haiku-20241022'),
        ],

        // Provider for release note generation
        'generation' => [
            'provider' => env('LARALEDGER_GENERATION_PROVIDER', 'anthropic'),
            'model' => env('LARALEDGER_GENERATION_MODEL', 'claude-sonnet-4-20250514'),
        ],

        // Fallback provider if primary fails
        'fallback' => [
            'provider' => env('LARALEDGER_FALLBACK_PROVIDER', 'openai'),
            'model' => env('LARALEDGER_FALLBACK_MODEL', 'gpt-4o-mini'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Heuristic Analysis Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the heuristic analyzer that processes commits before
    | potentially invoking AI classification.
    |
    */

    'heuristics' => [
        // Confidence threshold for skipping AI classification (0-100)
        // If heuristic confidence >= threshold, AI is skipped
        'confidence_threshold' => env('LARALEDGER_HEURISTIC_THRESHOLD', 85),

        // Always use AI regardless of heuristic confidence
        'always_use_ai' => env('LARALEDGER_ALWAYS_USE_AI', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analysis Options
    |--------------------------------------------------------------------------
    |
    | Default options for release analysis.
    |
    */

    'analysis' => [
        // Default analysis depth: 'standard' or 'deep'
        // 'standard' uses commit messages + file paths
        // 'deep' includes diff analysis
        'default_depth' => env('LARALEDGER_ANALYSIS_DEPTH', 'standard'),

        // Default note style: 'technical', 'user_friendly', 'marketing'
        'default_note_style' => env('LARALEDGER_NOTE_STYLE', 'technical'),
    ],
];
