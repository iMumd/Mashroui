<?php

namespace App\Models;

use App\Enums\TaskStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['team_id', 'title', 'description', 'status', 'created_by'])]
class Task extends Model
{
    protected function casts(): array
    {
        return [
            'status' => TaskStatusEnum::class,
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(TaskFile::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TaskNote::class);
    }
}
