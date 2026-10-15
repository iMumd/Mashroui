<?php

namespace App\Enums;

// مكان تواجد الطالب — تُستخدم للتصنيف وفصل جداول المناقشات والتقارير (غزة / وسط غزة / الجنوب).
enum RegionEnum: string
{
    case Gaza = 'gaza';     // غزة
    case Middle = 'middle'; // وسط غزة
    case South = 'south';   // جنوب
}
