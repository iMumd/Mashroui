<?php

namespace App\Models;

use App\Enums\TaskStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['team_id', 'term_id', 'title', 'description', 'status', 'due_date', 'created_by'])]
class Task extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => TaskStatusEnum::class,
            'due_date' => 'date',
        ];
    }

    /** مهمة بلا فريق (لعضو لجنة أو مشرف) — صاحب الوصول إليها هو المكلّف بها */
    public function isStaffAssignee(User $user): bool
    {
        return $this->team_id === null && $this->assignees()->whereKey($user->id)->exists();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignee')->withTimestamps();
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
