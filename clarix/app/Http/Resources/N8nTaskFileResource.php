<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An attachment as the task bot's pipeline sees it.
 *
 * Same fields as TaskFileResource and the same omission — file_path is the
 * object's key inside the bucket, which is storage layout rather than anything
 * a caller needs, and publishing it would harden a path the backfill command
 * has already had to change once.
 *
 * A separate class only because of $wrap: the pipeline reads a bare array at
 * the top level, and changing TaskFileResource to suit it would silently
 * reshape the token API's responses for every existing integration.
 *
 * @mixin \App\Models\TaskFile
 */
class N8nTaskFileResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'            => (int) $this->id,
            'task_id'       => (int) $this->task_id,
            'original_name' => $this->original_name,
            'file_size'     => (int) $this->file_size,
            'mime_type'     => $this->mime_type,
            'uploaded_by'   => $this->uploaded_by === null ? null : (int) $this->uploaded_by,
            'created_at'    => $this->created_at?->toIso8601String(),
        ];
    }
}
