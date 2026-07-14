<?php

namespace App\Models;

use App\Enums\DiscussionStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'supervisor_id',
    'place',
    'discussion_date',
    'discussion_time',
    'committee',
    'whatsapp',
    'status',
    'term_id',
])]
class Discussion extends Model
{
    protected function casts(): array
    {
        return [
            'discussion_date' => 'date',
            'status' => DiscussionStatusEnum::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }
}
