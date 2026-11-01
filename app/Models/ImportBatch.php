<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'term_id',
    'kind',
    'original_filename',
    'uploaded_by',
    'summary',
    'reverse',
    'status',
    'applied_at',
    'reverted_at',
])]
class ImportBatch extends Model
{
    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'reverse' => 'array',
            'applied_at' => 'datetime',
            'reverted_at' => 'datetime',
        ];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
