<?php

namespace App\Livewire\Dashboard;

use App\Models\Task;
use Livewire\Component;

class CreditsCard extends Component
{
    public string $role;
    public string $period = 'total';

    public function setPeriod(string $period): void
    {
        if (!in_array($period, ['today', 'month', 'total'], true)) {
            return;
        }
        $this->period = $period;
    }

    public function render()
    {
        $query = Task::where('status', 'completed');

        if ($this->role === 'writer') {
            $query->whereHas('assignments', fn($q) => $q->where('writer_id', auth()->id()));
        }

        if ($this->period === 'today') {
            $query->whereDate('completed_at', today());
        } elseif ($this->period === 'month') {
            $query->whereMonth('completed_at', now()->month)
                  ->whereYear('completed_at', now()->year);
        }

        $credits = $query->sum('credit_amount');

        return view('livewire.dashboard.credits-card', [
            'credits' => $credits,
        ]);
    }
}
