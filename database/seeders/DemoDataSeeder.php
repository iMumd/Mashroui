<?php

namespace Database\Seeders;

use App\Enums\DiscussionStatusEnum;
use App\Enums\ProjectStatusEnum;
use App\Enums\ProposalStatusEnum;
use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\AcademicTerm;
use App\Models\Discussion;
use App\Models\FinalReport;
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

/** بيانات عرض واقعية وموسّعة — تُشغَّل بعد TestUsersSeeder وFeaturedProjectSeeder لإغناء الديمو الحي */
class DemoDataSeeder extends Seeder
{
    /** فيديوهات حقيقية قابلة للتشغيل (Blender Foundation، مفتوحة المصدر) تُستخدم كعرض نهائي توضيحي */
    protected const DEMO_VIDEOS = [
        'https://www.youtube.com/watch?v=YE7VzlLtp-4',
        'https://www.youtube.com/watch?v=eRsGyueVLvQ',
        'https://www.youtube.com/watch?v=R6MlUcmOul8',
        'https://www.youtube.com/watch?v=TLkA0RELQ1g',
    ];

    protected ?int $termId = null;

    protected array $spec = [];

    public function run(): void
    {
        $this->termId = AcademicTerm::where('is_current', true)->value('id');

        foreach (Specialization::with('department')->get() as $s) {
            $this->spec[$s->name.'|'.$s->degree->value] = $s;
        }
        $web = Specialization::where('name', 'تصميم وتطوير مواقع الويب')->first();
        $db = Specialization::where('name', 'برمجيات وقواعد بيانات')->first();
        $mobile = Specialization::where('name', 'تصميم وبرمجة تطبيقات الموبايل')->first();
        $mediaDiploma = Specialization::where('name', 'تكنولوجيا الوسائط المتعددة')->where('degree', 'diploma')->first();
        $mediaBachelor = Specialization::where('name', 'تكنولوجيا الوسائط المتعددة')->where('degree', 'bachelor')->first();

        $supervisorMain = User::where('email', 'supervisor@mashroui.local')->first();
        $leaderMain = User::where('email', 'leader@mashroui.local')->first();
        $studentMain = User::where('email', 'student@mashroui.local')->first();

        $supHeba = User::create([
            'name' => 'م. هبة الزعبي', 'email' => 'h.zoubi@mashroui.local',
            'password' => 'password', 'role' => RoleEnum::Supervisor, 'specialization_id' => $mediaBachelor->id,
            'whatsapp' => '970569234481', 'employee_number' => 'EMP-1005',
        ]);
        $supRami = User::create([
            'name' => 'م. رامي قاسم', 'email' => 'r.qasem@mashroui.local',
            'password' => 'password', 'role' => RoleEnum::Supervisor, 'specialization_id' => $db->id,
            'whatsapp' => '970525871123', 'employee_number' => 'EMP-1006',
        ]);
        $supNour = User::create([
            'name' => 'د. نور الشريف', 'email' => 'n.sharif@mashroui.local',
            'password' => 'password', 'role' => RoleEnum::Supervisor, 'specialization_id' => $web->id,
            'whatsapp' => '970542298761', 'employee_number' => 'EMP-1007',
        ]);

        // ===== فريق 1: النظام الأساسي (حسابات الاختبار) — قيد التنفيذ =====
        $leaderMain->update(['specialization_id' => $db->id, 'term_id' => $this->termId]);
        $studentMain->update(['specialization_id' => $db->id, 'term_id' => $this->termId]);
        $extra1 = $this->student('سيف قطناني', 'saif.member@mashroui.local', $db->id);

        [$team1, $project1] = $this->makeTeam(
            name: 'فريق إدارة المخزون الذكي',
            supervisor: $supervisorMain,
            leader: $leaderMain,
            members: [$studentMain, $extra1],
            spec: $db,
            projectName: 'نظام إدارة المخزون الذكي بالباركود',
            description: 'نظام ويب لإدارة مخزون المحال التجارية الصغيرة، يدعم تتبّع المنتجات عبر الباركود، تنبيهات النفاد، وتقارير المبيعات اللحظية.',
            status: ProjectStatusEnum::InProgress,
        );
        $this->approvedProposal($project1, $leaderMain, $supervisorMain,
            'مقترح-مخزون-ذكي.pdf',
            'المحال الصغيرة تعتمد على السجلات الورقية أو ملفات إكسل لإدارة المخزون، ما يسبب أخطاء بالجرد ونفاد مفاجئ للمنتجات.',
            'نظام ويب متكامل يربط نقاط البيع بالمخزون مباشرة، مع مسح الباركود وتنبيهات تلقائية عند اقتراب النفاد.',
            'مسح باركود، تقارير مبيعات، تنبيهات نفاد، دعم أكثر من فرع.',
        );
        $this->tasks($team1, $leaderMain, [
            ['تحليل المتطلبات وتصميم قاعدة البيانات', TaskStatusEnum::Done],
            ['تصميم واجهات المستخدم (UI/UX)', TaskStatusEnum::Done],
            ['بناء نظام تسجيل الدخول والصلاحيات', TaskStatusEnum::Done],
            ['تطوير وحدة إدارة المنتجات والباركود', TaskStatusEnum::InProgress],
            ['ربط نقاط البيع بالمخزون', TaskStatusEnum::InProgress],
            ['بناء تقارير المبيعات والتنبيهات', TaskStatusEnum::Review],
            ['اختبار النظام وإصلاح الأخطاء', TaskStatusEnum::Pending],
        ]);
        $this->meetings($team1, $supervisorMain, [
            ['متابعة تقدّم الوحدة الثانية', now()->subDays(9), 'راجعنا واجهات المنتجات، طلب المشرف تحسين تجربة البحث.'],
            ['مراجعة ربط نقاط البيع بالمخزون', now()->subDays(2), 'تحضير عرض توضيحي لآلية التنبيهات قبل الاجتماع.'],
            ['متابعة اختبار النظام', now()->addDays(5), null],
        ]);

        // ===== فريق 2: حجز المواعيد الطبية — مقترح قيد المراجعة =====
        $leader2 = $this->leader('يزن أبو حمّاد', 'yazan.leader@mashroui.local', $web->id);
        $mem2a = $this->student('دانة الحوراني', 'dana.member@mashroui.local', $web->id);
        $mem2b = $this->student('فراس دراوشة', 'firas.member@mashroui.local', $web->id);

        [$team2, $project2] = $this->makeTeam(
            'فريق حجز المواعيد الطبية', $supNour, $leader2, [$mem2a, $mem2b], $web,
            'منصة حجز المواعيد الطبية',
            'منصة ويب تتيح للمرضى حجز مواعيد مع العيادات مباشرة، مع تذكيرات تلقائية وسجل طبي مبسّط.',
            ProjectStatusEnum::Proposed,
        );
        $this->pendingProposal($project2, $leader2,
            'مقترح-حجز-طبي.pdf',
            'حجز المواعيد بالعيادات الصغيرة ما زال هاتفياً، ما يسبب ازدحام وتضارب بالمواعيد.',
            'منصة حجز إلكترونية بواجهة بسيطة للمريض والعيادة معاً، مع تذكير تلقائي عبر واتساب.',
            'حجز فوري، تذكيرات واتساب، لوحة تحكّم للعيادة.',
        );
        $this->tasks($team2, $leader2, [
            ['دراسة تجربة الحجز الحالية بالعيادات', TaskStatusEnum::Done],
            ['تصميم قاعدة بيانات المواعيد والأطباء', TaskStatusEnum::InProgress],
        ]);

        // ===== فريق 3: تعلّم اللغات بالواقع المعزز — مقترح مرفوض =====
        $leader3 = $this->leader('كرم صوالحة', 'karam.leader@mashroui.local', $mobile->id);
        $mem3 = $this->student('رغد النجار', 'raghad.member@mashroui.local', $mobile->id);

        [$team3, $project3] = $this->makeTeam(
            'فريق تعلّم اللغات بالواقع المعزز', $supervisorMain, $leader3, [$mem3], $mobile,
            'تطبيق تعلّم اللغات بالواقع المعزز',
            'تطبيق موبايل يعلّم المفردات عبر تراكب عناصر ثلاثية الأبعاد فوق الأجسام الحقيقية بالكاميرا.',
            ProjectStatusEnum::Proposed,
        );
        $this->rejectedProposal($project3, $leader3, $supervisorMain,
            'مقترح-تعلم-لغات.pdf',
            'تطبيقات تعلّم اللغات الحالية تعتمد على البطاقات النصية فقط، وتفتقر للتفاعل البصري.',
            'استخدام تقنية الواقع المعزز لعرض الكلمة ومعناها فوق الجسم الحقيقي مباشرة عبر الكاميرا.',
            'تعلّم تفاعلي، دعم عدة لغات، تتبّع تقدّم المستخدم.',
            'النطاق واسع جداً لمدة الفصل المتاحة، يرجى تضييق التركيز على لغة واحدة ومجموعة مفردات محدودة أولاً.',
        );

        // ===== فريق 4: إنتاج محتوى تعليمي — مكتمل =====
        $leader4 = $this->leader('ليان بدارنة', 'layan.leader@mashroui.local', $mediaBachelor->id);
        $mem4a = $this->student('عمر شاهين', 'omar.member@mashroui.local', $mediaBachelor->id);
        $mem4b = $this->student('هديل مساعدة', 'hadeel.member@mashroui.local', $mediaBachelor->id);

        [$team4, $project4] = $this->makeTeam(
            'فريق إنتاج المحتوى التعليمي', $supHeba, $leader4, [$mem4a, $mem4b], $mediaBachelor,
            'منصة إنتاج فيديوهات تعليمية تفاعلية',
            'منصة تتيح للمدرّسين إنتاج فيديوهات تعليمية بمؤثرات حركية وأسئلة تفاعلية أثناء العرض.',
            ProjectStatusEnum::Completed, completedAt: now()->subDays(6),
        );
        $this->approvedProposal($project4, $leader4, $supHeba,
            'مقترح-فيديوهات-تعليمية.pdf',
            'إنتاج الفيديو التعليمي التفاعلي يتطلب أدوات مونتاج متعددة ومعقّدة للمدرّس غير المتخصص.',
            'أداة واحدة مبسّطة تجمع التسجيل والمونتاج وإدراج الأسئلة التفاعلية بخطوات قليلة.',
            'مونتاج مبسّط، أسئلة تفاعلية أثناء الفيديو، تصدير مباشر لمنصات التعلم.',
        );
        $this->tasks($team4, $leader4, [
            ['تصميم واجهة أداة التسجيل', TaskStatusEnum::Done],
            ['بناء محرّك المونتاج الأساسي', TaskStatusEnum::Done],
            ['إضافة الأسئلة التفاعلية أثناء العرض', TaskStatusEnum::Done],
            ['دعم التصدير لمنصات التعلم', TaskStatusEnum::Done],
            ['اختبار قبول المستخدم النهائي', TaskStatusEnum::Done],
        ]);
        $this->meetings($team4, $supHeba, [
            ['مراجعة أولى لمحرّك المونتاج', now()->subDays(20), 'اعتماد الشكل النهائي لواجهة المونتاج.'],
            ['مراجعة نهائية قبل التسليم', now()->subDays(8), 'كل الميزات جاهزة، بانتظار تسجيل الفيديو التعريفي.'],
        ]);
        $this->finalReport($project4, $leader4, 'التقرير-النهائي-فيديوهات-تعليمية.pdf', self::DEMO_VIDEOS[0]);
        $this->discussion($project4, $supHeba, 'قاعة المناقشات - مبنى B', now()->subDays(3), '11:00:00',
            'د. نور الشريف، م. رامي قاسم، أ. سيف قطناني');

        // ===== فريق 5: حجز قاعات الجامعة — مكتمل =====
        $leader5 = $this->leader('مالك الأحمد', 'malek.leader@mashroui.local', $db->id);
        $mem5a = $this->student('شهد ياسين', 'shahd.member@mashroui.local', $db->id);
        $mem5b = $this->student('وسيم برهم', 'waseem.member@mashroui.local', $db->id);

        [$team5, $project5] = $this->makeTeam(
            'فريق حجز القاعات الجامعية', $supRami, $leader5, [$mem5a, $mem5b], $db,
            'نظام حجز القاعات الجامعية الذكي',
            'نظام ويب يتيح للطلاب وأعضاء الهيئة التدريسية حجز القاعات والمختبرات إلكترونياً، مع منع التعارض بالمواعيد تلقائياً.',
            ProjectStatusEnum::Completed, completedAt: now()->subDays(14),
        );
        $this->approvedProposal($project5, $leader5, $supRami,
            'مقترح-حجز-قاعات.pdf',
            'حجز القاعات حالياً يتم عبر التنسيق اليدوي مع إدارة الكلية، ما يؤدي أحياناً لتعارض الحجوزات.',
            'نظام حجز إلكتروني بتقويم موحّد يمنع التعارض تلقائياً ويرسل تأكيد فوري لصاحب الحجز.',
            'تقويم تفاعلي، منع تعارض تلقائي، تقارير استخدام القاعات.',
        );
        $this->tasks($team5, $leader5, [
            ['تصميم قاعدة بيانات القاعات والحجوزات', TaskStatusEnum::Done],
            ['بناء واجهة التقويم التفاعلي', TaskStatusEnum::Done],
            ['منطق منع تعارض الحجوزات', TaskStatusEnum::Done],
            ['تقارير الاستخدام لإدارة الكلية', TaskStatusEnum::Done],
        ]);
        $this->meetings($team5, $supRami, [
            ['اعتماد تصميم قاعدة البيانات', now()->subDays(25), 'تمت الموافقة على المخطط بعد تعديل طفيف على جدول الحجوزات.'],
        ]);
        $this->finalReport($project5, $leader5, 'التقرير-النهائي-حجز-قاعات.pdf', self::DEMO_VIDEOS[1]);
        $this->discussion($project5, $supRami, 'قاعة المناقشات الرئيسية - مبنى A', now()->subDays(10), '09:30:00',
            'د. سامر خليل، م. هبة الزعبي، د. نور الشريف');

        // ===== فريق 6: توصيل الطلبات الجامعية — قيد التنفيذ =====
        $leader6 = $this->leader('جود عبدالله', 'joud.leader@mashroui.local', $web->id);
        $mem6 = $this->student('تالا حجازي', 'tala.member@mashroui.local', $web->id);

        [$team6, $project6] = $this->makeTeam(
            'فريق توصيل الطلبات الجامعية', $supNour, $leader6, [$mem6], $web,
            'تطبيق توصيل الطلبات داخل الحرم الجامعي',
            'تطبيق ويب وموبايل يتيح للطلاب طلب وجبات وقرطاسية من كافيتريات ومكتبة الجامعة وتوصيلها لمكانهم داخل الحرم.',
            ProjectStatusEnum::InProgress,
        );
        $this->approvedProposal($project6, $leader6, $supNour,
            'مقترح-توصيل-جامعي.pdf',
            'الطلاب يضيّعون وقت المحاضرات بالوقوف بطوابير الكافيتريات وقت الذروة.',
            'تطبيق طلب وتوصيل داخلي بشبكة مندوبين من الطلاب أنفسهم مقابل رسوم رمزية.',
            'تتبّع الطلب لحظياً، دفع إلكتروني، نظام تقييم للمندوبين.',
        );
        $this->tasks($team6, $leader6, [
            ['تصميم واجهات الطلب والتتبّع', TaskStatusEnum::Done],
            ['بناء نظام إدارة الطلبات للكافيتريا', TaskStatusEnum::InProgress],
            ['ربط بوابة الدفع الإلكتروني', TaskStatusEnum::Pending],
            ['نظام تقييم المندوبين', TaskStatusEnum::Pending],
        ]);
        $this->meetings($team6, $supNour, [
            ['مراجعة واجهات الطلب', now()->subDays(5), 'طلبت المشرفة تبسيط خطوات إتمام الطلب.'],
            ['متابعة ربط بوابة الدفع', now()->addDays(4), null],
        ]);

        // ===== فريق 7: تقييم أداء الموظفين — مكتمل =====
        $leader7 = $this->leader('آدم صالح', 'adam.leader@mashroui.local', $db->id);
        $mem7 = $this->student('لينا خطيب', 'lina.member@mashroui.local', $db->id);

        [$team7, $project7] = $this->makeTeam(
            'فريق تقييم أداء الموظفين', $supervisorMain, $leader7, [$mem7], $db,
            'منصة تقييم أداء الموظفين',
            'نظام ويب للشركات الصغيرة لإدارة دورات تقييم الأداء الدورية، بنماذج قابلة للتخصيص وتقارير تحليلية للإدارة.',
            ProjectStatusEnum::Completed, completedAt: now()->subDays(30),
        );
        $this->approvedProposal($project7, $leader7, $supervisorMain,
            'مقترح-تقييم-موظفين.pdf',
            'الشركات الصغيرة تدير تقييم الأداء بملفات إكسل متفرقة يصعب تجميعها وتحليلها.',
            'نظام مركزي بنماذج تقييم قابلة للتخصيص، وتقارير تحليلية تلقائية لكل موظف وقسم.',
            'نماذج تقييم مخصصة، تقارير تحليلية، تذكيرات لدورات التقييم.',
        );
        $this->tasks($team7, $leader7, [
            ['تصميم نماذج التقييم القابلة للتخصيص', TaskStatusEnum::Done],
            ['بناء محرّك التقارير التحليلية', TaskStatusEnum::Done],
            ['نظام الصلاحيات لمدراء الأقسام', TaskStatusEnum::Done],
        ]);
        $this->finalReport($project7, $leader7, 'التقرير-النهائي-تقييم-موظفين.pdf', self::DEMO_VIDEOS[2]);
        $this->discussion($project7, $supervisorMain, 'قاعة المناقشات الرئيسية - مبنى A', now()->subDays(26), '10:30:00',
            'م. رامي قاسم، م. هبة الزعبي، أ. مالك الأحمد');

        // ===== فريق 8: لعبة تعليمية للأطفال — مقترح قيد المراجعة =====
        $leader8 = $this->leader('غيث النمر', 'ghaith.leader@mashroui.local', $mobile->id);
        $mem8 = $this->student('جنى الرفاعي', 'jana.member@mashroui.local', $mobile->id);

        [$team8, $project8] = $this->makeTeam(
            'فريق اللعبة التعليمية للأطفال', $supHeba, $leader8, [$mem8], $mobile,
            'لعبة تعليمية لتعليم أساسيات البرمجة للأطفال',
            'لعبة موبايل تعلّم الأطفال (8-12 سنة) أساسيات التفكير المنطقي والبرمجة عبر تحدّيات مرحلية بواجهة سحب وإفلات.',
            ProjectStatusEnum::Proposed,
        );
        $this->pendingProposal($project8, $leader8,
            'مقترح-لعبة-برمجة-أطفال.pdf',
            'تعليم البرمجة للأطفال غالباً نظري وممل، ما يفقدهم الحماس بسرعة.',
            'لعبة مرحلية بتحديات تفاعلية وقصة مشوّقة تعلّم مفاهيم البرمجة بطريقة السحب والإفلات دون كتابة كود.',
            'واجهة سحب وإفلات، تحديات متدرجة الصعوبة، تقرير تقدّم لولي الأمر.',
        );

        // ===== فريق 9 (ميديا دبلوم): تصوير وتوثيق فعاليات الحرم — قيد التنفيذ =====
        $leader9 = $this->leader('براء سلامة', 'baraa.leader@mashroui.local', $mediaDiploma->id);
        $mem9 = $this->student('رنيم أبو صالح', 'raneem.member@mashroui.local', $mediaDiploma->id);

        [$team9, $project9] = $this->makeTeam(
            'فريق توثيق فعاليات الحرم الجامعي', $supHeba, $leader9, [$mem9], $mediaDiploma,
            'منصة أرشفة وتوثيق فعاليات الحرم الجامعي',
            'منصة لتنظيم وأرشفة تسجيلات وصور فعاليات الجامعة، مع تصنيف تلقائي حسب الكلية والتاريخ ومحرّك بحث سريع.',
            ProjectStatusEnum::InProgress,
        );
        $this->approvedProposal($project9, $leader9, $supHeba,
            'مقترح-توثيق-فعاليات.pdf',
            'أرشيف فعاليات الجامعة مبعثر بين مجلدات فردية بلا تصنيف أو فهرسة، يصعب الرجوع له لاحقاً.',
            'منصة أرشفة مركزية برفع سريع وتصنيف تلقائي وبحث بالكلمات المفتاحية والتاريخ.',
            'أرشفة مركزية، تصنيف تلقائي، بحث متقدّم، صلاحيات حسب الكلية.',
        );
        $this->tasks($team9, $leader9, [
            ['تصميم بنية الأرشيف والتصنيف', TaskStatusEnum::Done],
            ['بناء واجهة الرفع والمعاينة', TaskStatusEnum::InProgress],
            ['محرّك البحث والفلترة', TaskStatusEnum::Pending],
        ]);
        $this->meetings($team9, $supHeba, [
            ['مراجعة بنية التصنيف', now()->subDays(6), 'اتفقنا على تصنيف ثلاثي: الكلية، النوع، التاريخ.'],
        ]);

        // مناقشة قادمة (لم تُعقد بعد) — لعرض حالة "قادم" بجدول لجنة الإشراف
        Discussion::create([
            'project_id' => $project6->id,
            'supervisor_id' => $supNour->id,
            'place' => 'قاعة المناقشات - مبنى B',
            'discussion_date' => now()->addDays(12)->toDateString(),
            'discussion_time' => '12:00:00',
            'committee' => 'د. نور الشريف، م. رامي قاسم، أ. جود عبدالله',
            'whatsapp' => null,
            'status' => DiscussionStatusEnum::Pending,
            'term_id' => $this->termId,
        ]);
    }

