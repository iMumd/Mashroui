<?php

namespace App\Enums;

enum NotificationChannelEnum: string
{
    case Email = 'email';
    case Whatsapp = 'whatsapp';
}
