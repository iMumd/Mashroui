<?php

namespace App\Models\Scopes;

use App\Support\CurrentTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TermScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $termId = app(CurrentTerm::class)->get();

        if ($termId !== null) {
            $builder->where($model->getTable().'.term_id', $termId);
        }
    }
}
