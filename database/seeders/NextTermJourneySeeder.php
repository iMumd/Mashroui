<?php

namespace Database\Seeders;

use App\Enums\ProjectStatusEnum;
use App\Enums\TaskStatusEnum;
use App\Models\AcademicTerm;
use App\Models\User;
use Database\Seeders\Concerns\SeedsJourney;
use Illuminate\Database\Seeder;

class NextTermJourneySeeder extends Seeder
{
    use SeedsJourney;

    public function run(): void
    {
        // الفصل القادم: أحدث فصل غير حالي (وليس الفصل الحالي أو السابق)
        $term = AcademicTerm::where('name', 'السنة الدراسية 2027/2028')->first()
            ?? AcademicTerm::where('is_current', false)->orderByDesc('id')->first()
            ?? AcademicTerm::first();
        $this->termId = $term->id;
        $this->studentSeq = 6000;

        $supervisor = fn ($e) => User::where('email', $e)->firstOrFail();

        $specSoft = $this->spec('برمجيات وقواعد بيانات');
        $specWeb = $this->spec('تصميم وتطوير مواقع الويب');
        $specNet = $this->spec('الشبكات وأمن المعلومات');
        $specAi = $this->spec('الأنظمة الذكية');

        // next 1 in progress
        $l1 = $this->member(['name' => 'ياسين حمد', 'email' => 'yaseen.hamad@smail.ucas.edu.ps', 'gender' => 'male'], $specSoft);
        $m1a = $this->member(['name' => 'جنى الأسطل', 'email' => 'jana.astal@smail.ucas.edu.ps', 'gender' => 'female'], $specSoft);
        [$team1, $proj1] = $this->makeTeam('فريق نظام إدارة الصرف الصحي الذكي', $supervisor('e.faraj@ucas.edu.ps'), $l1, [$m1a], $specSoft, 'نظام ذكي لرصد استهلاك المياه والصرف', 'نظام لمراقبة الاستهلاك، كشف التسرب، وإنذارات فورية مع تقارير.', ['أنظمة ذكية', 'بيئة'], ProjectStatusEnum::InProgress);
        $this->proposal($proj1, $l1, $supervisor('e.faraj@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 2, 'review_days' => 0]);
        $this->tasks($team1, $l1, [
            ['دراسة المتطلبات الأولية', TaskStatusEnum::Done, 'مراجعة الفكرة مع المشرف.'],
            ['تصميم قاعدة البيانات', TaskStatusEnum::InProgress, 'تصميم الجداول الأساسية.'],
            ['بناء الواجهة الأولية', TaskStatusEnum::Pending, 'صفحات تسجيل الدخول ولوحة التحكم.'],
        ]);
        $this->meetings($team1, $supervisor('e.faraj@ucas.edu.ps'), [
            ['اجتماع انطلاق الفصل', -1, 'تحديد خطة العمل للفصل القادم.'],
        ]);

        // next 2 in progress
        $l2 = $this->member(['name' => 'أحمد العالول', 'email' => 'ahmad.alaloul@smail.ucas.edu.ps', 'gender' => 'male'], $specWeb);
        $m2a = $this->member(['name' => 'تالا كمال', 'email' => 'tala.kamal@smail.ucas.edu.ps', 'gender' => 'female'], $specWeb);
        $m2b = $this->member(['name' => 'ريان أبو شرخ', 'email' => 'rayyan.abusharkh@smail.ucas.edu.ps', 'gender' => 'male'], $specWeb);
        [$team2, $proj2] = $this->makeTeam('فريق دليل الكلية التفاعلي', $supervisor('l.harrazeen@ucas.edu.ps'), $l2, [$m2a, $m2b], $specWeb, 'دليل تفاعلي ذكي للكلية مع مسارات للزوار', 'تطبيق يعرض أقسام الكلية، أعضاء الهيئة التدريسية، وجدول فعاليات مع خرائط تفاعلية.', ['تطوير ويب', 'خرائط'], ProjectStatusEnum::InProgress);
        $this->proposal($proj2, $l2, $supervisor('l.harrazeen@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 1, 'review_days' => 0]);
        $this->tasks($team2, $l2, [
            ['تحليل المتطلبات', TaskStatusEnum::Done, 'جمع المتطلبات من المشرف.'],
            ['تصميم الواجهات', TaskStatusEnum::InProgress, 'تصميم الصفحات الرئيسية.'],
            ['بناء قاعدة البيانات', TaskStatusEnum::Pending, 'جداول الأقسام والأعضاء.'],
        ]);
        $this->meetings($team2, $supervisor('l.harrazeen@ucas.edu.ps'), [
            ['اجتماع أولي', -1, 'اتفاق على تقسيم المهام.'],
        ]);

        // next 3 proposed
        $l3 = $this->member(['name' => 'محمد الزين', 'email' => 'mohammed.zein@smail.ucas.edu.ps', 'gender' => 'male'], $specNet);
        $m3a = $this->member(['name' => 'إيمان حلس', 'email' => 'eman.halas@smail.ucas.edu.ps', 'gender' => 'female'], $specNet);
        [$team3, $proj3] = $this->makeTeam('فريق فحص أمن الشبكات التلقائي', $supervisor('e.faraj@ucas.edu.ps'), $l3, [$m3a], $specNet, 'أداة فحص أولية لأمن الشبكة والتقارير', 'أداة لفحص ثغرات شائعة وإنشاء تقرير مبدئي مع توصيات.', ['أمن معلومات', 'شبكات'], ProjectStatusEnum::Proposed);
        $this->proposal($proj3, $l3, $supervisor('e.faraj@ucas.edu.ps'), ['state' => 'pending', 'submitted_days' => 1]);

        // next 4 proposed
        $l4 = $this->member(['name' => 'كريم شلباية', 'email' => 'karim.shalabaya@smail.ucas.edu.ps', 'gender' => 'male'], $specAi);
        $m4a = $this->member(['name' => 'رنا أبو صبحة', 'email' => 'rana.abusabha@smail.ucas.edu.ps', 'gender' => 'female'], $specAi);
        $m4b = $this->member(['name' => 'آية القاضي', 'email' => 'aya.qadi@smail.ucas.edu.ps', 'gender' => 'female'], $specAi);
        [$team4, $proj4] = $this->makeTeam('فريق روبوت دردشة ذكي للكلية', $supervisor('l.harrazeen@ucas.edu.ps'), $l4, [$m4a, $m4b], $specAi, 'روبوت دردشة ذكي يجاوب على أسئلة الطلاب', 'روبوت يعتمد على أسئلة شائعة ويجيب تلقائياً عن التسجيل، الجداول، والمواعيد.', ['ذكاء اصطناعي', 'روبوت محادثة'], ProjectStatusEnum::Proposed);
        $this->proposal($proj4, $l4, $supervisor('l.harrazeen@ucas.edu.ps'), ['state' => 'pending', 'submitted_days' => 0]);
    }
}
