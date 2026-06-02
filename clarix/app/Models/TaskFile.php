<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskFile extends Model
{
    protected $fillable = [
        'task_id',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
        'uploaded_by',
        'is_completed_file',
    ];

    protected function casts(): array
    {
        return [
            'is_completed_file' => 'boolean',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeRegular(Builder $query): Builder
    {
        return $query->where('is_completed_file', false);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('is_completed_file', true);
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