    protected int $studentSeq = 0;

    protected function leader(string $name, string $email, int $specId): User
    {
        return User::create([
            'name' => $name, 'email' => $email, 'password' => 'password',
            'role' => RoleEnum::TeamLeader, 'specialization_id' => $specId, 'term_id' => $this->termId,
            'whatsapp' => $this->palestinianNumber(),
            'university_number' => $this->universityNumber(),
        ]);
    }

    protected function student(string $name, string $email, int $specId): User
    {
        return User::create([
            'name' => $name, 'email' => $email, 'password' => 'password',
            'role' => RoleEnum::Student, 'specialization_id' => $specId, 'term_id' => $this->termId,
            'whatsapp' => $this->palestinianNumber(),
            'university_number' => $this->universityNumber(),
        ]);
    }

    /** رقم واتساب فلسطيني واقعي (بادئات جوال وأوريدو الشائعة) بصيغة wa.me: 970 بدون صفر أو + */
    protected function palestinianNumber(): string
    {
        $prefixes = ['59', '56', '52', '54'];
        $prefix = $prefixes[$this->studentSeq % count($prefixes)];
        $line = str_pad((string) (2210000 + $this->studentSeq * 37), 7, '0', STR_PAD_LEFT);

        return '970'.$prefix.$line;
    }

