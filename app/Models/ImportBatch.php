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

    /**
     * يسجّل دفعة استيراد مطبَّقة ويتجاوز الدفعة السابقة من نفس النوع والفصل
     * (لا تبقى إلا آخر دفعة قابلة للتراجع — رجوع خطوة واحدة).
     */
    public static function record(string $kind, ?int $termId, ?int $uploadedBy, ?string $filename, array $summary, array $reverse): self
    {
        self::where('kind', $kind)
            ->where('term_id', $termId)
            ->where('status', 'applied')
            ->update(['status' => 'superseded']);

        return self::create([
            'term_id' => $termId,
            'kind' => $kind,
            'original_filename' => $filename,
            'uploaded_by' => $uploadedBy,
            'summary' => $summary,
            'reverse' => $reverse,
            'status' => 'applied',
            'applied_at' => now(),
        ]);
    }

    /** أحدث دفعة لنوع وفصل (لعرض الحالة وإتاحة زر التراجع) */
    public static function latestFor(string $kind, ?int $termId): ?self
    {
        return self::where('kind', $kind)
            ->where('term_id', $termId)
            ->latest('id')
            ->first();
    }
}
