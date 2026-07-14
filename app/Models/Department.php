<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Department extends Model
{
    public function specializations(): HasMany
    {
        return $this->hasMany(Specialization::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
