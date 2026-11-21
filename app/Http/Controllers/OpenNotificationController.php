<?php

namespace App\Http\Controllers;

use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

class OpenNotificationController extends Controller
{
    /**
     * Mark one of the user's notifications as read and go to its page (usually the listing).
     */
    public function __invoke(string $id): RedirectResponse
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        // An admin viewing as this user shouldn't change what the user has read.
        if (! Impersonation::isActive()) {
            $notification->markAsRead();
        }

        // Some notifications open a page of their own, e.g. admin returns.
        $route = $notification->data['route'] ?? null;
        if ($route && Route::has($route)) {
            return redirect()->route($route);
        }

        $listingId = $notification->data['listing_id'] ?? null;

        return $listingId
            ? redirect()->route('listings.show', $listingId)
            : redirect()->route('notifications');
    }
}
