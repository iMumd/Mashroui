<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_current'])]
class AcademicTerm extends Model
{
    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'term_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'term_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'term_id');
    }

    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class, 'term_id');
    }
}
