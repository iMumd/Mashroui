<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\AcademicTerm;
use App\Models\Specialization;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * أعضاء تجريبيون لصفحة "إدارة الأعضاء": طلاب وطالبات (بالجنس ومكان التواجد) وأعضاء لجنة إشراف،
 * بعدد يكفي لظهور السلايدر (6 في كل شريحة). آمن لإعادة التشغيل: updateOrCreate بالبريد.
 *
 * php artisan db:seed --class=MembersDemoSeeder
 */
class MembersDemoSeeder extends Seeder
{
    private const MALE = ['محمد', 'أحمد', 'يوسف', 'عمر', 'خالد', 'إبراهيم', 'حمزة', 'سامي', 'مصطفى', 'أنس', 'عبد الرحمن', 'زياد', 'طارق', 'باسل', 'معاذ'];

    private const FEMALE = ['آية', 'سارة', 'مريم', 'نور', 'رهف', 'دعاء', 'هبة', 'ملك', 'شهد', 'لينا', 'بيان', 'إسراء', 'رغد', 'هديل', 'تسنيم'];

    private const FAMILY = ['النجار', 'الأغا', 'أبو شمالة', 'الحلبي', 'المدهون', 'الخضري', 'الشوا', 'أبو حسين', 'السقا', 'الجبالي', 'عوض', 'الكحلوت', 'أبو عمشة', 'الزق', 'صبرة'];

    private const COMMITTEE = [
        'د. سامي أبو ناصر', 'د. رنا الأسطل', 'أ. وائل شحادة', 'د. هالة المصري', 'أ. نضال عاشور', 'د. إيمان الحسنات',
        'أ. فادي الغلاييني', 'د. منى البرعي', 'أ. حسام الدحدوح', 'د. ريم أبو جراد', 'أ. كمال القيق', 'د. أسماء الريس',
    ];

    public function run(): void
    {
        $termId = AcademicTerm::where('is_current', true)->value('id');
        $specIds = Specialization::pluck('id')->all();

        foreach (self::COMMITTEE as $i => $name) {
            User::updateOrCreate(['email' => sprintf('committee%02d@ucas.edu.ps', $i + 1)], [
                'name' => $name,
                'password' => 'password',
                'role' => RoleEnum::Committee,
                'employee_number' => 'EMP-'.(2001 + $i),
                'whatsapp' => '97059'.str_pad((string) (3100000 + $i * 7919), 7, '0', STR_PAD_LEFT),
            ]);
        }

        // 15 طالب + 15 طالبة، موزّعون على غزة والجنوب والتخصصات
        foreach (['male' => self::MALE, 'female' => self::FEMALE] as $gender => $names) {
            foreach ($names as $i => $first) {
                $n = ($gender === 'male' ? 0 : 15) + $i;
                User::updateOrCreate(['email' => sprintf('student%02d@smail.ucas.edu.ps', $n + 1)], [
                    'name' => $first.' '.self::MALE[($i + 3) % 15].' '.self::FAMILY[$i],
                    'password' => 'password',
                    'role' => RoleEnum::Student,
                    'gender' => $gender,
                    'region' => $i % 3 === 2 ? 'south' : 'gaza',
                    'specialization_id' => $specIds ? $specIds[$n % count($specIds)] : null,
                    'term_id' => $termId,
                    'university_number' => (string) (120240000 + $n * 37),
                    'whatsapp' => '97056'.str_pad((string) (4200000 + $n * 6173), 7, '0', STR_PAD_LEFT),
                ]);
            }
        }
    }
}
