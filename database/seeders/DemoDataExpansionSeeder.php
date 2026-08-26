<?php

namespace Database\Seeders;

use App\Enums\ProjectStatusEnum;
use App\Enums\TaskStatusEnum;
use App\Models\AcademicTerm;
use App\Models\Specialization;
use App\Models\User;

/** يضيف 11 مشروعًا إضافيًا فوق DemoDataSeeder (9 مشاريع) ليصل الإجمالي إلى 20 — لعرض الموقع بحالة واقعية مكتملة */
class DemoDataExpansionSeeder extends DemoDataSeeder
{
    public function run(): void
    {
        $this->termId = AcademicTerm::where('is_current', true)->value('id');
        $this->studentSeq = 200;

        $web = Specialization::where('name', 'تصميم وتطوير مواقع الويب')->first();
        $db = Specialization::where('name', 'برمجيات وقواعد بيانات')->first();
        $mobile = Specialization::where('name', 'تصميم وبرمجة تطبيقات الموبايل')->first();
        $mediaDiploma = Specialization::where('name', 'تكنولوجيا الوسائط المتعددة')->where('degree', 'diploma')->first();
        $mediaBachelor = Specialization::where('name', 'تكنولوجيا الوسائط المتعددة')->where('degree', 'bachelor')->first();

        $supKhaled = User::where('email', 'supervisor@mashroui.local')->first();
        $supHeba = User::where('email', 'h.zoubi@mashroui.local')->first();
        $supRami = User::where('email', 'r.qasem@mashroui.local')->first();
        $supNour = User::where('email', 'n.sharif@mashroui.local')->first();

        // ===== فريق 10: تتبّع الحافلات الجامعية — قيد التنفيذ =====
        $leader10 = $this->leader('نادين أبو عيطة', 'nadine.leader@mashroui.local', $db->id);
        $mem10 = $this->student('يوسف الديك', 'yousef.member@mashroui.local', $db->id);

        [$team10, $project10] = $this->makeTeam(
            'فريق تتبّع الحافلات الجامعية', $supRami, $leader10, [$mem10], $db,
            'نظام تتبّع حافلات النقل الجامعي',
            'نظام يعرض موقع حافلات النقل الجامعي لحظياً على خريطة، مع تقدير وقت الوصول لكل محطة.',
            ProjectStatusEnum::InProgress,
        );
        $this->approvedProposal($project10, $leader10, $supRami,
            'مقترح-تتبع-حافلات.pdf',
            'الطلاب ينتظرون الحافلات دون معرفة موعد وصولها الفعلي، ما يسبب إهدار وقت كبير.',
            'تطبيق يعرض مواقع الحافلات عبر GPS مع تقدير وقت الوصول لكل محطة على المسار.',
            'خريطة لحظية، تقدير وقت الوصول، إشعار قبل وصول الحافلة بدقائق.',
        );
        $this->tasks($team10, $leader10, [
            ['تصميم قاعدة بيانات المسارات والمحطات', TaskStatusEnum::Done],
            ['بناء واجهة الخريطة اللحظية', TaskStatusEnum::InProgress],
            ['حساب تقدير وقت الوصول', TaskStatusEnum::Pending],
        ]);
        $this->meetings($team10, $supRami, [
            ['مراجعة تصميم قاعدة البيانات', now()->subDays(7), 'اعتماد مخطط المسارات والمحطات.'],
        ]);

        // ===== فريق 11: منصة التطوع الطلابي — مكتمل =====
        $leader11 = $this->leader('رهف عودة', 'rahaf.leader@mashroui.local', $web->id);
        $mem11a = $this->student('حمزة أبو شرخ', 'hamza.member@mashroui.local', $web->id);
        $mem11b = $this->student('ديما صيام', 'dima.member@mashroui.local', $web->id);

        [$team11, $project11] = $this->makeTeam(
            'فريق منصة التطوع الطلابي', $supNour, $leader11, [$mem11a, $mem11b], $web,
            'منصة تنظيم فرص التطوع الطلابي',
            'منصة تجمع فرص التطوع بالجامعة والمجتمع المحلي، وتتيح للطلاب التسجيل ومتابعة ساعات التطوع المعتمدة.',
            ProjectStatusEnum::Completed, completedAt: now()->subDays(18),
        );
        $this->approvedProposal($project11, $leader11, $supNour,
            'مقترح-منصة-تطوع.pdf',
            'فرص التطوع مبعثرة بين إعلانات متفرقة، ولا توجد طريقة موثوقة لاحتساب ساعات التطوع.',
            'منصة مركزية لنشر الفرص والتسجيل فيها، مع سجل ساعات تطوع معتمد لكل طالب.',
            'نشر فرص تطوع، تسجيل فوري، سجل ساعات معتمد، شهادات تلقائية.',
        );
        $this->tasks($team11, $leader11, [
            ['تصميم قاعدة بيانات الفرص والمتطوعين', TaskStatusEnum::Done],
            ['بناء واجهة نشر الفرص والتسجيل', TaskStatusEnum::Done],
            ['نظام سجل الساعات والشهادات', TaskStatusEnum::Done],
        ]);
        $this->meetings($team11, $supNour, [
            ['مراجعة نهائية قبل التسليم', now()->subDays(20), 'كل الميزات جاهزة، بانتظار تسجيل الفيديو التعريفي.'],
        ]);
        $this->finalReport($project11, $leader11, 'التقرير-النهائي-تطوع-طلابي.pdf', self::DEMO_VIDEOS[3]);
        $this->discussion($project11, $supNour, 'قاعة المناقشات - مبنى B', now()->subDays(15), '10:00:00',
            'م. خالد أبو غزالة، م. رامي قاسم، أ. نادين أبو عيطة');

        // ===== فريق 12: الدعم النفسي للطلاب — قيد التنفيذ =====
        $leader12 = $this->leader('مجد النجار', 'majd.leader@mashroui.local', $mobile->id);
        $mem12 = $this->student('سارة أبو رمان', 'sara.member@mashroui.local', $mobile->id);

        [$team12, $project12] = $this->makeTeam(
            'فريق تطبيق الدعم النفسي الطلابي', $supHeba, $leader12, [$mem12], $mobile,
            'تطبيق دعم نفسي أولي وحجز جلسات إرشاد',
            'تطبيق موبايل يتيح للطالب تقييمًا نفسيًا أوليًا وحجز جلسة مع مرشد الجامعة بسرية تامة.',
            ProjectStatusEnum::InProgress,
        );
        $this->approvedProposal($project12, $leader12, $supHeba,
            'مقترح-دعم-نفسي.pdf',
            'كثير من الطلاب يترددون بطلب الدعم النفسي بسبب الحرج من الحجز المباشر وجهاً لوجه.',
            'تطبيق بواجهة سرية بالكامل، تقييم أولي بسيط، وحجز جلسة مع مرشد دون كشف الهوية لغير المختص.',
            'تقييم أولي، حجز سري، تذكيرات جلسات، محتوى توعوي.',
        );
        $this->tasks($team12, $leader12, [
            ['تصميم نموذج التقييم النفسي الأولي', TaskStatusEnum::Done],
            ['بناء نظام حجز الجلسات', TaskStatusEnum::InProgress],
            ['ضمان سرية البيانات والتشفير', TaskStatusEnum::Review],
        ]);
        $this->meetings($team12, $supHeba, [
            ['مراجعة نموذج التقييم الأولي', now()->subDays(4), 'طلبت المشرفة تبسيط أسئلة النموذج.'],
            ['متابعة نظام الحجز', now()->addDays(6), null],
        ]);

        // ===== فريق 13: أرشفة الرسائل الجامعية — مكتمل =====
        $leader13 = $this->leader('علي أبو سنينة', 'ali.leader@mashroui.local', $db->id);
        $mem13 = $this->student('نور دويكات', 'nour.member@mashroui.local', $db->id);

        [$team13, $project13] = $this->makeTeam(
            'فريق أرشفة الرسائل الجامعية', $supKhaled, $leader13, [$mem13], $db,
            'منصة أرشفة رسائل الماجستير والبكالوريوس',
            'منصة بحث نصي متقدّم داخل رسائل التخرج والماجستير المؤرشفة، مع تصنيف حسب القسم والسنة والمشرف.',
            ProjectStatusEnum::Completed, completedAt: now()->subDays(22),
        );
        $this->approvedProposal($project13, $leader13, $supKhaled,
            'مقترح-أرشفة-رسائل.pdf',
            'رسائل التخرج القديمة مخزّنة كملفات PDF متفرقة بلا فهرسة، يصعب البحث داخل محتواها.',
            'منصة ترفع الرسائل وتفهرس نصها تلقائياً، مع بحث متقدم بالكلمات المفتاحية والقسم والسنة.',
            'بحث نصي كامل، تصنيف تلقائي، تحميل مباشر، إحصاءات الأقسام.',
        );
        $this->tasks($team13, $leader13, [
            ['تصميم بنية الفهرسة والبحث', TaskStatusEnum::Done],
            ['بناء محرّك البحث النصي', TaskStatusEnum::Done],
            ['واجهة الرفع والتصنيف', TaskStatusEnum::Done],
        ]);
        $this->finalReport($project13, $leader13, 'التقرير-النهائي-أرشفة-رسائل.pdf', self::DEMO_VIDEOS[0]);
        $this->discussion($project13, $supKhaled, 'قاعة المناقشات الرئيسية - مبنى A', now()->subDays(19), '11:30:00',
            'د. نور الشريف، م. هبة الزعبي، أ. علي أبو سنينة');

        // ===== فريق 14: هوية بصرية لفعاليات الجامعة — مقترح قيد المراجعة =====
        $leader14 = $this->leader('لارا حماد', 'lara.leader@mashroui.local', $mediaDiploma->id);
        $mem14 = $this->student('كريم شاهين', 'karim.member@mashroui.local', $mediaDiploma->id);

        [$team14, $project14] = $this->makeTeam(
            'فريق الهوية البصرية لفعاليات الجامعة', $supHeba, $leader14, [$mem14], $mediaDiploma,
            'منصة قوالب تصميم لفعاليات الجامعة',
            'منصة تضم قوالب تصميم جاهزة (بوسترات، بانرات، منشورات) بهوية الجامعة البصرية لاستخدام الأقسام والأندية الطلابية.',
            ProjectStatusEnum::Proposed,
        );
        $this->pendingProposal($project14, $leader14,
            'مقترح-هوية-بصرية.pdf',
            'كل قسم أو نادٍ طلابي يصمم إعلاناته بأسلوب مختلف، ما يفقد الجامعة هوية بصرية موحّدة.',
            'مكتبة قوالب جاهزة قابلة للتعديل السريع، متوافقة مع دليل الهوية البصرية الرسمي للجامعة.',
            'قوالب جاهزة، تعديل سريع، تصدير بجودة طباعة، مكتبة شعارات وألوان رسمية.',
        );

        // ===== فريق 15: مواقف السيارات الذكية — قيد التنفيذ =====
        $leader15 = $this->leader('عمر قنديل', 'omar.leader@mashroui.local', $mobile->id);
        $mem15 = $this->student('رزان عابدين', 'razan.member@mashroui.local', $mobile->id);

        [$team15, $project15] = $this->makeTeam(
            'فريق مواقف السيارات الذكية', $supRami, $leader15, [$mem15], $mobile,
            'تطبيق حجز مواقف السيارات بالحرم الجامعي',
            'تطبيق يعرض المواقف المتاحة داخل الحرم لحظياً، ويتيح حجز موقف مسبقاً لأصحاب السيارات.',
            ProjectStatusEnum::InProgress,
        );
        $this->approvedProposal($project15, $leader15, $supRami,
            'مقترح-مواقف-سيارات.pdf',
            'أصحاب السيارات يضيّعون وقتاً طويلاً بالبحث عن موقف فارغ خصوصاً بساعات الذروة.',
            'تطبيق يعرض حالة كل موقف عبر حساسات بسيطة، ويسمح بحجز موقف مسبقاً لمدة محددة.',
            'عرض لحظي للمواقف، حجز مسبق، تنبيه عند اقتراب انتهاء مدة الحجز.',
        );
        $this->tasks($team15, $leader15, [
            ['تصميم قاعدة بيانات المواقف والحجوزات', TaskStatusEnum::Done],
            ['بناء واجهة عرض المواقف اللحظي', TaskStatusEnum::InProgress],
            ['منطق الحجز المسبق والتنبيهات', TaskStatusEnum::Pending],
        ]);
        $this->meetings($team15, $supRami, [
            ['مراجعة تصميم قاعدة البيانات', now()->subDays(3), null],
        ]);

        // ===== فريق 16: تبادل الكتب الجامعية — مكتمل =====
        $leader16 = $this->leader('هبة سمارة', 'heba.leader@mashroui.local', $web->id);
        $mem16a = $this->student('زياد فروانة', 'ziad.member@mashroui.local', $web->id);
        $mem16b = $this->student('ريم أبو زنط', 'reem.member@mashroui.local', $web->id);

        [$team16, $project16] = $this->makeTeam(
            'فريق منصة تبادل الكتب الجامعية', $supNour, $leader16, [$mem16a, $mem16b], $web,
            'منصة تبادل وبيع الكتب الجامعية المستعملة',
            'منصة تربط الطلاب لبيع وشراء وتبادل الكتب الجامعية المستعملة حسب المساق والتخصص.',
            ProjectStatusEnum::Completed, completedAt: now()->subDays(11),
        );
        $this->approvedProposal($project16, $leader16, $supNour,
            'مقترح-تبادل-كتب.pdf',
            'الطلاب يشترون كتباً جديدة كل فصل رغم توفر نسخ مستعملة بحوزة طلاب سابقين لا طريقة موثوقة للوصول إليها.',
            'منصة تدرج الكتب حسب المساق، وتتيح تواصلاً مباشراً بين البائع والمشتري داخل الحرم.',
            'بحث حسب المساق، تواصل مباشر، تقييم البائعين، قسم إهداء كتب مجانية.',
        );
        $this->tasks($team16, $leader16, [
            ['تصميم قاعدة بيانات الكتب والمساقات', TaskStatusEnum::Done],
            ['بناء واجهة العرض والبحث', TaskStatusEnum::Done],
            ['نظام الرسائل بين المستخدمين', TaskStatusEnum::Done],
            ['نظام تقييم البائعين', TaskStatusEnum::Done],
        ]);
        $this->meetings($team16, $supNour, [
            ['مراجعة أولى للواجهة', now()->subDays(24), 'اعتماد شكل بطاقة عرض الكتاب.'],
            ['مراجعة نهائية قبل التسليم', now()->subDays(13), 'جاهزون للتسليم، تبقّى تسجيل الفيديو التعريفي.'],
        ]);
        $this->finalReport($project16, $leader16, 'التقرير-النهائي-تبادل-كتب.pdf', self::DEMO_VIDEOS[1]);
        $this->discussion($project16, $supNour, 'قاعة المناقشات - مبنى B', now()->subDays(9), '13:00:00',
            'م. خالد أبو غزالة، م. رامي قاسم، أ. هبة سمارة');

        // ===== فريق 17: نظام إدارة المختبرات — مقترح مرفوض =====
        $leader17 = $this->leader('فادي أبو علي', 'fadi.leader@mashroui.local', $db->id);
        $mem17 = $this->student('ميس الحلو', 'mais.member@mashroui.local', $db->id);

        [$team17, $project17] = $this->makeTeam(
            'فريق نظام إدارة المختبرات', $supKhaled, $leader17, [$mem17], $db,
            'نظام حجز وجدولة مختبرات الحاسوب',
            'نظام يتيح للطلاب وأعضاء الهيئة التدريسية حجز أوقات استخدام مختبرات الحاسوب إلكترونياً.',
            ProjectStatusEnum::Proposed,
        );
        $this->rejectedProposal($project17, $leader17, $supKhaled,
            'مقترح-إدارة-مختبرات.pdf',
            'جدولة المختبرات تتم يدوياً عبر جداول ورقية معلّقة، ما يسبب تعارضاً متكرراً بالحجوزات.',
            'نظام حجز إلكتروني بتقويم موحّد لكل مختبر يمنع التعارض تلقائياً.',
            'تقويم موحّد، منع تعارض، تقارير استخدام.',
            'الفكرة تتشابه كثيراً مع نظام حجز القاعات الجامعية المعتمد مسبقاً — يرجى دمج الفكرتين أو اقتراح نطاق مختلف.',
        );

        // ===== فريق 18: بودكاست أكاديمي — قيد التنفيذ =====
        $leader18 = $this->leader('يارا مشني', 'yara.leader@mashroui.local', $mediaBachelor->id);
        $mem18 = $this->student('باسل زيدان', 'basel.member@mashroui.local', $mediaBachelor->id);

        [$team18, $project18] = $this->makeTeam(
            'فريق إنتاج بودكاست أكاديمي', $supHeba, $leader18, [$mem18], $mediaBachelor,
            'منصة إنتاج ونشر بودكاست أكاديمي',
            'منصة تتيح لأعضاء هيئة التدريس والطلاب تسجيل ونشر حلقات بودكاست أكاديمية بمونتاج مبسّط.',
            ProjectStatusEnum::InProgress,
        );
        $this->approvedProposal($project18, $leader18, $supHeba,
            'مقترح-بودكاست-أكاديمي.pdf',
            'لا توجد منصة موحّدة لنشر المحتوى الصوتي الأكاديمي للجامعة، والمحتوى الحالي متفرق بين حسابات شخصية.',
            'منصة تسجيل ومونتاج مبسّط مع نشر مباشر وتصنيف الحلقات حسب القسم والموضوع.',
            'تسجيل ومونتاج مدمج، تصنيف حسب القسم، إحصاءات استماع.',
        );
        $this->tasks($team18, $leader18, [
            ['تصميم واجهة الاستماع والتصنيف', TaskStatusEnum::Done],
            ['بناء أداة التسجيل والمونتاج المبسّط', TaskStatusEnum::InProgress],
            ['نظام النشر والإحصاءات', TaskStatusEnum::Pending],
        ]);
        $this->meetings($team18, $supHeba, [
            ['مراجعة واجهة الاستماع', now()->subDays(6), 'اعتماد التصنيف حسب القسم.'],
        ]);

        // ===== فريق 19: الإرشاد الأكاديمي الذكي — مكتمل =====
        $leader19 = $this->leader('تيم أبو دية', 'tim.leader@mashroui.local', $db->id);
        $mem19 = $this->student('جنى صبح', 'jana2.member@mashroui.local', $db->id);

        [$team19, $project19] = $this->makeTeam(
            'فريق الإرشاد الأكاديمي الذكي', $supRami, $leader19, [$mem19], $db,
            'منصة الإرشاد الأكاديمي الذكي',
            'منصة تحلّل سجل الطالب الأكاديمي وتقترح خطة مساقات مناسبة، مع تنبيه مبكر عند مؤشرات تعثّر دراسي.',
            ProjectStatusEnum::Completed, completedAt: now()->subDays(27),
        );
        $this->approvedProposal($project19, $leader19, $supRami,
            'مقترح-إرشاد-أكاديمي.pdf',
            'المرشدون الأكاديميون يديرون عشرات الطلاب يدوياً دون أدوات تنبّه مبكراً بمؤشرات التعثّر.',
            'منصة تحلّل المعدلات والحضور وتقترح خطة مساقات مناسبة، وتنبّه المرشد عند تراجع الأداء.',
            'تحليل أكاديمي تلقائي، اقتراح خطة مساقات، تنبيه مبكر للمرشد.',
        );
        $this->tasks($team19, $leader19, [
            ['تصميم نموذج تحليل السجل الأكاديمي', TaskStatusEnum::Done],
            ['بناء محرّك اقتراح المساقات', TaskStatusEnum::Done],
            ['نظام التنبيه المبكر للمرشدين', TaskStatusEnum::Done],
        ]);
        $this->meetings($team19, $supRami, [
            ['اعتماد نموذج التحليل الأكاديمي', now()->subDays(29), 'تمت الموافقة بعد تعديل أوزان المؤشرات.'],
        ]);
        $this->finalReport($project19, $leader19, 'التقرير-النهائي-إرشاد-أكاديمي.pdf', self::DEMO_VIDEOS[2]);
        $this->discussion($project19, $supRami, 'قاعة المناقشات الرئيسية - مبنى A', now()->subDays(24), '09:00:00',
            'د. نور الشريف، م. خالد أبو غزالة، أ. تيم أبو دية');

        // ===== فريق 20: تقييم المطاعم الطلابية — مقترح قيد المراجعة =====
        $leader20 = $this->leader('نغم عيسى', 'nagham.leader@mashroui.local', $web->id);
        $mem20 = $this->student('أنس دبابسة', 'anas.member@mashroui.local', $web->id);

        [$team20, $project20] = $this->makeTeam(
            'فريق منصة تقييم المطاعم الطلابية', $supNour, $leader20, [$mem20], $web,
            'منصة تقييم مطاعم وكافيتريات الحرم الجامعي',
            'منصة يقيّم فيها الطلاب مطاعم وكافيتريات الحرم من حيث الجودة والسعر والنظافة، بمراجعات وتقييمات موثوقة.',
            ProjectStatusEnum::Proposed,
        );
        $this->pendingProposal($project20, $leader20,
            'مقترح-تقييم-مطاعم.pdf',
            'لا توجد طريقة موثوقة لمعرفة جودة الخيارات المتاحة بمطاعم الحرم قبل التجربة الفعلية.',
            'منصة تقييمات ومراجعات من طلاب حقيقيين، مع ترتيب المطاعم حسب التقييم والسعر.',
            'تقييمات موثّقة، ترتيب حسب السعر والجودة، صور من الطلاب، إبلاغ عن الأسعار.',
        );
    }
}
