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
            'name' => 'Committee Member',
            'email' => 'committee@mashroui.local',
            'password' => 'password',
            'role' => RoleEnum::Committee,
        ]);

        User::create([
            'name' => 'Supervisor',
            'email' => 'supervisor@mashroui.local',
            'password' => 'password',
            'role' => RoleEnum::Supervisor,
            'specialization_id' => $specializationId,
        ]);

        User::create([
            'name' => 'Team Leader',
            'email' => 'leader@mashroui.local',
            'password' => 'password',
            'role' => RoleEnum::TeamLeader,
            'specialization_id' => $specializationId,
            'term_id' => $termId,
        ]);

        User::create([
            'name' => 'Student',
            'email' => 'student@mashroui.local',
            'password' => 'password',
            'role' => RoleEnum::Student,
            'specialization_id' => $specializationId,
            'term_id' => $termId,
        ]);
    }
}
