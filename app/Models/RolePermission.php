<?php

namespace App\Models;

use App\Enums\AccessLevelEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['role', 'module', 'level'])]
class RolePermission extends Model
{
    protected function casts(): array
    {
        return [
            'role' => RoleEnum::class,
            'level' => AccessLevelEnum::class,
        ];
    }
}