    /** رقم جامعي بصيغة سنة القبول + رقم تسلسلي، بأسلوب الجامعات الفلسطينية */
    protected function universityNumber(): string
    {
        $years = ['120', '121', '122'];
        $year = $years[$this->studentSeq % count($years)];
        $this->studentSeq++;

        return $year.str_pad((string) (500 + $this->studentSeq * 13), 5, '0', STR_PAD_LEFT);
    }

    /** @param  User[]  $members */
    protected function makeTeam(
        string $name, User $supervisor, User $leader, array $members, Specialization $spec,
        string $projectName, string $description, ProjectStatusEnum $status, ?\Illuminate\Support\Carbon $completedAt = null,
    ): array {
        $team = Team::create([
            'name' => $name, 'supervisor_id' => $supervisor->id,
            'specialization_id' => $spec->id, 'term_id' => $this->termId, 'leader_id' => $leader->id,
        ]);
        TeamMember::create(['team_id' => $team->id, 'student_id' => $leader->id, 'is_leader' => true]);
        foreach ($members as $member) {
            TeamMember::create(['team_id' => $team->id, 'student_id' => $member->id, 'is_leader' => false]);
        }

        $project = Project::create([
            'team_id' => $team->id, 'supervisor_id' => $supervisor->id, 'name' => $projectName,
            'description' => $description, 'department_id' => $spec->department_id, 'specialization_id' => $spec->id,
            'term_id' => $this->termId, 'status' => $status,
            'completed_at' => $completedAt,
        ]);

        return [$team, $project];
    }

