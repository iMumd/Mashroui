<?php

namespace App\Models;

use App\Models\Scopes\TermScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'author_id',
    'batch',
    'kind',
    'type',
    'team_id',
    'target_user_id',
    'title',
    'body',
    'rating',
    'channels',
    'term_id',
])]
class Announcement extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new TermScope);
    }

    protected function casts(): array
    {
        return ['channels' => 'array'];
    }

    /** مرفقات المراسلة (مشتركة بين كل صفوف نفس الـbatch) */
    public function attachments(): HasMany
    {
        return $this->hasMany(AnnouncementAttachment::class, 'batch', 'batch');
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
