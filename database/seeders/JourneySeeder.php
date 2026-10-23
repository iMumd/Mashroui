<?php

namespace Database\Seeders;

use App\Enums\ProjectStatusEnum;
use App\Enums\ProposalStatusEnum;
use App\Enums\TaskStatusEnum;
use App\Models\AcademicTerm;
use App\Models\User;
use Database\Seeders\Concerns\SeedsJourney;
use Illuminate\Database\Seeder;

class JourneySeeder extends Seeder
{
    use SeedsJourney;

    public function run(): void
    {
        $term = AcademicTerm::where('is_current', true)->firstOrFail();
        $this->termId = $term->id;
        $this->studentSeq = 1000;

        $supervisor = fn($e) => User::where('email', $e)->firstOrFail();
        $committee = User::where('role', \App\Enums\RoleEnum::Committee)->orderBy('id')->first();

        $specSoft = $this->spec('برمجيات وقواعد بيانات');
        $specWeb = $this->spec('تصميم وتطوير مواقع الويب');
        $specNet = $this->spec('الشبكات وأمن المعلومات');
        $specAi = $this->spec('الأنظمة الذكية');

        // 1) مخزون ذكي - test accounts (leader+student) بدون مقترح لاختبار التقديم
        $leader1 = User::where('email', 'leader@smail.ucas.edu.ps')->firstOrFail();
        $member2 = User::where('email', 'student@smail.ucas.edu.ps')->firstOrFail();
        $s1a = $this->member(['name' => 'سيف قطناني', 'email' => 'saif.qutnani@smail.ucas.edu.ps', 'gender' => 'male'], $specSoft);
        $s1b = $this->member(['name' => 'هديل المصري', 'email' => 'hadeel.masri.test@smail.ucas.edu.ps', 'gender' => 'female'], $specSoft);
        [$team1, $proj1] = $this->makeTeam('فريق إدارة المخزون الذكي', $supervisor('supervisor@ucas.edu.ps'), $leader1, [$member2, $s1a, $s1b], $specSoft, 'نظام إدارة المخزون الذكي', 'نظام ذكي لإدارة المخزون يتيح تتبع الأصناف، تنبيهات النفاد، والتقارير الإحصائية مع صلاحيات متعددة.', ['تطوير ويب', 'أنظمة إدارة', 'قواعد بيانات'], ProjectStatusEnum::InProgress);
        $this->proposal($proj1, $leader1, $supervisor('supervisor@ucas.edu.ps'), ['state' => 'none']);
        $this->tasks($team1, $leader1, [
            ['تحليل المتطلبات وتصميم قاعدة البيانات', TaskStatusEnum::Done, 'توثيق متطلبات النظام، رسم مخطط ERD، وتحديد العلاقات بين الجداول مع التأكد من التطبيع.', [0,1]],
            ['تصميم واجهات المستخدم وتجربة الاستخدام', TaskStatusEnum::Done, 'تصميم واجهات للمدخلات، فلاتر، وجدول البيانات مع مراعاة سهولة الاستخدام.', [0,2]],
            ['بناء وحدات الأصناف والمخزون', TaskStatusEnum::InProgress, 'تطبيق CRUD للأصناف، وحدات القياس، مخزون الفروع، وحركة الوارد/الصادر.', [0,1]],
            ['نظام التنبيهات وتقارير المخزون', TaskStatusEnum::InProgress, 'إنشاء تنبيهات عند وصول الحد الأدنى، تقارير الاستهلاك، وإمكانية التصدير.', [2,3]],
            ['اختبار التكامل وحفظ النسخ الاحتياطية', TaskStatusEnum::Pending, 'اختبار سيناريوهات كاملة، التحقق من الصلاحيات، وإعداد آلية النسخ الاحتياطي.', [1,2]],
        ]);
        $this->meetings($team1, $supervisor('supervisor@ucas.edu.ps'), [
            ['اجتماع التخطيط العام', -12, 'اتفاق على خطة العمل، تقسيم المهام، وتحديد موعد التقييم المرحلي.'],
            ['متابعة تقدم الوحدات الأساسية', -6, 'مراجعة تصميم قاعدة البيانات وواجهة تسجيل الدخول وملاحظات التحسين.'],
            ['مراجعة الربط بين الوحدات', -2, 'مراجعة نظام التنبيهات، وضبط صلاحيات المستخدمين.'],
        ]);
        $this->teamAnnouncements($team1, [
            ['title' => 'تذكير: رفع ملفات المهام', 'body' => 'يرجى رفع ملفات كل مهمة مكتملة مع شرح موجز لما تم إنجازه قبل الموعد.', 'days' => 5, 'author' => $supervisor('supervisor@ucas.edu.ps')],
            ['title' => 'جاهزية للعرض المرحلي', 'body' => 'سيُجرى العرض المرحلي قريبًا، يرجى تجهيز عرض مختصر يبرز أهم ما تم إنجازه.', 'days' => 2, 'author' => $supervisor('supervisor@ucas.edu.ps')],
        ]);

        // 2) مقترح قيد الانتظار
        $l2 = $this->member(['name' => 'محمد الشنطي', 'email' => 'mohammed.shanti@smail.ucas.edu.ps', 'gender' => 'male'], $specWeb);
        $m2a = $this->member(['name' => 'رغد شلبي', 'email' => 'raghad.shalaby2@smail.ucas.edu.ps', 'gender' => 'female'], $specWeb);
        $m2b = $this->member(['name' => 'عمر أبو جاموس', 'email' => 'omar.abujamous@smail.ucas.edu.ps', 'gender' => 'male'], $specWeb);
        [$team2, $proj2] = $this->makeTeam('فريق حجز المواعيد الطبية', $supervisor('r.jabr@ucas.edu.ps'), $l2, [$m2a, $m2b], $specWeb, 'منصة حجز المواعيد الطبية الذكية', 'منصة تتيح للمرضى حجز المواعيد مع الأطباء، إدارة الجداول، وإرسال التنبيهات مع لوحة تحكم للعيادة.', ['تطبيق ويب', 'نظم معلومات', 'خدمات طبية'], ProjectStatusEnum::Proposed);
        $this->proposal($proj2, $l2, $supervisor('r.jabr@ucas.edu.ps'), ['state' => 'pending', 'submitted_days' => 8, 'problems' => 'الحجز هاتفيًا يسبب ازدحامًا وطول انتظار وصعوبة تتبع المواعيد.', 'solutions' => 'منصة إلكترونية للحجز الذاتي مع تفعيل المواعيد حسب جدول الطبيب.', 'features' => 'حجز فوري، تأكيد عبر الواتساب، إلغاء وإعادة جدولة، تقارير للعيادة.']);

        // 3) مقترح مرفوض
        $l3 = $this->member(['name' => 'أحمد قندس', 'email' => 'ahmad.qandil@smail.ucas.edu.ps', 'gender' => 'male'], $specAi);
        $m3a = $this->member(['name' => 'تسنيم أبو عمرة', 'email' => 'tasneem.abouamra2@smail.ucas.edu.ps', 'gender' => 'female'], $specAi);
        [$team3, $proj3] = $this->makeTeam('فريق تعلم اللغات بالذكاء الاصطناعي', $supervisor('y.matar@ucas.edu.ps'), $l3, [$m3a], $specAi, 'نظام تعلم اللغات التفاعلي بالذكاء الاصطناعي', 'نظام يقترح دروسًا مخصصة بناءً على مستوى الطالب ويستخدم تمارين تفاعلية مع تقييم فوري.', ['ذكاء اصطناعي', 'تعليم إلكتروني'], ProjectStatusEnum::Proposed);
        $this->proposal($proj3, $l3, $supervisor('y.matar@ucas.edu.ps'), ['state' => 'rejected', 'submitted_days' => 18, 'reasons' => ['يجب توضيح نطاق المشروع وحدوده بشكل أكثر دقة وربطه بأهداف قابلة للقياس.', 'ينقص المشروع في شرح تقنيات الذكاء الاصطناعي المستخدمة وكيفية قياس فعاليتها.']]);

        // 4) مكتمل
        $l4 = $this->member(['name' => 'ياسر أبو سعدة', 'email' => 'yaser.abusaada@smail.ucas.edu.ps', 'gender' => 'male'], $specWeb);
        $m4a = $this->member(['name' => 'نور أبو صفية', 'email' => 'noor.abusafiya2@smail.ucas.edu.ps', 'gender' => 'female'], $specWeb);
        $m4b = $this->member(['name' => 'معتصم أبو شمالة', 'email' => 'moatasem.shamala@smail.ucas.edu.ps', 'gender' => 'male'], $specWeb);
        $m4c = $this->member(['name' => 'دانا الأسعد', 'email' => 'dana.assad2@smail.ucas.edu.ps', 'gender' => 'female'], $specWeb);
        [$team4, $proj4] = $this->makeTeam('فريق إنتاج المحتوى التعليمي', $supervisor('h.abuamer@ucas.edu.ps'), $l4, [$m4a, $m4b, $m4c], $specWeb, 'منصة إنتاج ومشاركة المحتوى التعليمي الرقمي', 'منصة تتيح للمدرسين إنشاء الدروس، رفع الفيديوهات، وإدارة الاختبارات مع تتبع تقدم الطلاب.', ['تعليم إلكتروني', 'تطوير ويب'], ProjectStatusEnum::Completed, 16);
        $this->proposal($proj4, $l4, $supervisor('h.abuamer@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 22, 'review_days' => 12]);
        $this->tasks($team4, $l4, [
            ['تحليل المتطلبات ورسم مخطط العمل', TaskStatusEnum::Done, 'جمع المتطلبات، تحديد الأدوار، ورسم خارطة الطريق.', [0,1]],
            ['تصميم النظام وقاعدة البيانات', TaskStatusEnum::Done, 'تصميم قاعدة البيانات، علاقات الجداول، وتصميم الواجهة.', [0,2]],
            ['تطوير إدارة الدروس والاختبارات', TaskStatusEnum::Done, 'بناء وحدات الدروس، الأسئلة، والامتحانات التلقائية.', [1,2,3]],
            ['نظام تتبع التقدم والإحصائيات', TaskStatusEnum::Done, 'لوحة إحصائيات للمعلم والطالب مع رسوم بيانية.', [0,3]],
            ['التكامل والاختبار الشامل', TaskStatusEnum::Done, 'اختبار شامل، إصلاح الأخطاء، وتحسين الأداء.', [1,2]],
        ]);
        $this->meetings($team4, $supervisor('h.abuamer@ucas.edu.ps'), [
            ['اجتماع انطلاق المشروع', -20, 'مناقشة خطة العمل وجدول المواعيد.'],
            ['مراجعة التصميم الأولي', -14, 'مراجعة واجهات النظام والملاحظات.'],
            ['عرض مرحلي', -9, 'عرض الوحدات المنجزة ومراجعة الملاحظات.'],
            ['مراجعة التقرير النهائي', -4, 'مناقشة التقرير النهائي وجهوزية المناقشة.'],
        ]);
        $this->finalReport($proj4, $l4, $supervisor('h.abuamer@ucas.edu.ps'), $committee, ['review' => 'approved', 'supervisor_days' => 6, 'committee_days' => 3]);

        // 5) مكتمل قيد مراجعة اللجنة
        $l5 = $this->member(['name' => 'إبراهيم النجار', 'email' => 'ibrahim.najjar@smail.ucas.edu.ps', 'gender' => 'male'], $specWeb);
        $m5a = $this->member(['name' => 'شهد أبو هولي', 'email' => 'shahd.abholey2@smail.ucas.edu.ps', 'gender' => 'female'], $specWeb);
        $m5b = $this->member(['name' => 'أنس حمد', 'email' => 'anas.hamad@smail.ucas.edu.ps', 'gender' => 'male'], $specWeb);
        [$team5, $proj5] = $this->makeTeam('فريق حجز القاعات الجامعية', $supervisor('t.hamad@ucas.edu.ps'), $l5, [$m5a, $m5b], $specWeb, 'نظام حجز القاعات والمعامل الجامعية', 'نظام إلكتروني لحجز القاعات، تجنب التضارب، وموافقة المسؤول مع إشعارات فورية.', ['نظم معلومات', 'إدارة موارد'], ProjectStatusEnum::Completed, 12);
        $this->proposal($proj5, $l5, $supervisor('t.hamad@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 24, 'review_days' => 14]);
        $this->tasks($team5, $l5, [
            ['تحليل متطلبات حجز القاعات', TaskStatusEnum::Done, 'دراسة إجراءات الحجز الحالية وتحديد المتطلبات.'],
            ['تصميم النظام وقاعدة البيانات', TaskStatusEnum::Done, 'تصميم الجداول، العلاقات، وقواعد التحقق من التعارض.'],
            ['بناء نظام الحجز والموافقات', TaskStatusEnum::Done, 'تطبيق آلية الحجز، الموافقة/الرفض، ومنع التعارض الزمني.'],
            ['لوحة الإدارة والتقارير', TaskStatusEnum::Done, 'إحصائيات الاستخدام، تقارير دورية، وسجل النشاط.'],
            ['الاختبار والتحسين', TaskStatusEnum::Done, 'اختبار سيناريوهات التعارض والصلاحيات.'],
        ]);
        $this->meetings($team5, $supervisor('t.hamad@ucas.edu.ps'), [
            ['انطلاق المشروع', -22, 'اتفاق على الأدوار والجدول الزمني.'],
            ['مراجعة النموذج الأولي', -15, 'مراجعة واجهات الحجز والملاحظات.'],
            ['عرض التقدم', -9, 'عرض نظام التحقق من التعارض.'],
            ['جاهزية التقرير', -5, 'مراجعة التقرير والملفات.'],
        ]);
        $this->finalReport($proj5, $l5, $supervisor('t.hamad@ucas.edu.ps'), $committee, ['review' => 'pending_committee', 'supervisor_days' => 4]);

        // 6) قيد التنفيذ
        $l6 = $this->member(['name' => 'حسام الزق', 'email' => 'hossam.zaq@smail.ucas.edu.ps', 'gender' => 'male'], $specWeb);
        $m6a = $this->member(['name' => 'بيان الجرو', 'email' => 'bayan.jarou2@smail.ucas.edu.ps', 'gender' => 'female'], $specWeb);
        $m6b = $this->member(['name' => 'عبدالرحمن صافي', 'email' => 'abdelrahman.safi2@smail.ucas.edu.ps', 'gender' => 'male'], $specWeb);
        [$team6, $proj6] = $this->makeTeam('فريق توصيل الطلبات الجامعية', $supervisor('g.shabat@ucas.edu.ps'), $l6, [$m6a, $m6b], $specWeb, 'نظام توصيل الطلبات داخل الحرم الجامعي', 'تطبيق لطلب الطعام والمواد من داخل الكليات وتوصيلها للطالب مع تتبع الطلب والحالة.', ['تطبيق ويب', 'خدمات'], ProjectStatusEnum::InProgress);
        $this->proposal($proj6, $l6, $supervisor('g.shabat@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 16, 'review_days' => 8]);
        $this->tasks($team6, $l6, [
            ['تحليل المتطلبات وتصميم الواجهة', TaskStatusEnum::Done, 'تصميم شاشات الطالب والتاجر والمندوب.'],
            ['بناء نظام الطلبات والسلة', TaskStatusEnum::InProgress, 'إدارة السلة، الأسعار، والخصم إن وُجد.'],
            ['نظام تتبع الطلب وحالة التوصيل', TaskStatusEnum::InProgress, 'تتبع حي، تحديث الحالة، وإشعارات.'],
            ['لوحة التاجر وإحصائيات', TaskStatusEnum::Pending, 'إدارة المنتجات، الطلبات، والتقارير.'],
            ['نظام الدفع والتقييم', TaskStatusEnum::Pending, 'طرق الدفع، تقييم التوصيل، وقاعدة البيانات.'],
        ]);
        $this->meetings($team6, $supervisor('g.shabat@ucas.edu.ps'), [
            ['اجتماع انطلاق', -10, 'مناقشة الفكرة وتقسيم الأدوار.'],
            ['مراجعة أولية', -5, 'مراجعة تصميم الواجهات.'],
            ['متابعة التقدم', -1, 'مراجعة وحدة الطلبات والتشغيل التجريبي.'],
        ]);

        // 7) مكتمل
        $l7 = $this->member(['name' => 'خالد أبو حطب', 'email' => 'khaled.abuhatab2@smail.ucas.edu.ps', 'gender' => 'male'], $specNet);
        $m7a = $this->member(['name' => 'رأفت صالح', 'email' => 'rafat.saleh2@smail.ucas.edu.ps', 'gender' => 'male'], $specNet);
        $m7b = $this->member(['name' => 'نضال حلس', 'email' => 'nidal.halas2@smail.ucas.edu.ps', 'gender' => 'male'], $specNet);
        [$team7, $proj7] = $this->makeTeam('فريق تقييم أداء الموظفين', $supervisor('m.zaanoun@ucas.edu.ps'), $l7, [$m7a, $m7b], $specNet, 'نظام تقييم أداء الموظفين الإلكتروني', 'نظام لتقييم الأداء حسب معايير موضوعية مع تقارير دورية وإحصائيات للإدارة.', ['نظم معلومات', 'إدارة'], ProjectStatusEnum::Completed, 18);
        $this->proposal($proj7, $l7, $supervisor('m.zaanoun@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 26, 'review_days' => 16]);
        $this->tasks($team7, $l7, [
            ['تحليل المعايير وقاعدة البيانات', TaskStatusEnum::Done, 'تحديد معايير التقييم، أوزانها، والمراحل.'],
            ['بناء نماذج التقييم', TaskStatusEnum::Done, 'استمارات التقييم، التفويض، وسجل التقييمات.'],
            ['حساب الدرجات والتقارير', TaskStatusEnum::Done, 'حساب النتائج آليًا، تصنيف الأداء، وطباعة التقارير.'],
            ['صلاحيات وأدوار', TaskStatusEnum::Done, 'إدارة الأدوار، حماية البيانات، وسجل الدخول.'],
            ['الاختبار النهائي', TaskStatusEnum::Done, 'اختبار شامل والتأكد من دقة الحسابات.'],
        ]);
        $this->meetings($team7, $supervisor('m.zaanoun@ucas.edu.ps'), [
            ['انطلاق', -24, 'مناقشة متطلبات النظام.'],
            ['مراجعة أولية', -18, 'مراجعة معايير التقييم.'],
            ['عرض مرحلي', -12, 'عرض نماذج التقييم.'],
            ['مراجعة نهائية', -6, 'مراجعة التقرير ونتائج الاختبار.'],
        ]);
        $this->finalReport($proj7, $l7, $supervisor('m.zaanoun@ucas.edu.ps'), $committee, ['review' => 'approved', 'supervisor_days' => 7, 'committee_days' => 4]);

        // 8) مقترح
        $l8 = $this->member(['name' => 'جمال بريك', 'email' => 'jamal.breek2@smail.ucas.edu.ps', 'gender' => 'male'], $specAi);
        $m8a = $this->member(['name' => 'لارا الحلاق', 'email' => 'lara.hallak@smail.ucas.edu.ps', 'gender' => 'female'], $specAi);
        [$team8, $proj8] = $this->makeTeam('فريق اللعبة التعليمية التفاعلية', $supervisor('h.zein@ucas.edu.ps'), $l8, [$m8a], $specAi, 'لعبة تعليمية تفاعلية لطلاب المدارس', 'لعبة تعليمية تجمع بين المتعة والتعلم مع مستويات صعبة، شارات، ولوحة المتصدرين.', ['ألعاب تعليمية', 'ذكاء اصطناعي'], ProjectStatusEnum::Proposed);
        $this->proposal($proj8, $l8, $supervisor('h.zein@ucas.edu.ps'), ['state' => 'pending', 'submitted_days' => 7]);

        // 9) قيد التنفيذ
        $l9 = $this->member(['name' => 'محمود الجعبري', 'email' => 'mahmoud.jaabari2@smail.ucas.edu.ps', 'gender' => 'male'], $specWeb);
        $m9a = $this->member(['name' => 'إسراء حمدان', 'email' => 'esraa.hamdan2@smail.ucas.edu.ps', 'gender' => 'female'], $specWeb);
        $m9b = $this->member(['name' => 'سيف العالول', 'email' => 'saif.alaloul@smail.ucas.edu.ps', 'gender' => 'male'], $specWeb);
        [$team9, $proj9] = $this->makeTeam('فريق توثيق فعاليات الحرم الجامعي', $supervisor('a.nasser@ucas.edu.ps'), $l9, [$m9a, $m9b], $specWeb, 'نظام توثيق فعاليات وأنشطة الحرم الجامعي', 'منصة أرشفة للفعاليات مع معرض صور، تقارير الحضور، والتقويم التفاعلي.', ['تطوير ويب', 'أرشفة رقمية'], ProjectStatusEnum::InProgress);
        $this->proposal($proj9, $l9, $supervisor('a.nasser@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 15, 'review_days' => 7]);
        $this->tasks($team9, $l9, [
            ['تحليل المتطلبات وتصميم قاعدة البيانات', TaskStatusEnum::Done, 'تصميم هيكل النظام وجدول الفعاليات.'],
            ['بناء نظام إضافة الفعاليات والتقويم', TaskStatusEnum::InProgress, 'إضافة فعاليات، تصنيفات، وسحب لعرض التقويم.'],
            ['معرض الصور والألبومات', TaskStatusEnum::InProgress, 'رفع صور، تصنيفات، وعرض حسب الحدث.'],
            ['تقارير الحضور والإحصائيات', TaskStatusEnum::Pending, 'تسجيل الحضور، تقارير، ورسم بياني.'],
        ]);
        $this->meetings($team9, $supervisor('a.nasser@ucas.edu.ps'), [
            ['انطلاق', -9, 'اتفاق الخطة.'],
            ['مراجعة أولية', -4, 'مراجعة التصميم.'],
        ]);

        // 10) قيد التنفيذ
        $l10 = $this->member(['name' => 'باسل قديح', 'email' => 'basel.qdeih2@smail.ucas.edu.ps', 'gender' => 'male'], $specNet);
        $m10a = $this->member(['name' => 'يحيى الشيخ', 'email' => 'yahya.sheikh2@smail.ucas.edu.ps', 'gender' => 'male'], $specNet);
        $m10b = $this->member(['name' => 'علي الخضري', 'email' => 'ali.khader2@smail.ucas.edu.ps', 'gender' => 'male'], $specNet);
        [$team10, $proj10] = $this->makeTeam('فريق تتبع حافلات الجامعة', $supervisor('m.zaanoun@ucas.edu.ps'), $l10, [$m10a, $m10b], $specNet, 'نظام تتبع حافلات الجامعة في الوقت الفعلي', 'نظام لعرض موقع الحافلة، المسارات، أوقات الوصول التقديرية، والتنبيهات.', ['إنترنت الأشياء', 'شبكات'], ProjectStatusEnum::InProgress);
        $this->proposal($proj10, $l10, $supervisor('m.zaanoun@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 14, 'review_days' => 6]);
        $this->tasks($team10, $l10, [
            ['دراسة المسارات وتجميع البيانات', TaskStatusEnum::Done, 'رسم المسارات، نقاط التوقف، وجداول السير.'],
            ['تصميم نظام التتبع', TaskStatusEnum::InProgress, 'تصميم API لتحديث الموقع ولوحة العرض.'],
            ['تطبيق خريطة تفاعلية', TaskStatusEnum::InProgress, 'عرض الموقع، المسار، والوقت التقديري.'],
            ['إشعارات وتنبيهات', TaskStatusEnum::Pending, 'تنبيهات الاقتراب، تأخر الرحلة، وتغيير المسار.'],
        ]);
        $this->meetings($team10, $supervisor('m.zaanoun@ucas.edu.ps'), [
            ['انطلاق', -8, 'مناقشة الخطة.'],
            ['مراجعة التقدم', -3, 'مراجعة تصميم التتبع.'],
        ]);

        // 11) مكتمل
        $l11 = $this->member(['name' => 'أمجد دغمش', 'email' => 'amjad.daghmesh2@smail.ucas.edu.ps', 'gender' => 'male'], $specWeb);
        $m11a = $this->member(['name' => 'رنيم شقير', 'email' => 'raneem.shaqeer2@smail.ucas.edu.ps', 'gender' => 'female'], $specWeb);
        $m11b = $this->member(['name' => 'ميس الريماوي', 'email' => 'mais.rimawi@smail.ucas.edu.ps', 'gender' => 'female'], $specWeb);
        [$team11, $proj11] = $this->makeTeam('فريق منصة التطوع الجامعي', $supervisor('e.abbadleh@ucas.edu.ps'), $l11, [$m11a, $m11b], $specWeb, 'منصة لإدارة فرص التطوع في الجامعة', 'منصة لعرض فرص التطوع، التسجيل، توثيق الساعات، وإصدار شهادات إلكترونية.', ['تطوير ويب', 'مجتمع'], ProjectStatusEnum::Completed, 10);
        $this->proposal($proj11, $l11, $supervisor('e.abbadleh@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 20, 'review_days' => 10]);
        $this->tasks($team11, $l11, [
            ['تحليل المتطلبات', TaskStatusEnum::Done, 'جمع متطلبات الوحدات، الطلاب، والمنسق.'],
            ['بناء إدارة الفرص والتسجيل', TaskStatusEnum::Done, 'نشر الفرص، قبول/رفض، قوائم المتطوعين.'],
            ['توثيق الساعات والشهادات', TaskStatusEnum::Done, 'حساب الساعات، إصدار شهادة PDF، وأرشفة.'],
            ['تقارير وإحصائيات', TaskStatusEnum::Done, 'تقارير حسب الكلية، الفترة، والنوع.'],
            ['الاختبار والتسليم', TaskStatusEnum::Done, 'اختبار شامل وجهوزية للتقديم.'],
        ]);
        $this->meetings($team11, $supervisor('e.abbadleh@ucas.edu.ps'), [
            ['انطلاق', -18, 'مناقشة متطلبات المنصة.'],
            ['عرض مرحلي', -12, 'مراجعة نظام التسجيل.'],
            ['مراجعة الشهادات', -7, 'مراجعة نظام إصدار الشهادات.'],
            ['مراجعة نهائية', -3, 'جاهزية التقرير.'],
        ]);
        $this->finalReport($proj11, $l11, $supervisor('e.abbadleh@ucas.edu.ps'), $committee, ['review' => 'pending_committee', 'supervisor_days' => 5]);

        // 12) قيد التنفيذ
        $l12 = $this->member(['name' => 'سائد دغلس', 'email' => 'saed.daghles2@smail.ucas.edu.ps', 'gender' => 'male'], $specSoft);
        $m12a = $this->member(['name' => 'هبة الرملاوي', 'email' => 'hiba.ramlawi2@smail.ucas.edu.ps', 'gender' => 'female'], $specSoft);
        $m12b = $this->member(['name' => 'ملك النيرب', 'email' => 'malak.nayrab2@smail.ucas.edu.ps', 'gender' => 'female'], $specSoft);
        [$team12, $proj12] = $this->makeTeam('فريق الدعم النفسي للطلاب', $supervisor('e.abbadleh@ucas.edu.ps'), $l12, [$m12a, $m12b], $specSoft, 'منصة الدعم النفسي والإرشاد للطلاب', 'منصة آمنة لطلب الاستشارة، حجز مواعيد مع المرشد النفسي، ومصادر توعية مع الحفاظ على السرية.', ['نظم معلومات', 'صحة نفسية'], ProjectStatusEnum::InProgress);
        $this->proposal($proj12, $l12, $supervisor('e.abbadleh@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 13, 'review_days' => 6]);
        $this->tasks($team12, $l12, [
            ['تحليل المتطلبات وحماية البيانات', TaskStatusEnum::Done, 'ضمان السرية، صلاحيات، وإخفاء الهوية عند الحاجة.'],
            ['بناء نظام حجز الاستشارات', TaskStatusEnum::InProgress, 'حجز مواعيد، تأكيد، وجدول المرشد.'],
            ['مكتبة التوعية النفسية', TaskStatusEnum::InProgress, 'مقالات، فيديوهات، وتمارين مع تصنيفات.'],
            ['نظام المتابعة والتقييم', TaskStatusEnum::Pending, 'متابعة الحالة، استبيانات الرضا، وتقارير إحصائية.'],
        ]);
        $this->meetings($team12, $supervisor('e.abbadleh@ucas.edu.ps'), [
            ['انطلاق', -7, 'مناقشة متطلبات السرية.'],
            ['مراجعة', -3, 'مراجعة نظام الحجز.'],
        ]);

        // 13) مكتمل
        $l13 = $this->member(['name' => 'فادي عيسى', 'email' => 'fadi.eissa2@smail.ucas.edu.ps', 'gender' => 'male'], $specSoft);
        $m13a = $this->member(['name' => 'يارا الحلو', 'email' => 'yara.halou@smail.ucas.edu.ps', 'gender' => 'female'], $specSoft);
        $m13b = $this->member(['name' => 'ريم عوض', 'email' => 'reem.awad@smail.ucas.edu.ps', 'gender' => 'female'], $specSoft);
        $m13c = $this->member(['name' => 'أحمد أبو هنية', 'email' => 'ahmad.abuhaniya@smail.ucas.edu.ps', 'gender' => 'male'], $specSoft);
        [$team13, $proj13] = $this->makeTeam('فريق أرشفة الرسائل الإلكترونية', $supervisor('saed.abunada@ucas.edu.ps'), $l13, [$m13a, $m13b, $m13c], $specSoft, 'نظام أرشفة وبحث الرسائل الإلكترونية المؤسسية', 'نظام لأرشفة الرسائل، تصنيفها، بحث متقدم، صلاحيات، ونسخ احتياطي.', ['أرشفة رقمية', 'قواعد بيانات'], ProjectStatusEnum::Completed, 14);
        $this->proposal($proj13, $l13, $supervisor('saed.abunada@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 21, 'review_days' => 11]);
        $this->tasks($team13, $l13, [
            ['تحليل المتطلبات وبنية الأرشفة', TaskStatusEnum::Done, 'تصميم هيكل التصنيف وفهرسة الرسائل.'],
            ['بناء نظام الاستيراد والبحث', TaskStatusEnum::Done, 'استيراد، فهرسة، وبحث نصي متقدم.'],
            ['صلاحيات وسجلات التدقيق', TaskStatusEnum::Done, 'صلاحيات دقيقة، سجل نشاط، وتتبع الوصول.'],
            ['تقارير وإحصائيات الأرشفة', TaskStatusEnum::Done, 'تقارير حسب القسم، التاريخ، والتصنيف.'],
            ['الاختبار النهائي', TaskStatusEnum::Done, 'اختبار الأداء والبحث وحفظ البيانات.'],
        ]);
        $this->meetings($team13, $supervisor('saed.abunada@ucas.edu.ps'), [
            ['انطلاق', -19, 'مناقشة متطلبات الأرشفة.'],
            ['مراجعة أولية', -14, 'مراجعة نموذج الفهرسة.'],
            ['عرض مرحلي', -9, 'عرض نظام البحث.'],
            ['جاهزية', -4, 'مراجعة التقرير.'],
        ]);
        $this->finalReport($proj13, $l13, $supervisor('saed.abunada@ucas.edu.ps'), $committee, ['review' => 'approved', 'supervisor_days' => 6, 'committee_days' => 3]);

        // 14) مقترح
        $l14 = $this->member(['name' => 'أنس الأسطل', 'email' => 'anas.astal2@smail.ucas.edu.ps', 'gender' => 'male'], $specWeb);
        $m14a = $this->member(['name' => 'جنى العجل', 'email' => 'jana.ajel2@smail.ucas.edu.ps', 'gender' => 'female'], $specWeb);
        [$team14, $proj14] = $this->makeTeam('فريق الهوية البصرية للكلية', $supervisor('doaa.alagha@ucas.edu.ps'), $l14, [$m14a], $specWeb, 'منصة إدارة الهوية البصرية للكلية', 'منصة لعرض دليل الهوية، قوالب جاهزة، وخدمة طلب المواد الدعائية مع نظام موافقات.', ['تصميم', 'إدارة'], ProjectStatusEnum::Proposed);
        $this->proposal($proj14, $l14, $supervisor('doaa.alagha@ucas.edu.ps'), ['state' => 'pending', 'submitted_days' => 6]);

        // 15) قيد التنفيذ
        $l15 = $this->member(['name' => 'حسام البيوك', 'email' => 'hossam.bayouk2@smail.ucas.edu.ps', 'gender' => 'male'], $specNet);
        $m15a = $this->member(['name' => 'محمود أبو وردة', 'email' => 'mahmoud.abuwarda@smail.ucas.edu.ps', 'gender' => 'male'], $specNet);
        $m15b = $this->member(['name' => 'أحمد المدهون', 'email' => 'ahmad.madhoun@smail.ucas.edu.ps', 'gender' => 'male'], $specNet);
        [$team15, $proj15] = $this->makeTeam('فريق نظام مواقف السيارات الذكي', $supervisor('h.abusharkh@ucas.edu.ps'), $l15, [$m15a, $m15b], $specNet, 'نظام ذكي لإدارة مواقف السيارات داخل الجامعة', 'نظام لعرض المواقف المتاحة، حجز سريع، تتبع الدخول/الخروج، وتنبيهات مع تقارير الاستخدام.', ['إنترنت الأشياء', 'شبكات'], ProjectStatusEnum::InProgress);
        $this->proposal($proj15, $l15, $supervisor('h.abusharkh@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 12, 'review_days' => 6]);
        $this->tasks($team15, $l15, [
            ['رسم خطة المواقف وتقسيمها', TaskStatusEnum::Done, 'تحديد المناطق، عدد المواقف، ونقاط الدخول/الخروج.'],
            ['تصميم نظام الحجز والعرض', TaskStatusEnum::InProgress, 'عرض المواقف المتاحة في الوقت الفعلي.'],
            ['نظام الدخول/الخروج والتقارير', TaskStatusEnum::InProgress, 'سجل الدخول والخروج، إحصائيات الاستخدام.'],
            ['لوحة الإدارة والتنبيهات', TaskStatusEnum::Pending, 'إدارة المواقف، البلاغات، والتنبيهات.'],
        ]);
        $this->meetings($team15, $supervisor('h.abusharkh@ucas.edu.ps'), [
            ['انطلاق', -7, 'مناقشة متطلبات المشروع.'],
            ['مراجعة', -3, 'مراجعة تصميم النظام.'],
        ]);

        // 16) مكتمل
        $l16 = $this->member(['name' => 'عمر شحادة', 'email' => 'omar.shahada2@smail.ucas.edu.ps', 'gender' => 'male'], $specWeb);
        $m16a = $this->member(['name' => 'ميار قشطة', 'email' => 'miar.qeshta@smail.ucas.edu.ps', 'gender' => 'female'], $specWeb);
        $m16b = $this->member(['name' => 'نغم أبو عكر', 'email' => 'naghm.abuakar@smail.ucas.edu.ps', 'gender' => 'female'], $specWeb);
        [$team16, $proj16] = $this->makeTeam('فريق تبادل الكتب الجامعية', $supervisor('r.adwan@ucas.edu.ps'), $l16, [$m16a, $m16b], $specWeb, 'منصة تبادل وبيع الكتب الدراسية المستعملة', 'منصة لطلاب الجامعة لعرض الكتب، التبادل، أو البيع مع تصنيفات حسب الكلية والمقرر.', ['تطوير ويب', 'مجتمع'], ProjectStatusEnum::Completed, 11);
        $this->proposal($proj16, $l16, $supervisor('r.adwan@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 19, 'review_days' => 9]);
        $this->tasks($team16, $l16, [
            ['تحليل المتطلبات وتصميم الواجهة', TaskStatusEnum::Done, 'تصميم صفحات العرض، البحث، والتواصل.'],
            ['نظام الإعلانات والتصنيفات', TaskStatusEnum::Done, 'إضافة إعلانات، تصنيفات حسب المادة، وحالة الكتاب.'],
            ['نظام الرسائل والتواصل', TaskStatusEnum::Done, 'رسائل خاصة بين الطلاب، بلاغات، وحظر.'],
            ['البحث والفلاتر والتقييم', TaskStatusEnum::Done, 'بحث متقدم، فلاتر، تقييم البائع.'],
            ['الاختبار والتسليم', TaskStatusEnum::Done, 'اختبار شامل وجهوزية.'],
        ]);
        $this->meetings($team16, $supervisor('r.adwan@ucas.edu.ps'), [
            ['انطلاق', -17, 'مناقشة الفكرة.'],
            ['عرض مرحلي', -11, 'مراجعة نظام الإعلانات.'],
            ['مراجعة نهائية', -4, 'جاهزية التقرير.'],
        ]);
        $this->finalReport($proj16, $l16, $supervisor('r.adwan@ucas.edu.ps'), $committee, ['review' => 'rejected', 'supervisor_days' => 5, 'committee_days' => 2, 'note' => 'يجب توسيع فصل النتائج وتحليل تجربة المستخدم، وإضافة مراجع علمية مناسبة، وتحسين توثيق النظام.']);

        // 17) مقترح مرفوض
        $l17 = $this->member(['name' => 'إبراهيم الكرد', 'email' => 'ibrahim.kurd2@smail.ucas.edu.ps', 'gender' => 'male'], $specNet);
        $m17a = $this->member(['name' => 'أحمد البرغوثي', 'email' => 'ahmad.barghouthi@smail.ucas.edu.ps', 'gender' => 'male'], $specNet);
        [$team17, $proj17] = $this->makeTeam('فريق إدارة المختبرات الجامعية', $supervisor('h.abusharkh@ucas.edu.ps'), $l17, [$m17a], $specNet, 'نظام إدارة المختبرات وحجز الأجهزة', 'نظام لحجز أجهزة المختبر، تتبع الصيانة، وجدول الاستخدام مع تقارير للإدارة.', ['شبكات', 'إدارة موارد'], ProjectStatusEnum::Proposed);
        $this->proposal($proj17, $l17, $supervisor('h.abusharkh@ucas.edu.ps'), ['state' => 'rejected', 'submitted_days' => 17, 'reasons' => ['نطاق المشروع واسع جدًا ولا يتناسب مع متطلبات مشروع التخرج.', 'ينبغي التركيز على جزء محدد وقابل للتطبيق ضمن الوقت المخصص.']]);

        // 18) قيد التنفيذ
        $l18 = $this->member(['name' => 'محمد الحاج', 'email' => 'mohammed.haj2@smail.ucas.edu.ps', 'gender' => 'male'], $specAi);
        $m18a = $this->member(['name' => 'سيرين العكر', 'email' => 'sireen.akar@smail.ucas.edu.ps', 'gender' => 'female'], $specAi);
        $m18b = $this->member(['name' => 'يارا شبير', 'email' => 'yara.shbeir@smail.ucas.edu.ps', 'gender' => 'female'], $specAi);
        [$team18, $proj18] = $this->makeTeam('فريق البودكاست الأكاديمي', $supervisor('y.matar@ucas.edu.ps'), $l18, [$m18a, $m18b], $specAi, 'منصة بودكاست أكاديمي لطلاب وأعضاء هيئة التدريس', 'منصة لنشر الحلقات الصوتية، التصنيفات، الاستماع، والتعليقات مع إحصائيات الاستماع.', ['وسائط متعددة', 'تعليم إلكتروني'], ProjectStatusEnum::InProgress);
        $this->proposal($proj18, $l18, $supervisor('y.matar@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 11, 'review_days' => 5]);
        $this->tasks($team18, $l18, [
            ['تصميم المنصة وبنية المحتوى', TaskStatusEnum::Done, 'تصميم صفحات البودكاست، المشغل، وقائمة الحلقات.'],
            ['نظام رفع الحلقات والتصنيفات', TaskStatusEnum::InProgress, 'رفع صوت، غلاف، وصف، وتحديد التصنيف.'],
            ['مشغل صوتي وإحصائيات الاستماع', TaskStatusEnum::InProgress, 'مشغل HTML5، عد المشاهدات، وإحصائيات.'],
            ['التعليقات والمفضلة', TaskStatusEnum::Pending, 'تعليقات، مفضلة، ومتابعة القنوات.'],
        ]);
        $this->meetings($team18, $supervisor('y.matar@ucas.edu.ps'), [
            ['انطلاق', -6, 'مناقشة الخطة.'],
            ['مراجعة', -2, 'مراجعة التصميم.'],
        ]);

        // 19) مكتمل
        $l19 = $this->member(['name' => 'أحمد أبو ليلة', 'email' => 'ahmad.abuleila2@smail.ucas.edu.ps', 'gender' => 'male'], $specSoft);
        $m19a = $this->member(['name' => 'وعد أبو جاموس', 'email' => 'waad.abujamous@smail.ucas.edu.ps', 'gender' => 'female'], $specSoft);
        $m19b = $this->member(['name' => 'تالا شاهين', 'email' => 'tala.shaheen@smail.ucas.edu.ps', 'gender' => 'female'], $specSoft);
        [$team19, $proj19] = $this->makeTeam('فريق الإرشاد الأكاديمي الذكي', $supervisor('a.nasser@ucas.edu.ps'), $l19, [$m19a, $m19b], $specSoft, 'نظام الإرشاد الأكاديمي الذكي للطلاب', 'نظام يقترح المساقات، يراقب التقدم الأكاديمي، ويصدر تنبيهات مبكرة للطلاب المعرضين للخطر.', ['أنظمة ذكية', 'تعليم إلكتروني'], ProjectStatusEnum::Completed, 15);
        $this->proposal($proj19, $l19, $supervisor('a.nasser@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 23, 'review_days' => 13]);
        $this->tasks($team19, $l19, [
            ['تحليل الخطة الدراسية وقواعد البيانات', TaskStatusEnum::Done, 'بناء قاعدة بيانات الخطة الدراسية والمتطلبات.'],
            ['نظام تتبع التقدم الأكاديمي', TaskStatusEnum::Done, 'حساب الساعات، المتبقي، والنسبة.'],
            ['اقتراح المساقات الذكي', TaskStatusEnum::Done, 'اقتراح بناءً على المتطلبات السابقة والأولوية.'],
            ['تنبيهات مبكرة ولوحة المرشد', TaskStatusEnum::Done, 'تنبيهات للطلاب المعرضين للخطر ولوحة المتابعة.'],
            ['الاختبار والتسليم', TaskStatusEnum::Done, 'اختبار شامل.'],
        ]);
        $this->meetings($team19, $supervisor('a.nasser@ucas.edu.ps'), [
            ['انطلاق', -21, 'مناقشة الخطة.'],
            ['مراجعة أولية', -15, 'مراجعة نموذج الاقتراح.'],
            ['عرض مرحلي', -10, 'عرض تتبع التقدم.'],
            ['جاهزية', -5, 'مراجعة التقرير.'],
        ]);
        $this->finalReport($proj19, $l19, $supervisor('a.nasser@ucas.edu.ps'), $committee, ['review' => 'approved', 'supervisor_days' => 7, 'committee_days' => 4]);

        // 20) مقترح
        $l20 = $this->member(['name' => 'محمود الجعبري', 'email' => 'mahmoud.jaabari3@smail.ucas.edu.ps', 'gender' => 'male'], $specAi);
        $m20a = $this->member(['name' => 'هالة قديح', 'email' => 'hala.qdeih@smail.ucas.edu.ps', 'gender' => 'female'], $specAi);
        [$team20, $proj20] = $this->makeTeam('فريق تقييم مطاعم الحرم الجامعي', $supervisor('r.adwan@ucas.edu.ps'), $l20, [$m20a], $specAi, 'نظام تقييم ومقارنة مطاعم الحرم الجامعي', 'نظام لتقييم المطاعم، الأصناف، الأسعار، وسرعة الخدمة مع تصنيفات وإحصائيات.', ['تطوير ويب', 'مجتمع'], ProjectStatusEnum::Proposed);
        $this->proposal($proj20, $l20, $supervisor('r.adwan@ucas.edu.ps'), ['state' => 'pending', 'submitted_days' => 5]);

        // 21) أتمتة نماذج الدوام - د. سائد أبو ندى (جديد)
        $l21 = $this->member(['name' => 'أنس الأسطل', 'email' => 'anas.astal3@smail.ucas.edu.ps', 'gender' => 'male'], $specSoft);
        $m21a = $this->member(['name' => 'ياسمين أبو زيد', 'email' => 'yasmeen.abuzid@smail.ucas.edu.ps', 'gender' => 'female'], $specSoft);
        $m21b = $this->member(['name' => 'رزان النجار', 'email' => 'razan.najjar@smail.ucas.edu.ps', 'gender' => 'female'], $specSoft);
        [$team21, $proj21] = $this->makeTeam('فريق أتمتة نماذج الدوام الإلكتروني', $supervisor('saed.abunada@ucas.edu.ps'), $l21, [$m21a, $m21b], $specSoft, 'نظام أتمتة نماذج الدوام ورصد الحضور الإلكتروني', 'نظام لإدارة الدوام، رصد الحضور، تقارير الغياب، وصلاحيات الأقسام مع تكامل بسيط.', ['أتمتة', 'نظم معلومات'], ProjectStatusEnum::InProgress);
        $this->proposal($proj21, $l21, $supervisor('saed.abunada@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 14, 'review_days' => 7]);
        $this->tasks($team21, $l21, [
            ['تحليل إجراءات الدوام الحالية', TaskStatusEnum::Done, 'دراسة النموذج الورقي ومتطلبات الأقسام.'],
            ['تصميم قاعدة البيانات والصلاحيات', TaskStatusEnum::Done, 'تصميم الجداول، الأدوار، وسجل التدقيق.'],
            ['بناء نموذج الدوام الإلكتروني', TaskStatusEnum::InProgress, 'استمارة الدوام، التوقيع، والتحقق من الوقت.'],
            ['تقارير الحضور والغياب', TaskStatusEnum::InProgress, 'تقارير يومية، شهرية، وإحصائيات.'],
            ['إشعارات ورصد المتأخرين', TaskStatusEnum::Pending, 'تنبيهات للطلاب والمدرسين وإدارة الاستثناءات.'],
        ]);
        $this->meetings($team21, $supervisor('saed.abunada@ucas.edu.ps'), [
            ['اجتماع انطلاق', -9, 'مناقشة متطلبات النظام.'],
            ['متابعة التقدم', -4, 'مراجعة نموذج الدوام.'],
        ]);

        // 22) مقترح - م. دعاء الأغا
        $l22 = $this->member(['name' => 'خالد أبو حطب', 'email' => 'khaled.abuhatab3@smail.ucas.edu.ps', 'gender' => 'male'], $specWeb);
        $m22a = $this->member(['name' => 'هديل أبو جراد', 'email' => 'hadeel.abujarad@smail.ucas.edu.ps', 'gender' => 'female'], $specWeb);
        [$team22, $proj22] = $this->makeTeam('فريق منصة دعم ذوي الاحتياجات الخاصة', $supervisor('doaa.alagha@ucas.edu.ps'), $l22, [$m22a], $specWeb, 'منصة دعم ذوي الاحتياجات الخاصة في البيئة الجامعية', 'منصة لتسهيل الخدمات، حجز المرافق المناسبة، طلب المساعدة، ومصادر توعية مع مراعاة سهولة الوصول.', ['تصميم يسهل الوصول', 'خدمات'], ProjectStatusEnum::Proposed);
        $this->proposal($proj22, $l22, $supervisor('doaa.alagha@ucas.edu.ps'), ['state' => 'pending', 'submitted_days' => 7]);

        // 23) قيد التنفيذ - أ. بلال الشريف
        $l23 = $this->member(['name' => 'فادي عيسى', 'email' => 'fadi.eissa3@smail.ucas.edu.ps', 'gender' => 'male'], $specAi);
        $m23a = $this->member(['name' => 'نغم شحاتة', 'email' => 'naghm.shahata@smail.ucas.edu.ps', 'gender' => 'female'], $specAi);
        $m23b = $this->member(['name' => 'سارة أبو هولي', 'email' => 'sara.abholey@smail.ucas.edu.ps', 'gender' => 'female'], $specAi);
        [$team23, $proj23] = $this->makeTeam('فريق نظام إدارة العيادة الجامعية الذكي', $supervisor('bilal.alsharif@ucas.edu.ps'), $l23, [$m23a, $m23b], $specAi, 'نظام ذكي لإدارة العيادة الجامعية والملفات الطبية', 'نظام لإدارة المواعيد، السجلات الطبية، الأدوية، والتقارير مع مراعاة خصوصية البيانات.', ['أنظمة ذكية', 'صحة'], ProjectStatusEnum::InProgress);
        $this->proposal($proj23, $l23, $supervisor('bilal.alsharif@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 13, 'review_days' => 6]);
        $this->tasks($team23, $l23, [
            ['تحليل متطلبات العيادة والخصوصية', TaskStatusEnum::Done, 'دراسة الإجراءات، متطلبات السرية، وحفظ السجلات.'],
            ['بناء نظام المواعيد والملفات', TaskStatusEnum::InProgress, 'ملف المريض، التاريخ المرضي، والوصفات.'],
            ['إدارة المخزون والتقارير', TaskStatusEnum::InProgress, 'الأدوية، العلاجات، وتقارير إحصائية.'],
            ['تنبيهات ومتابعة', TaskStatusEnum::Pending, 'تنبيهات المواعيد، متابعة الحالات، وسجل الزيارات.'],
        ]);
        $this->meetings($team23, $supervisor('bilal.alsharif@ucas.edu.ps'), [
            ['انطلاق', -8, 'مناقشة متطلبات النظام.'],
            ['مراجعة التقدم', -3, 'مراجعة نظام الملفات.'],
        ]);

        // 24) مكتمل - د. سائد أبو ندى
        $l24 = $this->member(['name' => 'ياسر النجار', 'email' => 'yaser.najjar2@smail.ucas.edu.ps', 'gender' => 'male'], $specSoft);
        $m24a = $this->member(['name' => 'لينا كحيل', 'email' => 'lina.kaheel2@smail.ucas.edu.ps', 'gender' => 'female'], $specSoft);
        $m24b = $this->member(['name' => 'ريناد العصيبي', 'email' => 'renad.asaibi@smail.ucas.edu.ps', 'gender' => 'female'], $specSoft);
        [$team24, $proj24] = $this->makeTeam('فريق تحليل انطباعات الطلبة بالذكاء الاصطناعي', $supervisor('saed.abunada@ucas.edu.ps'), $l24, [$m24a, $m24b], $specSoft, 'نظام تحليل انطباعات الطلبة وتحليل المشاعر', 'نظام يستخدم معالجة اللغات الطبيعية لتحليل آراء الطلبة، تصنيف المشاعر، ورصد مؤشرات الرضا مع تقارير.', ['ذكاء اصطناعي', 'معالجة لغات طبيعية'], ProjectStatusEnum::Completed, 13);
        $this->proposal($proj24, $l24, $supervisor('saed.abunada@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 20, 'review_days' => 10]);
        $this->tasks($team24, $l24, [
            ['جمع البيانات وتصميم النموذج', TaskStatusEnum::Done, 'جمع آراء الطلبة، تنظيف البيانات، وتصميم النموذج.'],
            ['معالجة اللغات الطبيعية وتصنيف المشاعر', TaskStatusEnum::Done, 'تصنيف إيجابي/سلبي/محايد مع دقة مقبولة.'],
            ['بناء لوحة التحليل والتقارير', TaskStatusEnum::Done, 'رسوم بيانية، سحابة كلمات، وتقارير حسب القسم.'],
            ['التدريب والاختبار والتحسين', TaskStatusEnum::Done, 'تحسين الدقة، اختبار على بيانات حقيقية، والتوثيق.'],
            ['التسليم النهائي', TaskStatusEnum::Done, 'التقرير، العرض، والملفات.'],
        ]);
        $this->meetings($team24, $supervisor('saed.abunada@ucas.edu.ps'), [
            ['انطلاق', -18, 'مناقشة متطلبات التحليل.'],
            ['مراجعة النموذج', -12, 'مراجعة نتائج التصنيف.'],
            ['عرض مرحلي', -7, 'عرض لوحة التحليل.'],
            ['جاهزية', -3, 'مراجعة التقرير.'],
        ]);
        $this->finalReport($proj24, $l24, $supervisor('saed.abunada@ucas.edu.ps'), $committee, ['review' => 'pending_supervisor', 'supervisor_days' => 2]);

        // 25) مميز
        $l25 = $this->member(['name' => 'عمر شحادة', 'email' => 'omar.shahada3@smail.ucas.edu.ps', 'gender' => 'male'], $specSoft);
        $m25a = $this->member(['name' => 'سارة الدجاني', 'email' => 'sara.dajani2@smail.ucas.edu.ps', 'gender' => 'female'], $specSoft);
        $m25b = $this->member(['name' => 'أحمد قندس', 'email' => 'ahmad.qandil2@smail.ucas.edu.ps', 'gender' => 'male'], $specSoft);
        $m25c = $this->member(['name' => 'نور الدين أبو شمالة', 'email' => 'nour.abushamala@smail.ucas.edu.ps', 'gender' => 'male'], $specSoft);
        [$team25, $proj25] = $this->makeTeam('فريق تسجيل الحضور بالتعرف على الوجه', $supervisor('supervisor@ucas.edu.ps'), $l25, [$m25a, $m25b, $m25c], $specSoft, 'نظام تسجيل الحضور بالوجه باستخدام التعرف البصري', 'نظام ذكي لتسجيل الحضور تلقائياً باستخدام التعرف على الوجوه، تقارير يومية، وإدارة المحاضرات.', ['ذكاء اصطناعي', 'رؤية حاسوبية'], ProjectStatusEnum::Completed, 9);
        $this->proposal($proj25, $l25, $supervisor('supervisor@ucas.edu.ps'), ['state' => 'approved', 'submitted_days' => 28, 'review_days' => 18]);
        $this->tasks($team25, $l25, [
            ['دراسة خوارزميات التعرف على الوجه', TaskStatusEnum::Done, 'مقارنة الخوارزميات واختيار الأنسب للتطبيق.'],
            ['جمع بيانات التدريب وبناء النموذج', TaskStatusEnum::Done, 'جمع صور، تدريب النموذج، وتحسين الدقة.'],
            ['بناء نظام التقاط التسجيل', TaskStatusEnum::Done, 'كاميرا، كشف الوجه، مقارنة، وتسجيل الحضور.'],
            ['تقارير الحضور ولوحة الإدارة', TaskStatusEnum::Done, 'تقارير يومية، إحصائيات، وصلاحيات المدرس.'],
            ['الاختبار والتكامل والتسليم', TaskStatusEnum::Done, 'اختبار في بيئة حقيقية، توثيق، وتسليم.'],
        ]);
        $this->meetings($team25, $supervisor('supervisor@ucas.edu.ps'), [
            ['انطلاق المشروع', -26, 'اتفاق على الخطة والتقنيات.'],
            ['مراجعة النموذج الأولي', -19, 'مراجعة دقة التعرف.'],
            ['عرض مرحلي', -13, 'عرض نظام التسجيل.'],
            ['مراجعة التقرير النهائي', -6, 'مناقشة التقرير وجهوزية المناقشة.'],
        ]);
        $this->finalReport($proj25, $l25, $supervisor('supervisor@ucas.edu.ps'), $committee, ['review' => 'approved', 'supervisor_days' => 5, 'committee_days' => 3]);
        $proj25->update(['is_featured' => true]);
    }
}
