<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Sign out a user whose account was deactivated while they were logged in.
     *
     * Runs on every web request, including Livewire updates and polling;
     * Livewire follows the redirect to the login page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isDeactivated()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', 'Your account has been deactivated. Ask an administrator for help.');
        }

        return $next($request);
    }
}
