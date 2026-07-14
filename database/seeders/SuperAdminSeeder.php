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
            'name' => 'Super Admin',
            'email' => 'admin@mashroui.local',
            'password' => 'password',
            'role' => RoleEnum::SuperAdmin,
            'must_change_password' => true,
        ]);
    }
}
