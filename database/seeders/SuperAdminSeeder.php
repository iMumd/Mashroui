<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\Concerns\UpsertsUsers;

class SuperAdminSeeder extends Seeder
{
    use UpsertsUsers;

    public function run(): void
    {
        $this->upsertUser('admin@mashroui.local', [
            'name' => 'باسل عوض',
            'password' => 'password',
            'role' => RoleEnum::SuperAdmin,
            'gender' => 'male',
            'whatsapp' => '970599112233',
            'employee_number' => 'EMP-1001',
            'must_change_password' => true,
        ], 'EMP-1001');
    }
}
