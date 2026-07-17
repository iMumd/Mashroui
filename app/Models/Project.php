<?php

namespace App\Models;

use App\Enums\ProjectStatusEnum;
use App\Models\Scopes\TermScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'team_id',
    'supervisor_id',
    'name',
    'description',
    'department_id',
    'specialization_id',
    'term_id',
    'status',
    'is_featured',
    'completed_at',
    'archived_at',
])]
class Project extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new TermScope);
    }

    protected function casts(): array
    {
        return [
            'status' => ProjectStatusEnum::class,
            'is_featured' => 'boolean',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    public function proposal(): HasOne
    {
        return $this->hasOne(Proposal::class);
    }

    public function finalReports(): HasMany
    {
        return $this->hasMany(FinalReport::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class);
    }
}
