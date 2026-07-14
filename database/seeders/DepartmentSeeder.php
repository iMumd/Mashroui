<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        Department::create(['name' => 'قسم البرمجيات']);
        Department::create(['name' => 'قسم الوسائط']);
    }
}
