<?php

namespace App\Models\Scopes;

use App\Enums\RoleEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * عضو لجنة الإشراف المرتبط بتخصص يرى بيانات تخصصه فقط (المجموعات والمشاريع وكل ما يُستعلم عبرها بـ whereHas).
 * عضو اللجنة بلا تخصص والأدوار الأخرى بلا تغيير.
 */
class SpecializationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if ($user && $user->role === RoleEnum::Committee) {
            $ids = $user->effectiveSpecializationIds();

            if ($ids) {
                $builder->whereIn($model->getTable().'.specialization_id', $ids);
            }
        }
    }
}
