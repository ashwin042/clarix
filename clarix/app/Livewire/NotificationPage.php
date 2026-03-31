<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;

class NotificationPage extends Component
{
    use WithPagination;

    public string $filter = ''; // '', 'unread', 'read'

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function markAsRead(string $id): void
    {
        auth()->user()->notifications()->findOrFail($id)->markAsRead();
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function render()
    {
        $query = auth()->user()->notifications();

        if ($this->filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($this->filter === 'read') {
            $query->whereNotNull('read_at');
        }

        return view('livewire.notification-page', [
            'notifications' => $query->latest()->paginate(15),
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
        ])->layout('layouts.app', ['pageTitle' => 'Notifications']);
    }
}
