<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An attachment as an integration sees it.
 *
 * file_path is deliberately absent. It is the object's key inside the bucket,
 * which is storage layout rather than anything a caller needs, and publishing
 * it would harden a path the backfill command has already had to change once.
 */
class TaskFileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'task_id'       => $this->task_id,
            'original_name' => $this->original_name,
            'file_size'     => (int) $this->file_size,
            'mime_type'     => $this->mime_type,
            'uploaded_by'   => $this->uploaded_by,
            'created_at'    => $this->created_at?->toIso8601String(),
        ];
    }
}
