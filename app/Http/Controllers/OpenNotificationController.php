<?php

namespace App\Http\Controllers;

use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;

class OpenNotificationController extends Controller
{
    /**
     * Mark one of the user's notifications as read and go to its listing.
     */
    public function __invoke(string $id): RedirectResponse
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        // An admin viewing as this user shouldn't change what the user has read.
        if (! Impersonation::isActive()) {
            $notification->markAsRead();
        }

        $listingId = $notification->data['listing_id'] ?? null;

        return $listingId
            ? redirect()->route('listings.show', $listingId)
            : redirect()->route('notifications');
    }
}
