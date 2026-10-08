<?php

namespace App\Enums;

enum RoleEnum: string
{
    case SuperAdmin = 'super_admin';
    case Committee = 'committee';
    case Supervisor = 'supervisor';
    // @deprecated القيادة صارت صلاحية داخل الفريق (teams.leader_id) لا دورًا — لا يُسند بعد الآن،
    // ويبقى هنا فقط لتوافق البيانات القديمة. القادة الحاليون دورهم student.
    case TeamLeader = 'team_leader';
    case Student = 'student';
}
