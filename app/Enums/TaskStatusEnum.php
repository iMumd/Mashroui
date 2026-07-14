<?php

namespace App\Enums;

enum TaskStatusEnum: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Done = 'done';
}
