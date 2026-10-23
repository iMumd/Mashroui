<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\Concerns\UpsertsUsers;
use Illuminate\Database\Seeder;

class TestUsersSeeder extends Seeder
{
    use UpsertsUsers;

    public function run(): void
    {
        $this->upsertUser('leader@smail.ucas.edu.ps', [
            'name' => 'عبدالله الطويل',
            'password' => 'password',
            'role' => RoleEnum::Student,
            'gender' => 'male',
            'region' => 'gaza',
            'whatsapp' => '970599800001',
            'university_number' => '12010001',
        ]);

        $this->upsertUser('student@smail.ucas.edu.ps', [
            'name' => 'زيد النتشة',
            'password' => 'password',
            'role' => RoleEnum::Student,
            'gender' => 'male',
            'region' => 'gaza',
            'whatsapp' => '970599800002',
            'university_number' => '12010002',
        ]);
    }
}
