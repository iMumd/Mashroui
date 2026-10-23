<?php

namespace Database\Seeders;

use App\Models\Specialization;
use App\Models\TeamMember;
use Database\Seeders\Concerns\SeedsJourney;
use Illuminate\Database\Seeder;

class StudentRosterSeeder extends Seeder
{
    use SeedsJourney;

    public function run(): void
    {
        $term = \App\Models\AcademicTerm::where('is_current', true)->firstOrFail();
        $this->termId = $term->id;
        $this->studentSeq = 2000;

        $specs = [
            $this->spec('برمجيات وقواعد بيانات'),
            $this->spec('تصميم وتطوير مواقع الويب'),
            $this->spec('الشبكات وأمن المعلومات'),
            $this->spec('الأنظمة الذكية'),
        ];

        $roster = [
            ['name' => 'أحمد أبو ليلة', 'email' => 'ahmad.abuleila@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'محمد الحاج', 'email' => 'mohammed.haj@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'ياسر النجار', 'email' => 'yaser.najjar@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'عمر شحادة', 'email' => 'omar.shahada@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'إبراهيم الكرد', 'email' => 'ibrahim.kurd@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'سائد دغلس', 'email' => 'saed.daghles@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'معتصم أبوربيع', 'email' => 'moatasem.aburabi@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'أنس الأسطل', 'email' => 'anas.astal@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'حسام البيوك', 'email' => 'hossam.bayouk@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'باسل قديح', 'email' => 'basel.qdeih@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'سارة الدجاني', 'email' => 'sara.dajani@smail.ucas.edu.ps', 'gender' => 'female'],
            ['name' => 'نور أبو صفية', 'email' => 'noor.abusafiya@smail.ucas.edu.ps', 'gender' => 'female'],
            ['name' => 'مريم قاسم', 'email' => 'mariam.qasem@smail.ucas.edu.ps', 'gender' => 'female'],
            ['name' => 'هديل المصري', 'email' => 'hadeel.masri@smail.ucas.edu.ps', 'gender' => 'female'],
            ['name' => 'رغد شلبي', 'email' => 'raghad.shalaby@smail.ucas.edu.ps', 'gender' => 'female'],
            ['name' => 'رهف عابد', 'email' => 'rahaf.abed@smail.ucas.edu.ps', 'gender' => 'female'],
            ['name' => 'هبة الرملاوي', 'email' => 'hiba.ramlawi@smail.ucas.edu.ps', 'gender' => 'female'],
            ['name' => 'ملك النيرب', 'email' => 'malak.nayrab@smail.ucas.edu.ps', 'gender' => 'female'],
            ['name' => 'شهد أبو هولي', 'email' => 'shahd.abholey@smail.ucas.edu.ps', 'gender' => 'female'],
            ['name' => 'لينا كحيل', 'email' => 'lina.kaheel@smail.ucas.edu.ps', 'gender' => 'female'],
            ['name' => 'علي الخضري', 'email' => 'ali.khader@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'عبدالرحمن صافي', 'email' => 'abdelrahman.safi@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'محمود الجعبري', 'email' => 'mahmoud.jaabari@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'خالد أبو حطب', 'email' => 'khaled.abuhatab@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'يحيى الشيخ', 'email' => 'yahya.sheikh@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'أمجد دغمش', 'email' => 'amjad.daghmesh@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'فادي عيسى', 'email' => 'fadi.eissa@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'رأفت صالح', 'email' => 'rafat.saleh@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'نضال حلس', 'email' => 'nidal.halas@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'جمال بريك', 'email' => 'jamal.breek@smail.ucas.edu.ps', 'gender' => 'male'],
            ['name' => 'تسنيم أبو عمرة', 'email' => 'tasneem.abouamra@smail.ucas.edu.ps', 'gender' => 'female'],
            ['name' => 'إسراء حمدان', 'email' => 'esraa.hamdan@smail.ucas.edu.ps', 'gender' => 'female'],
            ['name' => 'بيان الجرو', 'email' => 'bayan.jarou@smail.ucas.edu.ps', 'gender' => 'female'],
            ['name' => 'دانا الأسعد', 'email' => 'dana.assad@smail.ucas.edu.ps', 'gender' => 'female'],
            ['name' => 'جنى العجل', 'email' => 'jana.ajel@smail.ucas.edu.ps', 'gender' => 'female'],
            ['name' => 'رنيم شقير', 'email' => 'raneem.shaqeer@smail.ucas.edu.ps', 'gender' => 'female'],
        ];

        $memberIds = TeamMember::pluck('student_id')->all();

        foreach ($roster as $i => $r) {
            $spec = $specs[$i % count($specs)];
            $user = $this->member($r, $spec);
            // ensure they're not assigned to any team
            if (in_array($user->id, $memberIds, true)) {
                continue;
            }
        }
    }
}
