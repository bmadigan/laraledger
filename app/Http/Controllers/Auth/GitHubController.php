<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GitHubController extends Controller
{
    /**
     * Required OAuth scopes for LaraLedger functionality.
     * - repo: Full control of private repositories (read commits, tags, branches)
     * - read:user: Read user profile data
     */
    private const SCOPES = ['repo', 'read:user'];

    /**
     * Redirect the user to GitHub for authorization.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(self::SCOPES)
            ->redirect();
    }

    /**
     * Handle the callback from GitHub after authorization.
     */
    public function callback(): RedirectResponse
    {
        try {
            $githubUser = Socialite::driver('github')->user();
        } catch (\Exception $e) {
            return redirect()->route('settings.profile')
                ->with('error', 'Failed to connect GitHub: '.$e->getMessage());
        }

        $user = Auth::user();

        $user->update([
            'github_id' => $githubUser->getId(),
            'github_username' => $githubUser->getNickname(),
            'github_token' => encrypt($githubUser->token),
            'github_refresh_token' => $githubUser->refreshToken ? encrypt($githubUser->refreshToken) : null,
            'github_token_expires_at' => $githubUser->expiresIn
                ? now()->addSeconds($githubUser->expiresIn)
                : null,
            'github_scopes' => self::SCOPES,
        ]);

        // If user hasn't completed setup, advance to next step
        if (! $user->setup_completed && $user->setup_step < 2) {
            $user->update(['setup_step' => 2]);

            return redirect()->route('setup.ai')
                ->with('success', 'GitHub connected successfully!');
        }

        return redirect()->route('settings.profile')
            ->with('success', 'GitHub connected successfully!');
    }

    /**
     * Disconnect GitHub from the user's account.
     */
    public function disconnect(): RedirectResponse
    {
        $user = Auth::user();

        $user->update([
            'github_id' => null,
            'github_username' => null,
            'github_token' => null,
            'github_refresh_token' => null,
            'github_token_expires_at' => null,
            'github_scopes' => null,
        ]);

        return redirect()->route('settings.profile')
            ->with('success', 'GitHub disconnected successfully.');
    }
}
