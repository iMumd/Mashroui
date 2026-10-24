<?php

namespace Database\Seeders;

use App\Enums\DiscussionStatusEnum;
use App\Enums\ProjectStatusEnum;
use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\AcademicTerm;
use App\Models\Discussion;
use App\Models\User;
use Database\Seeders\Concerns\SeedsJourney;
use Illuminate\Database\Seeder;

class PastTermJourneySeeder extends Seeder
{
    use SeedsJourney;

    public function run(): void
    {
        // الفصل السابق: أحد الفصول غير الحالية الأقدم (وليس الفصل الحالي)
        $term = AcademicTerm::where('name', 'السنة الدراسية 2025/2026')->first()
            ?? AcademicTerm::where('is_current', false)->orderBy('id')->first()
            ?? AcademicTerm::first();
        $this->termId = $term->id;
        $this->studentSeq = 4000;

        $supervisor = fn ($e) => User::where('email', $e)->firstOrFail();
        $committee = User::where('role', RoleEnum::Committee)->orderBy('id')->first();

        $specSoft = $this->spec('برمجيات وقواعد بيانات');
        $specWeb = $this->spec('تصميم وتطوير مواقع الويب');
        $specNet = $this->spec('الشبكات وأمن المعلومات');
        $specAi = $this->spec('الأنظمة الذكية');

        // past 1
        $l1 = $this->member(['name' => 'محمد أبو صلاح', 'email' => 'mohammed.abusalah@smail.ucas.edu.ps', 'gender' => 'male'], $specSoft);
        $m1a = $this->member(['name' => 'أسماء العطار', 'email' => 'asmaa.attar@smail.ucas.edu.ps', 'gender' => 'female'], $specSoft);
        $m1b = $this->member(['name' => 'بكر جادو', 'email' => 'bakr.jadou@smail.ucas.edu.ps', 'gender' => 'male'], $specSoft);
        [$team1, $proj1] = $this->makeTeam('فريق نظام مكتبة الجامعة', $supervisor('n.abulbada@ucas.edu.ps'), $l1, [$m1a, $m1b], $specSoft, 'نظام مكتبة الجامعة الإلكتروني', 'نظام لإدارة الكتب، الإعارة، الاسترجاع، والغرامات مع بحث متقدم.', ['نظم معلومات', 'أرشفة'], ProjectStatusEnum::Completed, 420);
        $this->proposal($proj1, $l1, $supervisor('n.abulbada@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 480, 'review_days' => 440]);
        $this->tasks($team1, $l1, [
            ['تحليل المتطلبات', TaskStatusEnum::Done, 'دراسة نظام المكتبة الحالي.'],
            ['تصميم قاعدة البيانات', TaskStatusEnum::Done, 'تصميم الجداول وعلاقات الإعارة.'],
            ['بناء نظام الإعارة', TaskStatusEnum::Done, 'إعارة، استرجاع، وغرامات.'],
            ['البحث والتقارير', TaskStatusEnum::Done, 'بحث متقدم وتقارير دورية.'],
            ['الاختبار والتسليم', TaskStatusEnum::Done, 'اختبار شامل.'],
        ]);
        $this->meetings($team1, $supervisor('n.abulbada@ucas.edu.ps'), [
            ['انطلاق', -470, 'مناقشة الخطة.'],
            ['مراجعة', -440, 'متابعة التقدم.'],
            ['نهائي', -430, 'مراجعة التقرير.'],
        ]);
        $this->finalReport($proj1, $l1, $supervisor('n.abulbada@ucas.edu.ps'), $committee, ['review' => 'approved', 'supervisor_days' => 425, 'committee_days' => 422]);
        if (! Discussion::withoutGlobalScopes()->where('project_id', $proj1->id)->exists()) {
            Discussion::create([
                'project_id' => $proj1->id,
                'supervisor_id' => $supervisor('n.abulbada@ucas.edu.ps')->id,
                'place' => 'قاعة المناقشات - مبنى أ',
                'discussion_date' => now()->subDays(410)->toDateString(),
                'discussion_time' => '09:00',
                'committee' => 'د. نور الشريف، م. رامي قاسم، أ. هاني المصري',
                'whatsapp' => null,
                'status' => DiscussionStatusEnum::Confirmed,
                'term_id' => $this->termId,
            ]);
        }

        // past 2
        $l2 = $this->member(['name' => 'هاني الجبور', 'email' => 'hani.jabour@smail.ucas.edu.ps', 'gender' => 'male'], $specWeb);
        $m2a = $this->member(['name' => 'دعاء التلباني', 'email' => 'doaa.talbani@smail.ucas.edu.ps', 'gender' => 'female'], $specWeb);
        $m2b = $this->member(['name' => 'سماح أبو شنب', 'email' => 'samah.abushanab@smail.ucas.edu.ps', 'gender' => 'female'], $specWeb);
        [$team2, $proj2] = $this->makeTeam('فريق موقع الكلية التعريفي', $supervisor('s.qouqa@ucas.edu.ps'), $l2, [$m2a, $m2b], $specWeb, 'موقع تعريفي للكلية متعدد اللغات', 'موقع يعرض الأقسام، أعضاء هيئة التدريس، الأخبار، والفعاليات.', ['مواقع ويب', 'تعريف'], ProjectStatusEnum::Completed, 400);
        $this->proposal($proj2, $l2, $supervisor('s.qouqa@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 460, 'review_days' => 430]);
        $this->tasks($team2, $l2, [
            ['تصميم الواجهة', TaskStatusEnum::Done, 'تصميم صفحات الموقع.'],
            ['بناء الأقسام والأخبار', TaskStatusEnum::Done, 'نظام الأخبار، الأقسام، والفعاليات.'],
            ['معرض الصور والاتصال', TaskStatusEnum::Done, 'معرض، نموذج اتصال، وخريطة.'],
            ['الاختبار والنشر', TaskStatusEnum::Done, 'اختبار وتجهيز للنشر.'],
        ]);
        $this->meetings($team2, $supervisor('s.qouqa@ucas.edu.ps'), [
            ['انطلاق', -450, 'مناقشة المتطلبات.'],
            ['عرض', -435, 'عرض النموذج.'],
            ['نهائي', -425, 'مراجعة.'],
        ]);
        $this->finalReport($proj2, $l2, $supervisor('s.qouqa@ucas.edu.ps'), $committee, ['review' => 'approved', 'supervisor_days' => 415, 'committee_days' => 412]);
        if (! Discussion::withoutGlobalScopes()->where('project_id', $proj2->id)->exists()) {
            Discussion::create([
                'project_id' => $proj2->id,
                'supervisor_id' => $supervisor('s.qouqa@ucas.edu.ps')->id,
                'place' => 'قاعة المناقشات - مبنى ب',
                'discussion_date' => now()->subDays(405)->toDateString(),
                'discussion_time' => '11:00',
                'committee' => 'د. سامي أبو ناصر، د. إيمان الحسن، م. آمال صبري',
                'whatsapp' => null,
                'status' => DiscussionStatusEnum::Confirmed,
                'term_id' => $this->termId,
            ]);
        }

        // past 3
        $l3 = $this->member(['name' => 'أحمد المزين', 'email' => 'ahmad.muzain@smail.ucas.edu.ps', 'gender' => 'male'], $specNet);
        $m3a = $this->member(['name' => 'روان سمور', 'email' => 'rawan.samor@smail.ucas.edu.ps', 'gender' => 'female'], $specNet);
        $m3b = $this->member(['name' => 'محمود طه', 'email' => 'mahmoud.taha@smail.ucas.edu.ps', 'gender' => 'male'], $specNet);
        [$team3, $proj3] = $this->makeTeam('فريق مراقبة الشبكة الداخلية', $supervisor('n.abulbada@ucas.edu.ps'), $l3, [$m3a, $m3b], $specNet, 'نظام مراقبة الشبكة الداخلية وإنذارات', 'نظام لمراقبة الأجهزة، عرض الحالة، وإنشاء تنبيهات عند الأعطال.', ['شبكات', 'أمان'], ProjectStatusEnum::Completed, 390);
        $this->proposal($proj3, $l3, $supervisor('n.abulbada@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 450, 'review_days' => 420]);
        $this->tasks($team3, $l3, [
            ['رسم خريطة الشبكة', TaskStatusEnum::Done, 'تحديد الأجهزة ونقاط الاتصال.'],
            ['بناء نظام المراقبة', TaskStatusEnum::Done, 'فحص حالة الأجهزة وإنشاء سجلات.'],
            ['نظام التنبيهات', TaskStatusEnum::Done, 'تنبيهات عند الخروج عن الحالة.'],
            ['تقارير ومراقبة', TaskStatusEnum::Done, 'تقارير دورية.'],
        ]);
        $this->meetings($team3, $supervisor('n.abulbada@ucas.edu.ps'), [
            ['انطلاق', -440, 'مناقشة.'],
            ['عرض', -425, 'عرض النموذج.'],
            ['نهائي', -415, 'مراجعة.'],
        ]);
        $this->finalReport($proj3, $l3, $supervisor('n.abulbada@ucas.edu.ps'), $committee, ['review' => 'approved', 'supervisor_days' => 405, 'committee_days' => 402]);
        if (! Discussion::withoutGlobalScopes()->where('project_id', $proj3->id)->exists()) {
            Discussion::create([
                'project_id' => $proj3->id,
                'supervisor_id' => $supervisor('n.abulbada@ucas.edu.ps')->id,
                'place' => 'قاعة المناقشات - مبنى أ',
                'discussion_date' => now()->subDays(395)->toDateString(),
                'discussion_time' => '12:00',
                'committee' => 'د. وليد بركة، د. فادي التاج، م. رامي قاسم',
                'whatsapp' => null,
                'status' => DiscussionStatusEnum::Confirmed,
                'term_id' => $this->termId,
            ]);
        }

        // past 4
        $l4 = $this->member(['name' => 'رامي الدرة', 'email' => 'rami.dorra@smail.ucas.edu.ps', 'gender' => 'male'], $specAi);
        $m4a = $this->member(['name' => 'هلا العجرمي', 'email' => 'hala.ajrami@smail.ucas.edu.ps', 'gender' => 'female'], $specAi);
        [$team4, $proj4] = $this->makeTeam('فريق نظام توصية الكتب الذكي', $supervisor('s.qouqa@ucas.edu.ps'), $l4, [$m4a], $specAi, 'نظام توصية كتب ذكي بناءً على اهتمامات الطالب', 'نظام يستخدم التصفية التعاونية لاقتراح الكتب المناسبة لكل طالب.', ['ذكاء اصطناعي', 'أنظمة توصية'], ProjectStatusEnum::Completed, 380);
        $this->proposal($proj4, $l4, $supervisor('s.qouqa@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 440, 'review_days' => 410]);
        $this->tasks($team4, $l4, [
            ['جمع البيانات وبناء النموذج', TaskStatusEnum::Done, 'تحليل سلوك القراءة وبناء نموذج التوصية.'],
            ['بناء النظام واختباره', TaskStatusEnum::Done, 'تطبيق النظام واختبار دقة التوصية.'],
            ['واجهة المستخدم والتقارير', TaskStatusEnum::Done, 'واجهة بسيطة مع اقتراحات مخصصة.'],
        ]);
        $this->meetings($team4, $supervisor('s.qouqa@ucas.edu.ps'), [
            ['انطلاق', -430, 'مناقشة.'],
            ['عرض', -415, 'عرض النموذج.'],
            ['نهائي', -405, 'مراجعة.'],
        ]);
        $this->finalReport($proj4, $l4, $supervisor('s.qouqa@ucas.edu.ps'), $committee, ['review' => 'approved', 'supervisor_days' => 395, 'committee_days' => 392]);
        if (! Discussion::withoutGlobalScopes()->where('project_id', $proj4->id)->exists()) {
            Discussion::create([
                'project_id' => $proj4->id,
                'supervisor_id' => $supervisor('s.qouqa@ucas.edu.ps')->id,
                'place' => 'قاعة المناقشات - مبنى ب',
                'discussion_date' => now()->subDays(385)->toDateString(),
                'discussion_time' => '09:30',
                'committee' => 'د. إيناس العبادلة، م. رشا العدوان، أ. منار حمدان',
                'whatsapp' => null,
                'status' => DiscussionStatusEnum::Confirmed,
                'term_id' => $this->termId,
            ]);
        }
    }
}
