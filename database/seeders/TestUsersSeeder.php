<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\AcademicTerm;
use App\Models\Specialization;
use Database\Seeders\Concerns\UpsertsUsers;
use Illuminate\Database\Seeder;

class TestUsersSeeder extends Seeder
{
    use UpsertsUsers;

    public function run(): void
    {
        $termId = AcademicTerm::where('is_current', true)->value('id');
        $specializationId = Specialization::value('id');

        // عضو اللجنة مرتبط بتخصص حتى يرى مجموعات/مشاريع/مقترحات تخصصه فقط (SpecializationScope)
        $this->upsertUser('committee@ucas.edu.ps', [
            'name' => 'أ. منار حمدان',
            'password' => 'password',
            'role' => RoleEnum::Committee,
            'gender' => 'female',
            'specialization_id' => $specializationId,
            'whatsapp' => '970569845112',
            'employee_number' => 'EMP-1002',
        ], 'EMP-1002');

        $this->upsertUser('supervisor@ucas.edu.ps', [
            'name' => 'م. خالد أبو غزالة',
            'password' => 'password',
            'role' => RoleEnum::Supervisor,
            'gender' => 'male',
            'specialization_id' => $specializationId,
            'whatsapp' => '970525567234',
            'employee_number' => 'EMP-1003',
        ], 'EMP-1003');

        $this->upsertUser('leader@smail.ucas.edu.ps', [
            'name' => 'عبدالله الطويل',
            'password' => 'password',
            'role' => RoleEnum::Student,
            'gender' => 'male',
            'region' => 'gaza',
            'specialization_id' => $specializationId,
            'term_id' => $termId,
            'whatsapp' => '970599887654',
            'university_number' => '12010547',
        ]);

        $this->upsertUser('student@smail.ucas.edu.ps', [
            'name' => 'زيد النتشة',
            'password' => 'password',
            'role' => RoleEnum::Student,
            'gender' => 'male',
            'region' => 'gaza',
            'specialization_id' => $specializationId,
            'term_id' => $termId,
            'whatsapp' => '970568741235',
            'university_number' => '12010912',
        ]);
    }
}
