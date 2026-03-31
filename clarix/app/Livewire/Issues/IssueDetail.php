<?php

namespace App\Livewire\Issues;

use App\Models\Issue;
use App\Models\User;
use App\Notifications\IssueResolvedNotification;
use Livewire\Component;

class IssueDetail extends Component
{
    public Issue $issue;
    public string $replyMessage = '';

    public function mount(Issue $issue): void
    {
        $user = auth()->user();

        // Security: non-admin can only view their own issues
        if (!$user->isAdmin() && $issue->created_by !== $user->id) {
            abort(403);
        }

        $this->issue = $issue->load(['creator', 'replies.author']);
    }

    public function submitReply(): void
    {
        $this->validate([
            'replyMessage' => 'required|string|max:5000',
        ]);

        $this->issue->replies()->create([
            'message'    => $this->replyMessage,
            'created_by' => auth()->id(),
        ]);

        $this->replyMessage = '';
        $this->issue->refresh()->load('replies.author');
        $this->dispatch('notify', message: 'Reply added.', type: 'success');
    }

    public function updateStatus(string $status): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $allowed = ['open', 'in_review', 'resolved', 'closed'];
        abort_unless(in_array($status, $allowed), 422);

        $this->issue->update(['status' => $status]);
        $this->issue->refresh();

        // Notify issue creator when resolved
        if ($status === 'resolved' && $this->issue->creator) {
            $this->issue->creator->notify(new IssueResolvedNotification($this->issue));
        }

        $this->dispatch('notification-updated');
        $this->dispatch('notify', message: 'Status updated.', type: 'success');
    }

    public function render()
    {
        return view('livewire.issues.issue-detail')
            ->layout('layouts.app', ['pageTitle' => 'Issue Detail']);
    }
}
