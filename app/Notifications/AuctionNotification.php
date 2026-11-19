<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Notifications\Notification;

/**
 * Base class for ppbid's notifications.
 *
 * Notifications are in-app only: they're stored in the `notifications` table and shown
 * under the bell. No email (or any other channel) is ever used.
 *
 * Other users always appear as bidder numbers, never names or emails, except in
 * notifications sent to admins.
 */
abstract class AuctionNotification extends Notification
{
    public function __construct(public Listing $listing) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Short key used to pick the icon and colour, e.g. "outbid".
     */
    abstract protected function kind(): string;

    abstract protected function title(): string;

    abstract protected function message(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind(),
            'title' => $this->title(),
            'message' => $this->message(),
            'listing_id' => $this->listing->id,
            'listing_title' => $this->listing->title,
        ];
    }
}
