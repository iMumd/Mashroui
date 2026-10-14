<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** قالب دائم لنافذة إرسال إعلان أو ملف أو تكليف: عنوان + نص يُعبّآن بضغطة */
#[Fillable(['kind', 'title', 'body', 'created_by'])]
class ComposeTemplate extends Model
{
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ComposeTemplateFile::class);
    }
}
