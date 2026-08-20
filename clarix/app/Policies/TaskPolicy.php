<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;

/**
 * Two questions, kept separate.
 *
 *   May this role do this at all?   -> the Authorization panel, via
 *                                      hasPermission(). Configuration an
 *                                      agency's admin controls.
 *   To which rows?                  -> the user's relationship to the task.
 *                                      Structural, and not delegable: a PM
 *                                      granted tasks.update still only reaches
 *                                      their own unit's work, and a writer
 *                                      only the tasks assigned to them.
 *
 * Previously only the second question was asked, with the role name standing
 * in for the first, so every toggle in the panel that touched tasks was
 * decorative. Granting a permission now widens what a role may do; it never
 * widens which rows they may do it to.
 */
class TaskPolicy
{
    /**
     * The tasks a user is entitled to touch at all, independent of which
     * capability is being exercised.
     */
    protected function owns(User $user, Task $task): bool
    {
        return match ($user->role) {
            'admin' => true,

            /*
             * A third shape of reach, and the reason this arm exists at all.
             *
             * A supervisor oversees the work across the agency rather than
             * inside one unit, and carries no unit_id, so folding them in with
             * the PM would have compared null against every task's unit and
             * reached nothing. Falling through to the default did the same
             * thing more quietly.
             *
             * Broad is not full. This widens which rows a supervisor may act
             * on; it says nothing about which capabilities they hold — those
             * are still the panel's, and delete() below is not among them
             * because delete() does not ask this method.
             */
            'supervisor' => (int) $task->organization_id === (int) $user->organization_id,

            'pm'     => $task->unit_id === $user->unit_id,
            'writer' => $task->assignments()->where('writer_id', $user->id)->exists(),
            default  => false,
        };
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.view') && $this->owns($user, $task);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tasks.create');
    }

    public function update(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.update') && $this->owns($user, $task);
    }

    /**
     * Structural, not configurable. There is no tasks.delete permission to
     * grant, so no toggle in the Authorization panel can reach this.
     */
    public function delete(User $user, Task $task): bool
    {
        return $user->administers($task);
    }

    /**
     * Advancing a task through the workflow, which now has a toggle of its own.
     *
     * It was isAdmin() and nothing else — the one ability here the panel could
     * not describe, so a supervisor holding tasks.update still could not move
     * a card and the screen offered no reason.
     *
     * It is deliberately *not* hung off tasks.update. Editing a task's fields
     * and deciding it has moved on are different authorities, and tasks.update
     * is on by default for the PM as well as the supervisor: reusing it would
     * have handed every PM the workflow as a side effect of a change about
     * supervisors. An agency that does want its PMs running the board grants
     * them this one, and the policy obeys.
     *
     * owns() still answers the second question, so the grant widens what a
     * role may do and never which rows they may do it to.
     */
    public function updateStatus(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.update_status') && $this->owns($user, $task);
    }

    public function uploadFiles(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.upload_files') && $this->owns($user, $task);
    }

    /**
     * The writer's own deliverable coming back the other way, plus the people
     * who oversee the work and may need to put it there themselves.
     *
     * Still a structural question rather than a granted one — the panel has no
     * toggle for it, because it is the completion half of an assignment rather
     * than a capability an agency hands out. The supervisor arm is the same
     * shape as owns()'s: agency-wide, because a supervisor carries no unit_id
     * and comparing null against a unit would have reached nothing.
     *
     * The PM is deliberately absent, as before. Overseeing a unit's work is
     * not the same as producing it.
     */
    public function uploadCompletedFile(User $user, Task $task): bool
    {
        return match ($user->role) {
            'admin'      => true,
            'supervisor' => $this->owns($user, $task),
            'writer'     => $task->assignments()->where('writer_id', $user->id)->exists(),
            default      => false,
        };
    }

    /**
     * Reading a file off a task.
     *
     * Lifted out of TaskFileController, which enumerated roles inline and so
     * refused a supervisor by falling off the end of the chain. Every rule
     * that was there is preserved: a writer reaches only what they are
     * assigned, and a PM reaches a completed file only once the task is
     * actually complete.
     */
    public function downloadFile(User $user, Task $task, TaskFile $file): bool
    {
        if ($user->isAdmin() || $user->isSupervisor()) {
            return $this->owns($user, $task);
        }

        if ($file->is_completed_file) {
            return match ($user->role) {
                'writer' => $task->assignments()->where('writer_id', $user->id)->exists(),
                'pm'     => $task->unit_id === $user->unit_id && $task->status === 'completed',
                default  => false,
            };
        }

        return match ($user->role) {
            'pm'     => $task->unit_id === $user->unit_id,
            'writer' => $task->assignments()->where('writer_id', $user->id)->exists(),
            default  => false,
        };
    }

    /**
     * Removing a file, which is a deletion like any other.
     *
     * It used to be gated on uploadFiles, so every role that could add a file
     * could destroy one — including, once the supervisor was given upload
     * rights, a role that is explicitly not allowed to delete anything. This
     * is administers(), the same answer delete() gives for the task itself and
     * UnitPolicy and UserPolicy give for theirs: admin of the owning agency,
     * and no grant reaches it.
     */
    public function deleteFile(User $user, Task $task): bool
    {
        return $user->administers($task);
    }

    public function assign(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.assign') && $this->owns($user, $task);
    }
}
