<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskStatusUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Task $task) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'Task Status Updated',
            'message' => "Task status updated: {$this->task->task_code}",
            'related_id' => $this->task->id,
            'type' => 'task_status_updated',
        ];
    }
}
