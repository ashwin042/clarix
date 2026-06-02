<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CompletedFileUploadedNotification extends Notification
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
            'title'      => 'Completed File Uploaded',
            'message'    => "A completed file has been uploaded for {$this->task->task_code} and is awaiting your review.",
            'related_id' => $this->task->id,
            'type'       => 'completed_file_uploaded',
        ];
    }
}
