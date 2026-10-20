<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        Department::firstOrCreate(['name' => 'قسم البرمجيات']);
        Department::firstOrCreate(['name' => 'قسم الوسائط']);
    }
}
