<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\AcademicTerm;
use App\Models\Specialization;
use Database\Seeders\Concerns\UpsertsUsers;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    use UpsertsUsers;

    public function run(): void
    {
        $this->seedCommittee();
        $this->seedSupervisors();
    }

    protected function seedCommittee(): void
    {
        $current = AcademicTerm::where('is_current', true)->firstOrFail();
        $past = AcademicTerm::where('name', 'السنة الدراسية 2025/2026')->first();
        $next = AcademicTerm::where('name', 'السنة الدراسية 2027/2028')->first();

        $list = [
            ['name' => 'أ. منار حمدان', 'email' => 'committee@ucas.edu.ps', 'emp' => 'EMP-1002', 'whatsapp' => '970569845112', 'specs' => [['name' => 'تصميم وتطوير مواقع الويب'], ['name' => 'برمجيات وقواعد بيانات']], 'term' => $current->id],
            ['name' => 'د. سامي أبو ناصر', 'email' => 'committee01@ucas.edu.ps', 'emp' => 'EMP-4001', 'whatsapp' => '970599310001', 'specs' => [['name' => 'برمجيات وقواعد بيانات']], 'term' => $current->id],
            ['name' => 'د. إيمان الحسن', 'email' => 'committee02@ucas.edu.ps', 'emp' => 'EMP-4002', 'whatsapp' => '970599310002', 'specs' => [['name' => 'الشبكات وأمن المعلومات']], 'term' => $current->id],
            ['name' => 'م. رامي قاسم', 'email' => 'committee03@ucas.edu.ps', 'emp' => 'EMP-4003', 'whatsapp' => '970599310003', 'specs' => [['name' => 'الأنظمة الذكية']], 'term' => $current->id],
            ['name' => 'د. نور الشريف', 'email' => 'committee04@ucas.edu.ps', 'emp' => 'EMP-4004', 'whatsapp' => '970599310004', 'specs' => [['name' => 'برمجيات وقواعد بيانات']], 'term' => $current->id],
            ['name' => 'أ. هاني المصري', 'email' => 'committee05@ucas.edu.ps', 'emp' => 'EMP-4005', 'whatsapp' => '970599310005', 'specs' => [['name' => 'تصميم وتطوير مواقع الويب']], 'term' => $current->id],
            ['name' => 'د. وليد بركة', 'email' => 'committee06@ucas.edu.ps', 'emp' => 'EMP-4006', 'whatsapp' => '970599310006', 'specs' => [['name' => 'الشبكات وأمن المعلومات']], 'term' => $current->id],
            ['name' => 'م. آمال صبري', 'email' => 'committee07@ucas.edu.ps', 'emp' => 'EMP-4007', 'whatsapp' => '970599310007', 'specs' => [['name' => 'الأنظمة الذكية']], 'term' => $current->id],
            ['name' => 'د. باسم عودة', 'email' => 'committee08@ucas.edu.ps', 'emp' => 'EMP-4008', 'whatsapp' => '970599310008', 'specs' => [['name' => 'برمجيات وقواعد بيانات']], 'term' => $current->id],
            ['name' => 'م. رنا أبو حسين', 'email' => 'committee09@ucas.edu.ps', 'emp' => 'EMP-4009', 'whatsapp' => '970599310009', 'specs' => [['name' => 'تصميم وتطوير مواقع الويب']], 'term' => $current->id],
            ['name' => 'د. فادي التاج', 'email' => 'committee10@ucas.edu.ps', 'emp' => 'EMP-4010', 'whatsapp' => '970599310010', 'specs' => [['name' => 'الشبكات وأمن المعلومات']], 'term' => $current->id],
            ['name' => 'م. أمل درويش', 'email' => 'committee11@ucas.edu.ps', 'emp' => 'EMP-4011', 'whatsapp' => '970599310011', 'specs' => [['name' => 'الأنظمة الذكية']], 'term' => $current->id],
            // لجنة الفصل السابق (تبقى مرتبطة بفصلها ولا تظهر في الفصل الحالي)
            ['name' => 'د. فتحي الأغا', 'email' => 'committee_past01@ucas.edu.ps', 'emp' => 'EMP-4101', 'whatsapp' => '970599311001', 'specs' => [['name' => 'برمجيات وقواعد بيانات']], 'term' => $past?->id ?? $current->id],
            ['name' => 'م. سلمى أبو ندى', 'email' => 'committee_past02@ucas.edu.ps', 'emp' => 'EMP-4102', 'whatsapp' => '970599311002', 'specs' => [['name' => 'تصميم وتطوير مواقع الويب']], 'term' => $past?->id ?? $current->id],
            ['name' => 'د. جمال شقورة', 'email' => 'committee_past03@ucas.edu.ps', 'emp' => 'EMP-4103', 'whatsapp' => '970599311003', 'specs' => [['name' => 'الشبكات وأمن المعلومات']], 'term' => $past?->id ?? $current->id],

            // لجنة الفصل القادم
            ['name' => 'د. مازن أبو شمالة', 'email' => 'committee_next01@ucas.edu.ps', 'emp' => 'EMP-4201', 'whatsapp' => '970599312001', 'specs' => [['name' => 'برمجيات وقواعد بيانات']], 'term' => $next?->id ?? $current->id],
            ['name' => 'م. رهام الشرفا', 'email' => 'committee_next02@ucas.edu.ps', 'emp' => 'EMP-4202', 'whatsapp' => '970599312002', 'specs' => [['name' => 'الأنظمة الذكية']], 'term' => $next?->id ?? $current->id],
        ];

        foreach ($list as $row) {
            $firstSpec = $row['specs'][0];
            $spec = Specialization::where('name', $firstSpec['name'])->firstOrFail();

            $user = $this->upsertUser($row['email'], [
                'name' => $row['name'],
                'password' => 'password',
                'role' => RoleEnum::Committee,
                'employee_number' => $row['emp'],
                'whatsapp' => $row['whatsapp'],
                'specialization_id' => $spec->id,
                'term_id' => $row['term'],
                'must_change_password' => false,
            ], $row['emp']);

            $user->update([
                'role' => RoleEnum::Committee,
                'specialization_id' => $spec->id,
                'term_id' => $row['term'],
                'employee_number' => $row['emp'],
                'whatsapp' => $row['whatsapp'],
                'must_change_password' => false,
            ]);

            $ids = [];
            foreach ($row['specs'] as $s) {
                $sp = Specialization::where('name', $s['name'])->first();
                if ($sp) {
                    $ids[] = $sp->id;
                }
            }
            $user->specializations()->syncWithoutDetaching(array_values(array_unique($ids)));
        }
    }

    protected function seedSupervisors(): void
    {
        $current = AcademicTerm::where('is_current', true)->firstOrFail();
        $past = AcademicTerm::where('name', 'السنة الدراسية 2025/2026')->first();
        $next = AcademicTerm::where('name', 'السنة الدراسية 2027/2028')->first();

        $supervisors = [
            ['name' => 'د. سامر خليل', 'email' => 'supervisor@ucas.edu.ps', 'emp' => 'EMP-2001', 'whatsapp' => '970599320001', 'spec' => 'برمجيات وقواعد بيانات', 'term' => $current->id],
            ['name' => 'د. رائد جبر', 'email' => 'r.jabr@ucas.edu.ps', 'emp' => 'EMP-2002', 'whatsapp' => '970599320002', 'spec' => 'برمجيات وقواعد بيانات', 'term' => $current->id],
            ['name' => 'م. حنان أبو عامر', 'email' => 'h.abuamer@ucas.edu.ps', 'emp' => 'EMP-2003', 'whatsapp' => '970599320003', 'spec' => 'تصميم وتطوير مواقع الويب', 'term' => $current->id],
            ['name' => 'د. محمد الزعنون', 'email' => 'm.zaanoun@ucas.edu.ps', 'emp' => 'EMP-2004', 'whatsapp' => '970599320004', 'spec' => 'الشبكات وأمن المعلومات', 'term' => $current->id],
            ['name' => 'م. ياسر مطر', 'email' => 'y.matar@ucas.edu.ps', 'emp' => 'EMP-2005', 'whatsapp' => '970599320005', 'spec' => 'الأنظمة الذكية', 'term' => $current->id],
            ['name' => 'د. أمل ناصر', 'email' => 'a.nasser@ucas.edu.ps', 'emp' => 'EMP-2006', 'whatsapp' => '970599320006', 'spec' => 'برمجيات وقواعد بيانات', 'term' => $current->id],
            ['name' => 'م. طارق حمد', 'email' => 't.hamad@ucas.edu.ps', 'emp' => 'EMP-2007', 'whatsapp' => '970599320007', 'spec' => 'تصميم وتطوير مواقع الويب', 'term' => $current->id],
            ['name' => 'د. هدى أبو شرخ', 'email' => 'h.abusharkh@ucas.edu.ps', 'emp' => 'EMP-2008', 'whatsapp' => '970599320008', 'spec' => 'الشبكات وأمن المعلومات', 'term' => $current->id],
            ['name' => 'م. حسام الزين', 'email' => 'h.zein@ucas.edu.ps', 'emp' => 'EMP-2009', 'whatsapp' => '970599320009', 'spec' => 'الأنظمة الذكية', 'term' => $current->id],
            ['name' => 'د. إيناس العبادلة', 'email' => 'e.abbadleh@ucas.edu.ps', 'emp' => 'EMP-2010', 'whatsapp' => '970599320010', 'spec' => 'برمجيات وقواعد بيانات', 'term' => $current->id],
            ['name' => 'م. غسان شبات', 'email' => 'g.shabat@ucas.edu.ps', 'emp' => 'EMP-2011', 'whatsapp' => '970599320011', 'spec' => 'تصميم وتطوير مواقع الويب', 'term' => $current->id],
            ['name' => 'د. رشا العدوان', 'email' => 'r.adwan@ucas.edu.ps', 'emp' => 'EMP-2012', 'whatsapp' => '970599320012', 'spec' => 'الأنظمة الذكية', 'term' => $current->id],
            // past term
            ['name' => 'د. ناصر أبو لبدة', 'email' => 'n.abulbada@ucas.edu.ps', 'emp' => 'EMP-2101', 'whatsapp' => '970599321001', 'spec' => 'برمجيات وقواعد بيانات', 'term' => $past?->id ?? $current->id],
            ['name' => 'م. سهام القوقا', 'email' => 's.qouqa@ucas.edu.ps', 'emp' => 'EMP-2102', 'whatsapp' => '970599321002', 'spec' => 'تصميم وتطوير مواقع الويب', 'term' => $past?->id ?? $current->id],
            // next term
            ['name' => 'د. عماد فرج', 'email' => 'e.faraj@ucas.edu.ps', 'emp' => 'EMP-2201', 'whatsapp' => '970599322001', 'spec' => 'الشبكات وأمن المعلومات', 'term' => $next?->id ?? $current->id],
            ['name' => 'م. لبنى الحرازين', 'email' => 'l.harrazeen@ucas.edu.ps', 'emp' => 'EMP-2202', 'whatsapp' => '970599322002', 'spec' => 'الأنظمة الذكية', 'term' => $next?->id ?? $current->id],
            // new for current term
            ['name' => 'د. سائد أبو ندى', 'email' => 'saed.abunada@ucas.edu.ps', 'emp' => 'EMP-1008', 'whatsapp' => '970599320108', 'spec' => 'برمجيات وقواعد بيانات', 'term' => $current->id],
            ['name' => 'م. دعاء الأغا', 'email' => 'doaa.alagha@ucas.edu.ps', 'emp' => 'EMP-1009', 'whatsapp' => '970599320109', 'spec' => 'تصميم وتطوير مواقع الويب', 'term' => $current->id],
            ['name' => 'أ. بلال الشريف', 'email' => 'bilal.alsharif@ucas.edu.ps', 'emp' => 'EMP-1010', 'whatsapp' => '970599320110', 'spec' => 'الأنظمة الذكية', 'term' => $current->id],
        ];

        foreach ($supervisors as $row) {
            $spec = Specialization::where('name', $row['spec'])->firstOrFail();
            $user = $this->upsertUser($row['email'], [
                'name' => $row['name'],
                'password' => 'password',
                'role' => RoleEnum::Supervisor,
                'employee_number' => $row['emp'],
                'whatsapp' => $row['whatsapp'],
                'specialization_id' => $spec->id,
                'term_id' => $row['term'],
                'must_change_password' => false,
            ], $row['emp']);

            $user->update([
                'role' => RoleEnum::Supervisor,
                'specialization_id' => $spec->id,
                'employee_number' => $row['emp'],
                'whatsapp' => $row['whatsapp'],
                'term_id' => $row['term'],
                'must_change_password' => false,
            ]);
        }
    }
}
