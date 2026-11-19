<?php

namespace App\Livewire;

use App\Support\Impersonation;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The bell in the sidebar and mobile header: unread count, the latest notifications,
 * and a pop-up (toast) when a new one arrives. Checks for new ones every 15 seconds.
 */
class NotificationBell extends Component
{
    /** Only one bell on the page shows pop-ups, so they don't appear twice. */
    #[Locked]
    public bool $toasts = false;

    /** Where the dropdown opens: "bottom" in the sidebar, "bottom end" in the mobile header. */
    #[Locked]
    public string $align = 'start';

    /**
     * IDs of unread notifications already seen by this bell, so only new ones pop up.
     *
     * @var list<string>
     */
    #[Locked]
    public array $seen = [];

    public function mount(): void
    {
        $this->seen = $this->latestUnreadIds();
    }

    /**
     * Runs every 15 seconds (wire:poll). Re-rendering refreshes the count and list;
     * this also pops up any notification that arrived since the last check.
     */
    public function poll(): void
    {
        $current = $this->latestUnreadIds();

        if ($this->toasts) {
            $new = array_values(array_diff($current, $this->seen));

            auth()->user()->unreadNotifications()
                ->whereIn('id', array_slice($new, 0, 3))
                ->get()
                ->each(fn (DatabaseNotification $notification) => $this->dispatch('ppbid-toast',
                    kind: $notification->data['kind'] ?? null,
                    title: $notification->data['title'] ?? '',
                    message: $notification->data['message'] ?? '',
                    url: route('notifications.open', $notification->id),
                ));
        }

        $this->seen = $current;
    }

    public function markAllRead(): void
    {
        if (Impersonation::isActive()) {
            return; // an admin viewing as this user shouldn't change what they've read
        }

        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * @return list<string>
     */
    private function latestUnreadIds(): array
    {
        return auth()->user()->unreadNotifications()->latest()->limit(20)->pluck('id')->all();
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.notifications.bell', [
            'unread' => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()->latest()->limit(6)->get(),
        ]);
    }
}
