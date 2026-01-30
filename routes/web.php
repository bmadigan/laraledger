<?php

use App\Http\Controllers\Auth\GitHubController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\SetupWizardController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

// Home route - redirect to setup if no user exists, otherwise show welcome
Route::get('/', function () {
    if (! User::exists()) {
        return redirect()->route('setup.index');
    }

    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

// Setup Wizard routes (no auth required initially)
Route::prefix('setup')->name('setup.')->group(function () {
    Route::get('/', [SetupWizardController::class, 'index'])->name('index');
    Route::get('/{step}', [SetupWizardController::class, 'show'])->name('step');
    Route::post('/account', [SetupWizardController::class, 'createAccount'])->name('account.store');
    Route::get('/github/redirect', [SetupWizardController::class, 'redirectToGitHub'])->name('github.redirect')->middleware('auth');
    Route::get('/github/callback', [SetupWizardController::class, 'handleGitHubCallback'])->name('github.callback')->middleware('auth');
    Route::post('/ai-provider', [SetupWizardController::class, 'saveAiProvider'])->name('ai-provider.store')->middleware('auth');
    Route::post('/ai-provider/test', [SetupWizardController::class, 'testAiProvider'])->name('ai-provider.test')->middleware('auth');
    Route::post('/repository', [SetupWizardController::class, 'saveRepository'])->name('repository.store')->middleware('auth');
    Route::post('/complete', [SetupWizardController::class, 'complete'])->name('complete')->middleware('auth');
});

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'setup.complete'])
    ->name('dashboard');

// GitHub OAuth routes
Route::middleware('auth')->group(function () {
    Route::get('auth/github', [GitHubController::class, 'redirect'])->name('github.redirect');
    Route::get('auth/github/callback', [GitHubController::class, 'callback'])->name('github.callback');
    Route::delete('auth/github', [GitHubController::class, 'disconnect'])->name('github.disconnect');
});

// Repository management routes
Route::middleware(['auth', 'verified', 'setup.complete'])->group(function () {
    Route::resource('repositories', RepositoryController::class);
    Route::post('repositories/{repository}/sync', [RepositoryController::class, 'sync'])->name('repositories.sync');

    // Global release history
    Route::get('releases', [App\Http\Controllers\ReleaseController::class, 'index'])->name('releases.index');
    Route::get('releases/export', [App\Http\Controllers\ReleaseController::class, 'export'])->name('releases.export');

    // Analytics routes
    Route::get('analytics', [App\Http\Controllers\AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('analytics/missed-predictions', [App\Http\Controllers\AnalyticsController::class, 'missedPredictions'])->name('analytics.missed-predictions');

    // Analysis routes
    Route::get('repositories/{repository}/analyze', [App\Http\Controllers\AnalysisController::class, 'create'])->name('analysis.create');
    Route::post('repositories/{repository}/analyze/preview', [App\Http\Controllers\AnalysisController::class, 'preview'])->name('analysis.preview');
    Route::post('repositories/{repository}/analyze', [App\Http\Controllers\AnalysisController::class, 'store'])->name('analysis.store');

    // Release routes
    Route::get('repositories/{repository}/releases/{release}', [App\Http\Controllers\AnalysisController::class, 'show'])->name('releases.show');
    Route::post('repositories/{repository}/releases/{release}/accept', [App\Http\Controllers\AnalysisController::class, 'accept'])->name('releases.accept');
    Route::post('repositories/{repository}/releases/{release}/adjust', [App\Http\Controllers\AnalysisController::class, 'adjust'])->name('releases.adjust');
    Route::post('repositories/{repository}/releases/{release}/reject', [App\Http\Controllers\AnalysisController::class, 'reject'])->name('releases.reject');
    Route::post('repositories/{repository}/releases/{release}/regenerate-notes', [App\Http\Controllers\AnalysisController::class, 'regenerateNotes'])->name('releases.regenerate-notes');
});

require __DIR__.'/settings.php';
