<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use Illuminate\Database\Seeder;

class AcademicTermSeeder extends Seeder
{
    public function run(): void
    {
        AcademicTerm::create([
            'name' => 'الفصل الأول 2026/2027',
            'is_current' => true,
        ]);
    }
}
