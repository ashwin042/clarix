<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The task as an integration sees it.
 *
 * Written out field by field rather than returned as the model, so that
 * adding a column to the tasks table does not silently widen what the API
 * publishes. organization_id in particular is deliberately absent: it is
 * internal tenancy bookkeeping, and a caller already knows which agency it
 * authenticated as.
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'task_code'         => $this->task_code,
            'title'             => $this->title,
            'task_type'         => $this->task_type,
            'important_notes'   => $this->important_notes,
            'unit_id'           => $this->unit_id,
            'pm_id'             => $this->pm_id,
            'assigned_admin_id' => $this->assigned_admin_id,
            'priority'          => $this->priority,
            'status'            => $this->status,
            'deadline'          => $this->deadline?->toDateString(),
            'credit_amount'     => $this->credit_amount,
            'created_at'        => $this->created_at?->toIso8601String(),
        ];
    }
}