    protected function approvedProposal(Project $project, User $leader, User $supervisor, string $fileName, string $problems, string $solutions, string $features): void
    {
        Proposal::create([
            'project_id' => $project->id, 'name' => $project->name, 'description' => $project->description,
            'problems' => $problems, 'solutions' => $solutions, 'features_value' => $features,
            'pdf_path' => $this->placeholderPdf('proposals', $fileName),
            'status' => ProposalStatusEnum::Approved, 'submitted_by' => $leader->id, 'reviewed_by' => $supervisor->id,
        ]);
    }

    protected function pendingProposal(Project $project, User $leader, string $fileName, string $problems, string $solutions, string $features): void
    {
        Proposal::create([
            'project_id' => $project->id, 'name' => $project->name, 'description' => $project->description,
            'problems' => $problems, 'solutions' => $solutions, 'features_value' => $features,
            'pdf_path' => $this->placeholderPdf('proposals', $fileName),
            'status' => ProposalStatusEnum::Pending, 'submitted_by' => $leader->id,
        ]);
    }

    protected function rejectedProposal(Project $project, User $leader, User $supervisor, string $fileName, string $problems, string $solutions, string $features, string $reason): void
    {
        Proposal::create([
            'project_id' => $project->id, 'name' => $project->name, 'description' => $project->description,
            'problems' => $problems, 'solutions' => $solutions, 'features_value' => $features,
            'pdf_path' => $this->placeholderPdf('proposals', $fileName),
            'status' => ProposalStatusEnum::Rejected, 'rejection_reason' => $reason,
            'submitted_by' => $leader->id, 'reviewed_by' => $supervisor->id,
        ]);
    }

