<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A filed task, as the pipeline needs to read it back.
 *
 * $wrap is null for the same reason N8nTelegramIdentityResource's is: n8n
 * addresses fields by path in a visual editor, and a 'data.' prefix is one more
 * thing for every node downstream to get wrong.
 *
 * Written out field by field rather than returned as the model, so that adding
 * a column to the tasks table does not silently widen what the bot publishes.
 * organization_id is deliberately absent — it is internal tenancy bookkeeping,
 * and the caller has just been told which agency it is acting for.
 *
 * 'id' is the field that matters most in practice: it is what the next node
 * puts in the path of the attach call.
 *
 * @mixin \App\Models\Task
 */
class N8nTaskResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'              => (int) $this->id,
            'task_code'       => $this->task_code,
            'title'           => $this->title,
            'task_type'       => $this->task_type,
            'important_notes' => $this->important_notes,
            'unit_id'         => $this->unit_id === null ? null : (int) $this->unit_id,
            'pm_id'           => $this->pm_id === null ? null : (int) $this->pm_id,
            'priority'        => $this->priority,
            'status'          => $this->status,
            'deadline'        => $this->deadline?->toDateString(),
            'credit_amount'   => $this->credit_amount,
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
