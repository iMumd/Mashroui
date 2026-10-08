<?php

namespace App\Models;

use App\Models\Scopes\TermScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'author_id',
    'kind',
    'team_id',
    'target_user_id',
    'title',
    'body',
    'rating',
    'term_id',
])]
class Announcement extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new TermScope);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
