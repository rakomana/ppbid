<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Auth\Events\Login;

class RecordLastLogin
{
    /**
     * Save the time of every real login: the login form, and "remember me" logins
     * when a returning user's session has expired.
     *
     * Not recorded: an admin switching into or back from "Log in as" (that isn't the
     * user logging in), and deactivated accounts (they're turned away at the door).
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || Impersonation::isSwitching() || $user->isDeactivated()) {
            return;
        }

        // Without touching updated_at, which tracks changes to the account itself.
        $user->timestamps = false;
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $user->timestamps = true;
    }
}
