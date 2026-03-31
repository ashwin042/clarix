<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
{
    public int $unreadCount = 0;

    public function mount(): void
    {
        $this->loadCount();
    }

    #[On('notification-updated')]
    public function refreshNotifications(): void
    {
        $this->loadCount();
    }

    private function loadCount(): void
    {
        $this->unreadCount = auth()->user()->unreadNotifications()->count();
    }

    public function markAsRead(string $id): void
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $this->unreadCount = auth()->user()->unreadNotifications()->count();

        $data = $notification->data;
        $type = $data['type'] ?? '';
        $relatedId = $data['related_id'] ?? null;

        $url = route('notifications');
        if (in_array($type, ['task_created', 'task_status_updated']) && $relatedId) {
            $url = route('tasks.show', $relatedId);
        } elseif (in_array($type, ['issue_created', 'issue_resolved']) && $relatedId) {
            $url = route('issues.show', $relatedId);
        }

        $this->redirect($url);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
        $this->unreadCount = 0;
    }

    public function render()
    {
        return view('livewire.notification-bell', [
            'notifications' => auth()->user()
                ->notifications()
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }
}
