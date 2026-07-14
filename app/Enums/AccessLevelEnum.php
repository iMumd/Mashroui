<?php

namespace App\Enums;

enum AccessLevelEnum: string
{
    case Full = 'full';
    case ViewOnly = 'view_only';
    case Blocked = 'blocked';
}
