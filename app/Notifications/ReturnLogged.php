<?php

namespace App\Notifications;

/**
 * Sent to admins when a winner logs a return. Admins may see the buyer's name.
 */
class ReturnLogged extends ReturnNotification
{
    protected function kind(): string
    {
        return 'return_logged';
    }

    protected function title(): string
    {
        return 'Return logged';
    }

    protected function message(): string
    {
        $user = $this->return->user;
        $units = $this->return->quantity_requested;

        return "{$user->name} (Bidder {$user->bidder_number}) wants {$units} ".str('unit')->plural($units)
            ." of “{$this->listing->title}” replaced: {$this->return->reasonLabel()}.";
    }

    protected function route(): ?string
    {
        return 'admin.returns.index';
    }
}