    /** @param  array<array{0:string,1:TaskStatusEnum}>  $items */
    protected function tasks(Team $team, User $creator, array $items): void
    {
        foreach ($items as [$title, $status]) {
            Task::create(['team_id' => $team->id, 'title' => $title, 'status' => $status, 'created_by' => $creator->id]);
        }
    }

    /** @param  array<array{0:string,1:\Illuminate\Support\Carbon,2:?string}>  $items */
    protected function meetings(Team $team, User $creator, array $items): void
    {
        $links = ['https://meet.google.com/xyz-abcd-efg', 'https://meet.google.com/lmn-opqr-stu', 'https://meet.google.com/qrt-uvwx-yzc'];
        foreach ($items as $i => [$title, $when, $notes]) {
            Meeting::create([
                'team_id' => $team->id, 'title' => $title, 'scheduled_at' => $when->setTime(11, 0),
                'google_meet_link' => $links[$i % count($links)], 'notes' => $notes, 'created_by' => $creator->id,
            ]);
        }
    }

    protected function finalReport(Project $project, User $leader, string $fileName, string $videoUrl): void
    {
        FinalReport::create([
            'project_id' => $project->id,
            'pdf_path' => $this->placeholderPdf('final_reports', $fileName),
            'video_url' => $videoUrl,
            'uploaded_by' => $leader->id,
        ]);
    }

    protected function discussion(Project $project, User $supervisor, string $place, \Illuminate\Support\Carbon $date, string $time, string $committee): void
    {
        Discussion::create([
            'project_id' => $project->id, 'supervisor_id' => $supervisor->id, 'place' => $place,
            'discussion_date' => $date->toDateString(), 'discussion_time' => $time,
            'committee' => $committee, 'whatsapp' => null,
            'status' => DiscussionStatusEnum::Confirmed, 'term_id' => $this->termId,
        ]);
    }

    protected function placeholderPdf(string $folder, string $displayName): string
    {
        $path = $folder.'/'.uniqid().'.pdf';
        Storage::put($path, "%PDF-1.4\n% ملف عرض توضيحي — $displayName\n%%EOF");

        return $path;
    }
}
