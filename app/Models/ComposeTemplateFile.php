<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** مرفق قالب دائم — يُنزَّل ويُرفق مع الإرسال عند استخدام القالب */
#[Fillable(['compose_template_id', 'name', 'path', 'size'])]
class ComposeTemplateFile extends Model
{
    protected $hidden = ['path'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ComposeTemplate::class, 'compose_template_id');
    }
}
