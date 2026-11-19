<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockWhileImpersonating
{
    /**
     * Keep an admin who is viewing as another user away from that user's account settings
     * (profile, password, deleting the account). Also applied to Livewire updates.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Impersonation::isActive(), 403, "Account settings are turned off while you're logged in as another user.");

        return $next($request);
    }
}
