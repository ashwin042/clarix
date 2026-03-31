<?php

namespace App\Notifications;

use App\Models\Issue;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IssueCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Issue $issue) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'New Issue Submitted',
            'message' => "New issue submitted: {$this->issue->title}",
            'related_id' => $this->issue->id,
            'type' => 'issue_created',
        ];
    }
}
