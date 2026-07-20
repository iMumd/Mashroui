<?php

namespace App\Enums;

enum DeliveryStatusEnum: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
