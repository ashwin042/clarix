<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CreditQueryBuilder
{
    public function build(
        User $user,
        string $dateFrom = '',
        string $dateTo = '',
        string $filterUnit = '',
        string $filterPm = '',
        string $filterTaskType = ''
    ): Builder {
        $query = Task::with(['unit', 'creator', 'assignedAdmin', 'writers'])
            ->where('status', 'completed');

        if ($user->isWriter()) {
            $query->whereHas('assignments', fn ($q) => $q->where('writer_id', $user->id));
        }

        if ($user->isPm()) {
            $query->where('unit_id', $user->unit_id);
        }

        if ($user->isAdmin() && $filterUnit) {
            $query->where('unit_id', $filterUnit);
        }

        if ($user->isAdmin() && $filterPm) {
            $query->where('created_by', $filterPm);
        }

        if ($filterTaskType) {
            $query->where('task_type', $filterTaskType);
        }

        if ($dateFrom) {
            $query->whereDate('completed_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('completed_at', '<=', $dateTo);
        }

        return $query;
    }
}
