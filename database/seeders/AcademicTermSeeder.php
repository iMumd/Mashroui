<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use Illuminate\Database\Seeder;

class AcademicTermSeeder extends Seeder
{
    public function run(): void
    {
        AcademicTerm::firstOrCreate([
            'name' => 'السنة الدراسية 2025/2026',
        ], [
            'is_current' => false,
        ]);

        AcademicTerm::firstOrCreate([
            'name' => 'الفصل الأول 2026/2027',
        ], [
            'is_current' => true,
        ]);

        AcademicTerm::firstOrCreate([
            'name' => 'السنة الدراسية 2027/2028',
        ], [
            'is_current' => false,
        ]);
    }
}
