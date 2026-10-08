<?php

namespace App\Enums;

// مكان تواجد الطالب — تُستخدم للتصنيف وفصل جداول المناقشات والتقارير (غزة / الجنوب).
enum RegionEnum: string
{
    case Gaza = 'gaza';   // غزة
    case South = 'south'; // جنوب
}
