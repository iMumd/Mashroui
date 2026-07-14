<?php

namespace App\Enums;

enum UserStatusEnum: string
{
    case Active = 'active';
    case Restricted = 'restricted';
}
