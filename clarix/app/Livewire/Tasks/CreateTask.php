<?php

namespace App\Livewire\Tasks;

use App\Models\User;
use App\Services\TaskCreationService;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateTask extends Component
{
    use WithFileUploads;

    public string $title = '';
    public string $task_code = '';
    public string $task_type = '';
    public string $important_notes = '';
    public string $unit_id = '';
    public string $pm_id = '';
    public string $priority = 'medium';
    public string $deadline = '';
    public string $credit_amount = '0';
    public string $assigned_admin_id = '';
    public array  $uploads = [];

    public function mount(): void
    {
        $this->authorizeCreate();

        $this->unit_id = (string) auth()->user()->unit_id;
        $this->pm_id   = (string) auth()->id();
    }

    /**
     * Two separate conditions, and both have to hold.
     *
     * The permission is the agency's decision, taken in the Authorization
     * panel. The unit is structural: this screen files a task under the
     * actor's own unit, so somebody with no unit has nothing to file it
     * against and would otherwise reach a null unit_id and a database error
     * rather than a refusal.
     *
     * Called from save() as well as mount() because Livewire runs mount() only
     * on the initial render — every later action arrives on a hydrated
     * component that never passes through it again.
     */
    protected function authorizeCreate(): void
    {
        $user = auth()->user();

        abort_unless($user->hasPermission('tasks.create'), 403);
        abort_unless($user->unit_id !== null, 403);
    }

    public function removeFile(int $index): void
    {
        array_splice($this->uploads, $index, 1);
    }

    public function save(): void
    {
        $this->authorizeCreate();

        $actor = auth()->user();

        // The task's own rules come from the service, so this screen and the
        // API endpoint cannot drift apart. The upload rules stay here: they
        // describe how *this* caller received its files, and the endpoint
        // receives none.
        $this->validate(TaskCreationService::rules((int) $actor->unit_id) + [
            'uploads'   => ['nullable', 'array'],
            'uploads.*' => ['file', 'max:51200'],
        ]);

        $task = app(TaskCreationService::class)->create([
            'title'             => $this->title,
            'task_code'         => $this->task_code,
            'task_type'         => $this->task_type,
            'important_notes'   => $this->important_notes,
            'assigned_admin_id' => $this->assigned_admin_id,
            'priority'          => $this->priority,
            'deadline'          => $this->deadline,
            'credit_amount'     => $this->credit_amount,
        ], $actor, $this->uploads);

        $this->redirectRoute('tasks.show', $task);
    }

    public function render()
    {
        $adminUsers = User::where('role', 'admin')->orderBy('name')->get();

        return view('livewire.tasks.create-task', compact('adminUsers'))
            ->layout('layouts.app', ['pageTitle' => 'New Task']);
    }
}
