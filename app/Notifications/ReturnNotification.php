<?php

namespace App\Notifications;

use App\Models\ReturnRequest;

/**
 * Base for return notifications: in-app only, like every ppbid notification.
 */
abstract class ReturnNotification extends AuctionNotification
{
    public function __construct(public ReturnRequest $return)
    {
        parent::__construct($return->listing);
    }

    /**
     * Route name the notification opens. Null opens the listing.
     */
    protected function route(): ?string
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            ...parent::toArray($notifiable),
            'return_id' => $this->return->id,
            'route' => $this->route(),
        ];
    }
}
