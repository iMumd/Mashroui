<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'باسل عوض',
            'email' => 'admin@mashroui.local',
            'password' => 'password',
            'role' => RoleEnum::SuperAdmin,
            'whatsapp' => '970599112233',
            'employee_number' => 'EMP-1001',
            'must_change_password' => true,
        ]);
    }
}
