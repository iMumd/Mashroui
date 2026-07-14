<?php

namespace App\Models;

use App\Enums\AccessLevelEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'module', 'level', 'restricted_by'])]
class UserRestriction extends Model
{
    protected function casts(): array
    {
        return [
            'level' => AccessLevelEnum::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restrictedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restricted_by');
    }
}
