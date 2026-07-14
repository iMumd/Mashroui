<?php

namespace Database\Seeders;

use App\Enums\DegreeEnum;
use App\Models\Department;
use App\Models\Specialization;
use Illuminate\Database\Seeder;

class SpecializationSeeder extends Seeder
{
    public function run(): void
    {
        $software = Department::where('name', 'قسم البرمجيات')->firstOrFail();
        $media = Department::where('name', 'قسم الوسائط')->firstOrFail();

        Specialization::create([
            'department_id' => $software->id,
            'name' => 'تصميم وتطوير مواقع الويب',
            'degree' => DegreeEnum::Diploma,
        ]);

        Specialization::create([
            'department_id' => $software->id,
            'name' => 'برمجيات وقواعد بيانات',
            'degree' => DegreeEnum::Diploma,
        ]);

        Specialization::create([
            'department_id' => $software->id,
            'name' => 'تصميم وبرمجة تطبيقات الموبايل',
            'degree' => DegreeEnum::Bachelor,
        ]);

        Specialization::create([
            'department_id' => $media->id,
            'name' => 'تكنولوجيا الوسائط المتعددة',
            'degree' => DegreeEnum::Diploma,
        ]);

        Specialization::create([
            'department_id' => $media->id,
            'name' => 'تكنولوجيا الوسائط المتعددة',
            'degree' => DegreeEnum::Bachelor,
        ]);
    }
}
