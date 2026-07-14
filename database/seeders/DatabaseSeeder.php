<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            SpecializationSeeder::class,
            AcademicTermSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
