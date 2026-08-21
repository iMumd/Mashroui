<?php

namespace Database\Seeders;

use App\Enums\DiscussionStatusEnum;
use App\Enums\ProjectStatusEnum;
use App\Enums\ProposalStatusEnum;
use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\AcademicTerm;
use App\Models\Discussion;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Specialization;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/** بيانات عرض واقعية — تُشغَّل بعد TestUsersSeeder وFeaturedProjectSeeder لإغناء الديمو الحي */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $termId = AcademicTerm::where('is_current', true)->value('id');
        $webSpec = Specialization::where('name', 'تصميم وتطوير مواقع الويب')->first();
        $dbSpec = Specialization::where('name', 'برمجيات وقواعد بيانات')->first();
        $mobileSpec = Specialization::where('name', 'تصميم وبرمجة تطبيقات الموبايل')->first();
        $mediaSpec = Specialization::where('name', 'تكنولوجيا الوسائط المتعددة')->where('degree', 'bachelor')->first();

        $supervisorMain = User::where('email', 'supervisor@mashroui.local')->first();
        $leaderMain = User::where('email', 'leader@mashroui.local')->first();
        $studentMain = User::where('email', 'student@mashroui.local')->first();

        $extraSupervisor = User::create([
            'name' => 'م. هبة الزعبي',
            'email' => 'h.zoubi@mashroui.local',
            'password' => 'password',
            'role' => RoleEnum::Supervisor,
            'specialization_id' => $mediaSpec->id,
        ]);

        // فريق 1: النظام الأساسي — قيد التنفيذ، مقترح معتمد، مهام متنوعة الحالة
        $leaderMain->update(['specialization_id' => $dbSpec->id, 'term_id' => $termId]);
        $studentMain->update(['specialization_id' => $dbSpec->id, 'term_id' => $termId]);

        $team1 = Team::create([
            'name' => 'فريق إدارة المخزون الذكي',
            'supervisor_id' => $supervisorMain->id,
            'specialization_id' => $dbSpec->id,
            'term_id' => $termId,
            'leader_id' => $leaderMain->id,
        ]);
        TeamMember::create(['team_id' => $team1->id, 'student_id' => $leaderMain->id, 'is_leader' => true]);
        TeamMember::create(['team_id' => $team1->id, 'student_id' => $studentMain->id, 'is_leader' => false]);

        $project1 = Project::create([
            'team_id' => $team1->id,
            'supervisor_id' => $supervisorMain->id,
            'name' => 'نظام إدارة المخزون الذكي بالباركود',
            'description' => 'نظام ويب لإدارة مخزون المحال التجارية الصغيرة، يدعم تتبّع المنتجات عبر الباركود، تنبيهات النفاد، وتقارير المبيعات اللحظية.',
            'department_id' => $dbSpec->department_id,
            'specialization_id' => $dbSpec->id,
            'term_id' => $termId,
            'status' => ProjectStatusEnum::InProgress,
        ]);

        Proposal::create([
            'project_id' => $project1->id,
            'name' => $project1->name,
            'description' => $project1->description,
            'problems' => 'المحال الصغيرة تعتمد على السجلات الورقية أو ملفات إكسل لإدارة المخزون، ما يسبب أخطاء بالجرد ونفاد مفاجئ للمنتجات.',
            'solutions' => 'نظام ويب متكامل يربط نقاط البيع بالمخزون مباشرة، مع مسح الباركود وتنبيهات تلقائية عند اقتراب النفاد.',
            'features_value' => 'مسح باركود، تقارير مبيعات، تنبيهات نفاد، دعم أكثر من فرع.',
            'pdf_path' => $this->placeholderPdf('proposals', 'مقترح-مخزون-ذكي.pdf'),
            'status' => ProposalStatusEnum::Approved,
            'submitted_by' => $leaderMain->id,
            'reviewed_by' => $supervisorMain->id,
        ]);

        foreach ([
            ['تحليل المتطلبات وتصميم قاعدة البيانات', TaskStatusEnum::Done],
            ['تصميم واجهات المستخدم (UI/UX)', TaskStatusEnum::Done],
            ['بناء نظام تسجيل الدخول والصلاحيات', TaskStatusEnum::Done],
            ['تطوير وحدة إدارة المنتجات والباركود', TaskStatusEnum::InProgress],
            ['ربط نقاط البيع بالمخزون', TaskStatusEnum::InProgress],
            ['بناء تقارير المبيعات والتنبيهات', TaskStatusEnum::Review],
            ['اختبار النظام وإصلاح الأخطاء', TaskStatusEnum::Pending],
        ] as [$title, $status]) {
            Task::create([
                'team_id' => $team1->id,
                'title' => $title,
                'status' => $status,
                'created_by' => $leaderMain->id,
            ]);
        }

        Meeting::create([
            'team_id' => $team1->id,
            'title' => 'متابعة تقدّم الوحدة الثانية',
            'scheduled_at' => now()->subDays(4)->setTime(11, 0),
            'google_meet_link' => 'https://meet.google.com/xyz-abcd-efg',
            'notes' => 'راجعنا واجهات المنتجات، طلب المشرف تحسين تجربة البحث.',
            'created_by' => $supervisorMain->id,
        ]);
        Meeting::create([
            'team_id' => $team1->id,
            'title' => 'مراجعة ربط نقاط البيع بالمخزون',
            'scheduled_at' => now()->addDays(3)->setTime(12, 30),
            'google_meet_link' => 'https://meet.google.com/lmn-opqr-stu',
            'notes' => 'تحضير عرض توضيحي لآلية التنبيهات قبل الاجتماع.',
            'created_by' => $supervisorMain->id,
        ]);

        // فريق 2: مقترح قيد المراجعة — يوضّح مسار الاعتماد
        $leader2 = User::create([
            'name' => 'يزن أبو حمّاد', 'email' => 'yazan.leader@mashroui.local',
            'password' => 'password', 'role' => RoleEnum::TeamLeader,
            'specialization_id' => $webSpec->id, 'term_id' => $termId,
        ]);
        $member2 = User::create([
            'name' => 'دانة الحوراني', 'email' => 'dana.member@mashroui.local',
            'password' => 'password', 'role' => RoleEnum::Student,
            'specialization_id' => $webSpec->id, 'term_id' => $termId,
        ]);

        $team2 = Team::create([
            'name' => 'فريق حجز المواعيد الطبية',
            'supervisor_id' => $supervisorMain->id,
            'specialization_id' => $webSpec->id,
            'term_id' => $termId,
            'leader_id' => $leader2->id,
        ]);
        TeamMember::create(['team_id' => $team2->id, 'student_id' => $leader2->id, 'is_leader' => true]);
        TeamMember::create(['team_id' => $team2->id, 'student_id' => $member2->id, 'is_leader' => false]);

        $project2 = Project::create([
            'team_id' => $team2->id,
            'supervisor_id' => $supervisorMain->id,
            'name' => 'منصة حجز المواعيد الطبية',
            'description' => 'منصة ويب تتيح للمرضى حجز مواعيد مع العيادات مباشرة، مع تذكيرات تلقائية وسجل طبي مبسّط.',
            'department_id' => $webSpec->department_id,
            'specialization_id' => $webSpec->id,
            'term_id' => $termId,
            'status' => ProjectStatusEnum::Proposed,
        ]);

        Proposal::create([
            'project_id' => $project2->id,
            'name' => $project2->name,
            'description' => $project2->description,
            'problems' => 'حجز المواعيد بالعيادات الصغيرة ما زال هاتفياً، ما يسبب ازدحام وتضارب بالمواعيد.',
            'solutions' => 'منصة حجز إلكترونية بواجهة بسيطة للمريض والعيادة معاً، مع تذكير تلقائي عبر واتساب.',
            'features_value' => 'حجز فوري، تذكيرات واتساب، لوحة تحكّم للعيادة.',
            'pdf_path' => $this->placeholderPdf('proposals', 'مقترح-حجز-طبي.pdf'),
            'status' => ProposalStatusEnum::Pending,
            'submitted_by' => $leader2->id,
        ]);

        // فريق 3: مقترح مرفوض — يوضّح مسار الرفض وإعادة التقديم
        $leader3 = User::create([
            'name' => 'كرم صوالحة', 'email' => 'karam.leader@mashroui.local',
            'password' => 'password', 'role' => RoleEnum::TeamLeader,
            'specialization_id' => $mobileSpec->id, 'term_id' => $termId,
        ]);
        $member3 = User::create([
            'name' => 'رغد النجار', 'email' => 'raghad.member@mashroui.local',
            'password' => 'password', 'role' => RoleEnum::Student,
            'specialization_id' => $mobileSpec->id, 'term_id' => $termId,
        ]);

        $team3 = Team::create([
            'name' => 'فريق تعلّم اللغات بالواقع المعزز',
            'supervisor_id' => $supervisorMain->id,
            'specialization_id' => $mobileSpec->id,
            'term_id' => $termId,
            'leader_id' => $leader3->id,
        ]);
        TeamMember::create(['team_id' => $team3->id, 'student_id' => $leader3->id, 'is_leader' => true]);
        TeamMember::create(['team_id' => $team3->id, 'student_id' => $member3->id, 'is_leader' => false]);

        $project3 = Project::create([
            'team_id' => $team3->id,
            'supervisor_id' => $supervisorMain->id,
            'name' => 'تطبيق تعلّم اللغات بالواقع المعزز',
            'description' => 'تطبيق موبايل يعلّم المفردات عبر تراكب عناصر ثلاثية الأبعاد فوق الأجسام الحقيقية بالكاميرا.',
            'department_id' => $mobileSpec->department_id,
            'specialization_id' => $mobileSpec->id,
            'term_id' => $termId,
            'status' => ProjectStatusEnum::Proposed,
        ]);

        Proposal::create([
            'project_id' => $project3->id,
            'name' => $project3->name,
            'description' => $project3->description,
            'problems' => 'تطبيقات تعلّم اللغات الحالية تعتمد على البطاقات النصية فقط، وتفتقر للتفاعل البصري.',
            'solutions' => 'استخدام تقنية الواقع المعزز لعرض الكلمة ومعناها فوق الجسم الحقيقي مباشرة عبر الكاميرا.',
            'features_value' => 'تعلّم تفاعلي، دعم عدة لغات، تتبّع تقدّم المستخدم.',
            'pdf_path' => $this->placeholderPdf('proposals', 'مقترح-تعلم-لغات.pdf'),
            'status' => ProposalStatusEnum::Rejected,
            'rejection_reason' => 'النطاق واسع جداً لمدة الفصل المتاحة، يرجى تضييق التركيز على لغة واحدة ومجموعة مفردات محدودة أولاً.',
            'submitted_by' => $leader3->id,
            'reviewed_by' => $supervisorMain->id,
        ]);

        // فريق 4: قسم الوسائط — قيد التنفيذ تحت مشرفة مختلفة
        $leader4 = User::create([
            'name' => 'ليان بدارنة', 'email' => 'layan.leader@mashroui.local',
            'password' => 'password', 'role' => RoleEnum::TeamLeader,
            'specialization_id' => $mediaSpec->id, 'term_id' => $termId,
        ]);
        $member4 = User::create([
            'name' => 'عمر شاهين', 'email' => 'omar.member@mashroui.local',
            'password' => 'password', 'role' => RoleEnum::Student,
            'specialization_id' => $mediaSpec->id, 'term_id' => $termId,
        ]);

        $team4 = Team::create([
            'name' => 'فريق إنتاج المحتوى التعليمي',
            'supervisor_id' => $extraSupervisor->id,
            'specialization_id' => $mediaSpec->id,
            'term_id' => $termId,
            'leader_id' => $leader4->id,
        ]);
        TeamMember::create(['team_id' => $team4->id, 'student_id' => $leader4->id, 'is_leader' => true]);
        TeamMember::create(['team_id' => $team4->id, 'student_id' => $member4->id, 'is_leader' => false]);

        $project4 = Project::create([
            'team_id' => $team4->id,
            'supervisor_id' => $extraSupervisor->id,
            'name' => 'منصة إنتاج فيديوهات تعليمية تفاعلية',
            'description' => 'منصة تتيح للمدرّسين إنتاج فيديوهات تعليمية بمؤثرات حركية وأسئلة تفاعلية أثناء العرض.',
            'department_id' => $mediaSpec->department_id,
            'specialization_id' => $mediaSpec->id,
            'term_id' => $termId,
            'status' => ProjectStatusEnum::InProgress,
        ]);

        Proposal::create([
            'project_id' => $project4->id,
            'name' => $project4->name,
            'description' => $project4->description,
            'problems' => 'إنتاج الفيديو التعليمي التفاعلي يتطلب أدوات مونتاج متعددة ومعقّدة للمدرّس غير المتخصص.',
            'solutions' => 'أداة واحدة مبسّطة تجمع التسجيل والمونتاج وإدراج الأسئلة التفاعلية بخطوات قليلة.',
            'features_value' => 'مونتاج مبسّط، أسئلة تفاعلية أثناء الفيديو، تصدير مباشر لمنصات التعلم.',
            'pdf_path' => $this->placeholderPdf('proposals', 'مقترح-فيديوهات-تعليمية.pdf'),
            'status' => ProposalStatusEnum::Approved,
            'submitted_by' => $leader4->id,
            'reviewed_by' => $extraSupervisor->id,
        ]);

        foreach ([
            ['تصميم واجهة أداة التسجيل', TaskStatusEnum::Done],
            ['بناء محرّك المونتاج الأساسي', TaskStatusEnum::Done],
            ['إضافة الأسئلة التفاعلية أثناء العرض', TaskStatusEnum::InProgress],
            ['دعم التصدير لمنصات التعلم', TaskStatusEnum::Pending],
        ] as [$title, $status]) {
            Task::create([
                'team_id' => $team4->id,
                'title' => $title,
                'status' => $status,
                'created_by' => $leader4->id,
            ]);
        }

        // جلسة مناقشة للمشروع المميز (المكتمل) — لعرض صفحة مواعيد المناقشات
        $featuredProject = Project::where('is_featured', true)->first();
        if ($featuredProject) {
            Discussion::create([
                'project_id' => $featuredProject->id,
                'supervisor_id' => $featuredProject->supervisor_id,
                'place' => 'قاعة المناقشات الرئيسية - مبنى B',
                'discussion_date' => now()->subDays(2)->toDateString(),
                'discussion_time' => '10:00:00',
                'committee' => 'د. سامر خليل، أ. هبة الزعبي، أ. رامي قاسم',
                'whatsapp' => null,
                'status' => DiscussionStatusEnum::Confirmed,
                'term_id' => $termId,
            ]);
        }
    }

    private function placeholderPdf(string $folder, string $displayName): string
    {
        $path = $folder.'/'.uniqid().'.pdf';
        Storage::put($path, "%PDF-1.4\n% ملف عرض توضيحي — $displayName\n%%EOF");

        return $path;
    }
}
