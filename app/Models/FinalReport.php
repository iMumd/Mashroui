<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** التقرير النهائي ومسار اعتماده قبل الأرشفة: المشرف ← لجنة الإشراف (review_status) */
#[Fillable([
    'project_id', 'pdf_path', 'video_url', 'uploaded_by',
    'review_status', 'supervisor_reviewed_by', 'supervisor_reviewed_at', 'committee_reviewed_by', 'committee_reviewed_at', 'review_note',
])]
class FinalReport extends Model
{
    protected $attributes = ['review_status' => 'pending_supervisor'];

    // أسماء المراجعين تظهر مع التقرير أينما حُمّل (الفرق، المشروع، الأرشيف)
    protected $with = ['supervisorReviewer:id,name', 'committeeReviewer:id,name'];

    protected function casts(): array
    {
        return ['supervisor_reviewed_at' => 'datetime', 'committee_reviewed_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function supervisorReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_reviewed_by');
    }

    public function committeeReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'committee_reviewed_by');
    }
}
