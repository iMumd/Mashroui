<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * عضو لجنة الإشراف المرتبط بتخصصات (حتى 5) أو بقسم كامل يرى بياناتها فقط (المجموعات والمشاريع وكل ما يُستعلم عبرها بـ whereHas).
 * عضو اللجنة بلا تخصص والأدوار الأخرى بلا تغيير.
 */
class SpecializationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $ids = Auth::user()?->committeeSpecializationIds();

        if ($ids !== null) {
            $builder->whereIn($model->getTable().'.specialization_id', $ids);
        }
    }
}
