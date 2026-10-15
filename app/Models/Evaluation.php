<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_id', 'student_id', 'supervisor_id', 'score', 'supervisor_score', 'committee_score', 'notes'])]
class Evaluation extends Model
{
    /**
     * توحيد تنسيق الدرجات كـ decimal(…,2) عبر كل المحركات (MySQL يرجّعها نصًا "35.00"،
     * بينما SQLite يرجّع 35)، حتى تتطابق مخرجات الـ API ونتائج التقييم في كل البيئات.
     */
    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'supervisor_score' => 'decimal:2',
            'committee_score' => 'decimal:2',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
