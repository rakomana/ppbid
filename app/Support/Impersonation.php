<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Lets an admin log in as another user to see the app exactly as they do.
 *
 * The admin's id is kept in the session, so they can switch back without logging in again.
 * While impersonating, the app is read-only for that user: no bids, no settings changes,
 * and notifications aren't marked as read. Nothing is stored in the database; starts and
 * stops are written to the Laravel log.
 */
class Impersonation
{
    public const SESSION_KEY = 'impersonator_id';

    /** True while switching users, so the switch isn't counted as the user logging in. */
    private static bool $switching = false;

    public static function isSwitching(): bool
    {
        return self::$switching;
    }

    private static function switchTo(User $user): void
    {
        self::$switching = true;

        try {
            Auth::guard('web')->login($user);
        } finally {
            self::$switching = false;
        }

        session()->regenerate();
    }

    public static function isActive(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    /** The admin who is impersonating, if any. */
    public static function impersonator(): ?User
    {
        $id = session(self::SESSION_KEY);

        return $id ? User::find($id) : null;
    }

    /**
     * @throws ValidationException
     */
    public static function start(User $admin, User $target): void
    {
        $error = match (true) {
            ! $admin->is_admin => 'Only admins can log in as another user.',
            self::isActive() => 'You are already logged in as another user. Return to your admin account first.',
            $admin->is($target) => "You can't log in as yourself.",
            (bool) $target->is_admin => "You can't log in as another admin.",
            $target->isDeactivated() => "You can't log in as a deactivated user. Reactivate them first.",
            default => null,
        };

        if ($error) {
            throw ValidationException::withMessages(['impersonate' => $error]);
        }

        self::switchTo($target);
        session()->put(self::SESSION_KEY, $admin->id);

        Log::info('Impersonation started', ['admin_id' => $admin->id, 'admin' => $admin->email, 'user_id' => $target->id, 'user' => $target->email]);
    }

    /**
     * Switch back to the admin. If that admin can no longer use the admin area
     * (removed as admin or deactivated meanwhile), log out completely instead.
     *
     * @return bool Whether the admin is logged back in.
     */
    public static function stop(): bool
    {
        $admin = self::impersonator();
        $user = Auth::user();

        session()->forget(self::SESSION_KEY);

        Log::info('Impersonation stopped', ['admin_id' => $admin?->id, 'user_id' => $user?->id]);

        if (! $admin || ! $admin->is_admin || $admin->isDeactivated()) {
            Auth::guard('web')->logout();
            session()->invalidate();
            session()->regenerateToken();

            return false;
        }

        self::switchTo($admin);

        return true;
    }
}
