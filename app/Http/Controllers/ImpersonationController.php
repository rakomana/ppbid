<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ImpersonationController extends Controller
{
    /**
     * Admin starts viewing the app as another user.
     */
    public function start(User $user): RedirectResponse
    {
        $admin = auth()->user();

        try {
            Impersonation::start($admin, $user);
        } catch (ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('dashboard');
    }

    /**
     * Return to the admin account.
     */
    public function stop(): RedirectResponse
    {
        abort_unless(Impersonation::isActive(), 404);

        $user = auth()->user();

        if (! Impersonation::stop()) {
            return redirect()->route('login');
        }

        return redirect()->route('admin.users.index')
            ->with('status', "You're back in your admin account (you were viewing as {$user->name}).");
    }
}
