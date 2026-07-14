<?php

namespace App\Models;

use App\Enums\ProposalStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'name',
    'description',
    'problems',
    'solutions',
    'features_value',
    'pdf_path',
    'status',
    'rejection_reason',
    'submitted_by',
    'reviewed_by',
])]
class Proposal extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ProposalStatusEnum::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
