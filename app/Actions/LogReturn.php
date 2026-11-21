<?php

namespace App\Actions;

use App\Models\Listing;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Notifications\ReturnLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The winner logs a return for faulty units from a lot they won.
 */
class LogReturn
{
    public function handle(User $user, Listing $listing, int $quantity, string $reason, ?string $details): ReturnRequest
    {
        $return = DB::transaction(function () use ($user, $listing, $quantity, $reason, $details) {
            // Lock the listing so two returns logged at once can't go over the lot.
            $listing = Listing::query()->lockForUpdate()->findOrFail($listing->id);

            if ($listing->winner_id !== $user->id) {
                throw ValidationException::withMessages(['quantity' => 'Only the winner of this auction can log a return.']);
            }

            if (! $listing->acceptsReturns()) {
                throw ValidationException::withMessages(['quantity' => 'The return window for this auction has closed.']);
            }

            if (! array_key_exists($reason, ReturnRequest::REASONS)) {
                throw ValidationException::withMessages(['reason' => 'Choose a reason.']);
            }

            $available = $listing->returnableQuantity();

            if ($quantity < 1 || $quantity > $available) {
                throw ValidationException::withMessages(['quantity' => $available > 0
                    ? "You can return between 1 and {$available} ".str('unit')->plural($available).'.'
                    : 'Every unit of this lot is already in an open return.']);
            }

            $return = new ReturnRequest([
                'quantity_requested' => $quantity,
                'reason' => $reason,
                'details' => filled($details) ? trim($details) : null,
            ]);
            $return->listing()->associate($listing);
            $return->user()->associate($user);
            $return->status = ReturnRequest::STATUS_REQUESTED;
            $return->save();

            return $return;
        });

        Notification::send(
            User::query()->where('is_admin', true)->whereNull('deactivated_at')->get(),
            new ReturnLogged($return->load('listing', 'user')),
        );

        return $return;
    }
}
