<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** تعبئة نموذج تقييم لمجموعة من مقيّم واحد — answers: { project: {itemId: {score, note}}, students: {studentId: {itemId: score}} } */
#[Fillable(['form_id', 'team_id', 'evaluator_id', 'answers', 'general_notes', 'nominate'])]
class EvaluationSubmission extends Model
{
    protected function casts(): array
    {
        return ['answers' => 'array', 'nominate' => 'boolean'];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(EvaluationForm::class, 'form_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
