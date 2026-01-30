<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    /**
     * Routes that are exempt from the setup check.
     */
    protected array $except = [
        'setup.*',
        'login',
        'logout',
        'register',
        'password.*',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for exempt routes
        foreach ($this->except as $pattern) {
            if ($request->routeIs($pattern)) {
                return $next($request);
            }
        }

        // If no user exists, redirect to setup
        if (! User::exists()) {
            return redirect()->route('setup.index');
        }

        // If user is logged in and setup is not complete, redirect to setup
        $user = Auth::user();
        if ($user && ! $this->isSetupComplete($user)) {
            return redirect()->route('setup.index');
        }

        return $next($request);
    }

    protected function isSetupComplete($user): bool
    {
        return $user->isGithubConnected() && Setting::has('setup_completed_at');
    }
}
