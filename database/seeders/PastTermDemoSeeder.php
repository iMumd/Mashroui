<?php

namespace Database\Seeders;

use App\Enums\ProjectStatusEnum;
use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\AcademicTerm;
use App\Models\Specialization;
use App\Models\User;

/** بيانات سنة دراسية سابقة مكتملة بالكامل — كل مشاريعها تخرّجت، لعرض الأرشيف التاريخي وعزل السنوات */
class PastTermDemoSeeder extends DemoDataSeeder
{
    public function run(): void
    {
        $this->termId = AcademicTerm::where('name', 'السنة الدراسية 2025/2026')->value('id');
        $this->studentSeq = 400;

        $web = Specialization::where('name', 'تصميم وتطوير مواقع الويب')->first();
        $db = Specialization::where('name', 'برمجيات وقواعد بيانات')->first();
        $mobile = Specialization::where('name', 'تصميم وبرمجة تطبيقات الموبايل')->first();
        $mediaBachelor = Specialization::where('name', 'تكنولوجيا الوسائط المتعددة')->where('degree', 'bachelor')->first();

        $supYousef = $this->upsertUser($this->staffEmail('t2-y.hammad@mashroui.local'), [
            'name' => 'د. يوسف حماد', 'password' => 'password', 'role' => RoleEnum::Supervisor,
            'gender' => 'male', 'specialization_id' => $db->id,
            'term_id' => $this->termId, 'whatsapp' => '970562298001', 'employee_number' => 'EMP-2001',
        ], 'EMP-2001');
        $supRania = $this->upsertUser($this->staffEmail('t2-r.sobh@mashroui.local'), [
            'name' => 'م. رانيا صبح', 'password' => 'password', 'role' => RoleEnum::Supervisor,
            'gender' => 'female', 'specialization_id' => $web->id,
            'term_id' => $this->termId, 'whatsapp' => '970592298002', 'employee_number' => 'EMP-2002',
        ], 'EMP-2002');

        // فريق 1: نظام إدارة عيادات بيطرية — مكتمل
        $leader1 = $this->leader('سليم قعدان', 't2-salim.leader@mashroui.local', $db->id);
        $mem1 = $this->student('ياسمين حرب', 't2-yasmin.member@mashroui.local', $db->id);

        [$team1, $project1] = $this->makeTeam(
            'فريق إدارة العيادات البيطرية', $supYousef, $leader1, [$mem1], $db,
            'نظام إدارة العيادات البيطرية',
            'نظام ويب يدير سجلات المرضى من الحيوانات، مواعيد الفحص، والتطعيمات الدورية للعيادات البيطرية الصغيرة.',
            ProjectStatusEnum::Completed, completedAt: now()->subMonths(7),
        );
        $this->approvedProposal($project1, $leader1, $supYousef,
            'مقترح-عيادات-بيطرية.pdf',
            'العيادات البيطرية الصغيرة تدير سجلات المرضى ورقياً، ما يصعّب متابعة مواعيد التطعيم الدورية.',
            'نظام مركزي لسجلات المرضى مع تذكيرات تلقائية بمواعيد التطعيم والمتابعة.',
            'سجل طبي رقمي، تذكيرات تطعيم، تقارير دورية للعيادة.',
        );
        $this->tasks($team1, $leader1, [
            ['تصميم قاعدة بيانات السجلات الطبية', TaskStatusEnum::Done],
            ['بناء واجهة استقبال الحالات', TaskStatusEnum::Done],
            ['نظام تذكيرات التطعيم', TaskStatusEnum::Done],
        ]);
        $this->meetings($team1, $supYousef, [
            ['اعتماد تصميم السجل الطبي', now()->subMonths(7)->subDays(5), 'تمت الموافقة على الحقول الأساسية للسجل.'],
        ]);
        $this->finalReport($project1, $leader1, 'التقرير-النهائي-عيادات-بيطرية.pdf', self::DEMO_VIDEOS[0]);
        $this->discussion($project1, $supYousef, 'قاعة المناقشات الرئيسية - مبنى A', now()->subMonths(7)->addDays(2), '10:00:00',
            'د. يوسف حماد، م. رانيا صبح، أ. مالك الأحمد');

        // فريق 2: منصة تأجير الكتب الجامعية — مكتمل
        $leader2 = $this->leader('نور دويكات', 't2-nour.leader@mashroui.local', $web->id);
        $mem2a = $this->student('همام صالح', 't2-hammam.member@mashroui.local', $web->id);
        $mem2b = $this->student('رزان عودة', 't2-razan.member@mashroui.local', $web->id);

        [$team2, $project2] = $this->makeTeam(
            'فريق تأجير الكتب الجامعية', $supRania, $leader2, [$mem2a, $mem2b], $web,
            'منصة تأجير وتبادل الكتب الجامعية',
            'منصة تتيح للطلاب تأجير أو تبادل الكتب الجامعية المستعملة بدل الشراء الكامل كل فصل.',
            ProjectStatusEnum::Completed, completedAt: now()->subMonths(8),
        );
        $this->approvedProposal($project2, $leader2, $supRania,
            'مقترح-تأجير-كتب.pdf',
            'الطلاب يشترون كتب جديدة كل فصل رغم استخدامها لفصل واحد فقط، ما يشكّل عبئاً مالياً.',
            'منصة تربط الطلاب لتأجير أو تبادل الكتب المستعملة بأسعار رمزية بدل الشراء الكامل.',
            'قائمة كتب متاحة، حجز وتأجير مباشر، تقييم لحالة الكتاب.',
        );
        $this->tasks($team2, $leader2, [
            ['تصميم واجهة قائمة الكتب', TaskStatusEnum::Done],
            ['نظام الحجز والتأجير', TaskStatusEnum::Done],
            ['تقييم حالة الكتب والمستخدمين', TaskStatusEnum::Done],
        ]);
        $this->finalReport($project2, $leader2, 'التقرير-النهائي-تأجير-كتب.pdf', self::DEMO_VIDEOS[1]);
        $this->discussion($project2, $supRania, 'قاعة المناقشات - مبنى B', now()->subMonths(8)->addDays(3), '11:30:00',
            'م. رانيا صبح، د. يوسف حماد، أ. سليم قعدان');

        // فريق 3: تطبيق متابعة اللياقة الجامعي — مكتمل
        $leader3 = $this->leader('علاء زيد', 't2-alaa.leader@mashroui.local', $mobile->id);
        $mem3 = $this->student('ريم شريف', 't2-reem.member@mashroui.local', $mobile->id);

        [$team3, $project3] = $this->makeTeam(
            'فريق متابعة اللياقة الجامعي', $supYousef, $leader3, [$mem3], $mobile,
            'تطبيق متابعة اللياقة البدنية للطلاب',
            'تطبيق موبايل يتابع نشاط الطلاب الرياضي داخل صالة الجامعة الرياضية مع خطط تمرين مقترحة.',
            ProjectStatusEnum::Completed, completedAt: now()->subMonths(6),
        );
        $this->approvedProposal($project3, $leader3, $supYousef,
            'مقترح-متابعة-لياقة.pdf',
            'صالة الجامعة الرياضية بلا نظام رقمي لمتابعة تقدّم الطلاب الرياضي أو اقتراح خطط مناسبة.',
            'تطبيق يسجّل التمارين اليومية ويقترح خطط أسبوعية حسب الهدف (لياقة، تخسيس، بناء عضلي).',
            'تسجيل تمارين يومي، خطط مقترحة، رسوم بيانية للتقدّم.',
        );
        $this->tasks($team3, $leader3, [
            ['تصميم واجهة تسجيل التمارين', TaskStatusEnum::Done],
            ['محرّك اقتراح الخطط الأسبوعية', TaskStatusEnum::Done],
        ]);
        $this->finalReport($project3, $leader3, 'التقرير-النهائي-متابعة-لياقة.pdf', self::DEMO_VIDEOS[2]);
        $this->discussion($project3, $supYousef, 'قاعة المناقشات الرئيسية - مبنى A', now()->subMonths(6)->addDays(1), '09:00:00',
            'د. يوسف حماد، م. رانيا صبح، أ. نور دويكات');

        // فريق 4: أرشفة فعاليات نادي الإعلام — مكتمل
        $leader4 = $this->leader('سيرين عابد', 't2-sereen.leader@mashroui.local', $mediaBachelor->id);
        $mem4 = $this->student('باسل النجار', 't2-basel.member@mashroui.local', $mediaBachelor->id);

        [$team4, $project4] = $this->makeTeam(
            'فريق أرشفة فعاليات نادي الإعلام', $supRania, $leader4, [$mem4], $mediaBachelor,
            'منصة أرشفة إنتاج نادي الإعلام الطلابي',
            'منصة تنظّم وتؤرشف إنتاج نادي الإعلام الطلابي من فيديوهات وتصاميم عبر مواسم النشاط المختلفة.',
            ProjectStatusEnum::Completed, completedAt: now()->subMonths(9),
        );
        $this->approvedProposal($project4, $leader4, $supRania,
            'مقترح-أرشفة-نادي-إعلام.pdf',
            'إنتاج نادي الإعلام الطلابي مبعثر بين حسابات تخزين شخصية بلا تنظيم موحّد.',
            'منصة أرشفة مركزية مصنّفة حسب الموسم ونوع المحتوى مع صلاحيات مشاركة مرنة.',
            'أرشفة موسمية، تصنيف حسب النوع، مشاركة مرنة بين الأعضاء.',
        );
        $this->tasks($team4, $leader4, [
            ['تصميم بنية الأرشيف الموسمي', TaskStatusEnum::Done],
            ['واجهة الرفع والتصنيف', TaskStatusEnum::Done],
        ]);
        $this->finalReport($project4, $leader4, 'التقرير-النهائي-أرشفة-اعلام.pdf', self::DEMO_VIDEOS[3]);
        $this->discussion($project4, $supRania, 'قاعة المناقشات - مبنى B', now()->subMonths(9)->addDays(4), '12:00:00',
            'م. رانيا صبح، د. يوسف حماد، أ. علاء زيد');
    }
}
