<?php

namespace App\Livewire\AI;

use App\Livewire\Traits\RequiresPlan;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Livewire\Component;

/**
 * Deadline calendar.
 *
 * Tasks carry a `deadline` date with no time component, so neither view uses
 * hour rows: the week is seven day columns and the month is a day grid, and in
 * both a day simply lists the chips due on it.
 *
 * Visibility is enforced in the query, never in the view. scopedQuery() is the
 * single place role filtering lives, and it is reused for the selected task
 * lookup too — select() takes an id from the browser, so resolving it through
 * anything wider would let a writer read a task they cannot see.
 */
class Calendar extends Component
{
    use RequiresPlan;

    /** 'week' or 'month'. */
    public string $view = 'week';

    /** Any date inside the period on screen. */
    public string $cursor = '';

    public ?int $selectedId = null;

    public function mount(): void
    {
        $this->cursor = now()->toDateString();
    }

    private function anchor(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->cursor);
    }

    public function setView(string $view): void
    {
        if (in_array($view, ['week', 'month'], true)) {
            $this->view = $view;
        }
    }

    public function goToday(): void
    {
        $this->cursor = now()->toDateString();
    }

    public function step(int $direction): void
    {
        $this->cursor = $this->view === 'week'
            ? $this->anchor()->addWeeks($direction)->toDateString()
            : $this->anchor()->addMonths($direction)->startOfMonth()->toDateString();
    }

    public function select(int $taskId): void
    {
        $this->selectedId = $taskId;
    }

    public function clearSelection(): void
    {
        $this->selectedId = null;
    }

    /**
     * Every task the signed-in user is allowed to see. Mirrors the filtering
     * ManageTasks applies, minus its "hide completed" clause — a deadline
     * calendar is more useful with finished work still on it.
     */
    private function scopedQuery()
    {
        $user = auth()->user();

        return Task::query()
            ->when($user->isPm(), fn ($q) => $q->where('unit_id', $user->unit_id))
            ->when($user->isWriter(), fn ($q) => $q->whereHas(
                'assignments',
                fn ($a) => $a->where('writer_id', $user->id)
            ));
    }

    /** First and last day the grid draws, inclusive. */
    private function range(): array
    {
        $anchor = $this->anchor();

        return $this->view === 'week'
            ? [$anchor->startOfWeek(), $anchor->endOfWeek()]
            : [$anchor->startOfMonth()->startOfWeek(), $anchor->endOfMonth()->endOfWeek()];
    }

    /**
     * Which colour a chip takes. Order matters: a finished task is never
     * "overdue", and an overdue one outranks its priority.
     */
    public static function tone(Task $task): string
    {
        if ($task->status === 'completed') {
            return 'completed';
        }

        if ($task->status === 'cancelled') {
            return 'cancelled';
        }

        if ($task->deadline->isBefore(now()->startOfDay())) {
            return 'overdue';
        }

        return $task->priority === 'high' ? 'high' : 'normal';
    }

    public function render()
    {
        // The plan layer first, the permission layer below it.
        $this->assertPlanIncludes('calendar');

        $user = auth()->user();
        abort_unless($user->hasPermission('tasks.view'), 403);

        [$start, $end] = $this->range();

        // Priority ordering is done in PHP, not with a FIELD() expression:
        // tests run on sqlite, which has no FIELD(). One period's worth of
        // tasks is a small set, so sorting in memory costs nothing.
        $rank = ['high' => 0, 'medium' => 1, 'low' => 2];

        $tasks = $this->scopedQuery()
            ->with('unit')
            ->whereBetween('deadline', [$start->toDateString(), $end->toDateString()])
            ->orderBy('deadline')
            ->get()
            ->sortBy(fn (Task $t) => [$t->deadline->toDateString(), $rank[$t->priority] ?? 3])
            ->groupBy(fn (Task $t) => $t->deadline->toDateString());

        // Build the day cells once so both views iterate the same structure.
        $days = [];
        for ($d = $start; $d->lessThanOrEqualTo($end); $d = $d->addDay()) {
            $key = $d->toDateString();
            $days[] = [
                'date'    => $d,
                'key'     => $key,
                'tasks'   => $tasks->get($key, collect()),
                'today'   => $d->isSameDay(now()),
                'muted'   => $this->view === 'month' && $d->month !== $this->anchor()->month,
            ];
        }

        // Resolved through the same scope, so a forged id resolves to nothing.
        $selected = $this->selectedId
            ? $this->scopedQuery()
                ->with(['unit', 'writers', 'pm'])
                ->withCount('files')
                ->find($this->selectedId)
            : null;

        return view('livewire.ai.calendar', [
            'days'     => $days,
            'selected' => $selected,
            'label'    => $this->view === 'week'
                ? ($start->format('M j') . ' – ' . $end->format('M j, Y'))
                : $this->anchor()->format('F Y'),
            'total'    => $tasks->flatten()->count(),
        ])->layout('layouts.app', ['pageTitle' => 'Calendar']);
    }
}
