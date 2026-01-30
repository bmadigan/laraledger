<?php

use App\Http\Controllers\Settings\LaraLedgerSettingsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    // LaraLedger-specific settings
    Route::get('settings/laraledger', [LaraLedgerSettingsController::class, 'index'])
        ->name('laraledger.settings');
    Route::put('settings/laraledger/ai-provider', [LaraLedgerSettingsController::class, 'updateAiProvider'])
        ->name('laraledger.settings.ai-provider');
    Route::put('settings/laraledger/generation-provider', [LaraLedgerSettingsController::class, 'updateGenerationProvider'])
        ->name('laraledger.settings.generation-provider');
    Route::put('settings/laraledger/analysis-defaults', [LaraLedgerSettingsController::class, 'updateAnalysisDefaults'])
        ->name('laraledger.settings.analysis-defaults');
    Route::post('settings/laraledger/test-ai', [LaraLedgerSettingsController::class, 'testAiProvider'])
        ->name('laraledger.settings.test-ai');
    Route::get('settings/laraledger/export', [LaraLedgerSettingsController::class, 'exportData'])
        ->name('laraledger.settings.export');
    Route::delete('settings/laraledger/history', [LaraLedgerSettingsController::class, 'clearHistory'])
        ->name('laraledger.settings.clear-history');
});
