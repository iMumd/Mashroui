<?php

namespace App\Enums;

enum RoleEnum: string
{
    case SuperAdmin = 'super_admin';
    case Committee = 'committee';
    case Supervisor = 'supervisor';
    case TeamLeader = 'team_leader';
    case Student = 'student';
}
