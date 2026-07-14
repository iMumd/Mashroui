<?php

namespace App\Enums;

enum ProjectStatusEnum: string
{
    case Proposed = 'proposed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
