<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** نموذج تقييم قابل للتعديل: أقسام (مشروع/طالب) وبنود بدرجات قصوى، وطريقة تقييم، ووزن في الدرجة النهائية */
#[Fillable(['key', 'kind', 'degree', 'title', 'method', 'weight', 'allow_nomination', 'sections', 'updated_by'])]
class EvaluationForm extends Model
{
    protected function casts(): array
    {
        return ['sections' => 'array', 'allow_nomination' => 'boolean', 'weight' => 'integer'];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(EvaluationSubmission::class, 'form_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** الدرجة القصوى لطالب واحد = بنود المشروع + بنود الطالب */
    public function maxPerStudent(): int
    {
        return collect($this->sections)->sum(fn ($s) => collect($s['items'])->sum('max'));
    }
}
