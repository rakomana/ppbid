<?php

namespace App\Livewire;

use App\Support\Impersonation;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Notifications')]
class Notifications extends Component
{
    use WithPagination;

    public function markAllRead(): void
    {
        if (Impersonation::isActive()) {
            return; // an admin viewing as this user shouldn't change what they've read
        }

        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render()
    {
        return view('livewire.notifications.index', [
            'notifications' => auth()->user()->notifications()->latest()->paginate(20),
            'unread' => auth()->user()->unreadNotifications()->count(),
        ]);
    }
}
