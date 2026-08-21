<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\AcademicTerm;
use App\Models\Specialization;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $termId = AcademicTerm::where('is_current', true)->value('id');
        $specializationId = Specialization::value('id');

        User::create([
            'name' => 'أ. منار حمدان',
            'email' => 'committee@mashroui.local',
            'password' => 'password',
            'role' => RoleEnum::Committee,
            'whatsapp' => '970569845112',
            'employee_number' => 'EMP-1002',
        ]);

        User::create([
            'name' => 'م. خالد أبو غزالة',
            'email' => 'supervisor@mashroui.local',
            'password' => 'password',
            'role' => RoleEnum::Supervisor,
            'specialization_id' => $specializationId,
            'whatsapp' => '970525567234',
            'employee_number' => 'EMP-1003',
        ]);

        User::create([
            'name' => 'عبدالله الطويل',
            'email' => 'leader@mashroui.local',
            'password' => 'password',
            'role' => RoleEnum::TeamLeader,
            'specialization_id' => $specializationId,
            'term_id' => $termId,
            'whatsapp' => '970599887654',
            'university_number' => '12010547',
        ]);

        User::create([
            'name' => 'زيد النتشة',
            'email' => 'student@mashroui.local',
            'password' => 'password',
            'role' => RoleEnum::Student,
            'specialization_id' => $specializationId,
            'term_id' => $termId,
            'whatsapp' => '970568741235',
            'university_number' => '12010912',
        ]);
    }
}
