<?php

namespace Database\Seeders;

use App\Enums\ProjectStatusEnum;
use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\AcademicTerm;
use App\Models\Specialization;
use App\Models\User;

/** بيانات سنة دراسية جديدة قيد الانطلاق — فرق بمراحل مبكرة (مقترحات ومهام أولى)، بلا أي مشروع مكتمل بعد */
class NextTermDemoSeeder extends DemoDataSeeder
{
    public function run(): void
    {
        $this->termId = AcademicTerm::where('name', 'السنة الدراسية 2027/2028')->value('id');
        $this->studentSeq = 600;

        $web = Specialization::where('name', 'تصميم وتطوير مواقع الويب')->first();
        $db = Specialization::where('name', 'برمجيات وقواعد بيانات')->first();
        $mobile = Specialization::where('name', 'تصميم وبرمجة تطبيقات الموبايل')->first();
        $mediaDiploma = Specialization::where('name', 'تكنولوجيا الوسائط المتعددة')->where('degree', 'diploma')->first();

        $supAmjad = $this->upsertUser($this->staffEmail('t3-a.sayma@mashroui.local'), [
            'name' => 'م. أمجد صايمة', 'password' => 'password', 'role' => RoleEnum::Supervisor,
            'gender' => 'male', 'specialization_id' => $mobile->id,
            'term_id' => $this->termId, 'whatsapp' => '970562298101', 'employee_number' => 'EMP-3001',
        ], 'EMP-3001');
        $supLina = $this->upsertUser($this->staffEmail('t3-l.fares@mashroui.local'), [
            'name' => 'د. لينا فارس', 'password' => 'password', 'role' => RoleEnum::Supervisor,
            'gender' => 'female', 'specialization_id' => $db->id,
            'term_id' => $this->termId, 'whatsapp' => '970592298102', 'employee_number' => 'EMP-3002',
        ], 'EMP-3002');

        // فريق 1: منصة تبادل المهارات الطلابية — قيد التنفيذ (بدأ توًا)
        $leader1 = $this->leader('فادي حماد', 't3-fadi.leader@mashroui.local', $db->id);
        $mem1 = $this->student('نغم صوان', 't3-nagham.member@mashroui.local', $db->id);

        [$team1, $project1] = $this->makeTeam(
            'فريق تبادل المهارات الطلابية', $supLina, $leader1, [$mem1], $db,
            'منصة تبادل المهارات بين الطلاب',
            'منصة تتيح للطلاب تبادل المهارات (تصميم، برمجة، لغات...) بنظام نقاط بدل الدفع النقدي.',
            ProjectStatusEnum::InProgress,
        );
        $this->approvedProposal($project1, $leader1, $supLina,
            'مقترح-تبادل-مهارات.pdf',
            'الطلاب يحتاجون مهارات بعضهم البعض (تصميم مقابل برمجة مثلاً) بدون وسيلة تنظيم أو دفع مناسبة لميزانيتهم.',
            'منصة نقاط: كل طالب يكسب نقاط بتقديم مهارته ويصرفها للحصول على مهارة غيره.',
            'نظام نقاط، تقييم متبادل، دردشة مباشرة لتنسيق الجلسات.',
        );
        $this->tasks($team1, $leader1, [
            ['تحليل المتطلبات وتصميم قاعدة البيانات', TaskStatusEnum::Done],
            ['تصميم واجهات المستخدم الأولية', TaskStatusEnum::InProgress],
        ]);

        // فريق 2: تطبيق ترشيد استهلاك الكهرباء بالسكن الجامعي — مقترح قيد المراجعة
        $leader2 = $this->leader('حلا مرعي', 't3-hala.leader@mashroui.local', $mobile->id);
        $mem2 = $this->student('طارق ياغي', 't3-tareq.member@mashroui.local', $mobile->id);

        [$team2, $project2] = $this->makeTeam(
            'فريق ترشيد استهلاك الكهرباء', $supAmjad, $leader2, [$mem2], $mobile,
            'تطبيق ترشيد استهلاك الكهرباء بالسكن الجامعي',
            'تطبيق موبايل يعرض لطلاب السكن الجامعي استهلاكهم الكهربائي اليومي مع نصائح ترشيد مخصصة.',
            ProjectStatusEnum::Proposed,
        );
        $this->pendingProposal($project2, $leader2,
            'مقترح-ترشيد-كهرباء.pdf',
            'طلاب السكن الجامعي بلا أي مؤشر على استهلاكهم الكهربائي، ما يؤدي لهدر ملحوظ.',
            'تطبيق يربط بعدادات ذكية ويعرض الاستهلاك اليومي مع نصائح ترشيد وتنبيهات تجاوز.',
            'مؤشر استهلاك يومي، نصائح ذكية، تنبيه عند تجاوز الحد المعتاد.',
        );

        // فريق 3: منصة توثيق مشاريع التخرج السابقة — مقترح قيد المراجعة
        $leader3 = $this->leader('عدي بشارات', 't3-oday.leader@mashroui.local', $web->id);
        $mem3a = $this->student('ميار سلامة', 't3-mayar.member@mashroui.local', $web->id);
        $mem3b = $this->student('كنان أبو زنط', 't3-kenan.member@mashroui.local', $web->id);

        [$team3, $project3] = $this->makeTeam(
            'فريق توثيق مشاريع التخرج', $supLina, $leader3, [$mem3a, $mem3b], $web,
            'منصة توثيق وأرشفة مشاريع التخرج السابقة',
            'منصة بحث تتيح للطلاب الجدد استعراض مشاريع تخرج السنوات السابقة كمرجع وإلهام لمقترحاتهم.',
            ProjectStatusEnum::Proposed,
        );
        $this->pendingProposal($project3, $leader3,
            'مقترح-توثيق-مشاريع-سابقة.pdf',
            'مشاريع التخرج السابقة تختفي بعد التخرج بلا أرشيف يستفيد منه الطلاب الجدد.',
            'منصة بحث وتصفح لمشاريع السنوات السابقة مصنّفة حسب القسم والتخصص وسنة التخرج.',
            'بحث متقدّم، تصنيف حسب السنة والتخصص، عرض فيديو العرض النهائي.',
        );

        // فريق 4 (ميديا دبلوم): بودكاست توجيه الطلاب الجدد — قيد التنفيذ (بدأ توًا)
        $leader4 = $this->leader('وعد شحادة', 't3-waad.leader@mashroui.local', $mediaDiploma->id);
        $mem4 = $this->student('إياد قنديل', 't3-eyad.member@mashroui.local', $mediaDiploma->id);

        [$team4, $project4] = $this->makeTeam(
            'فريق بودكاست توجيه الطلاب الجدد', $supAmjad, $leader4, [$mem4], $mediaDiploma,
            'بودكاست توجيهي للطلاب المستجدين',
            'سلسلة بودكاست قصيرة تشرح للطلاب المستجدين آلية التسجيل والحياة الجامعية بأسلوب غير رسمي.',
            ProjectStatusEnum::InProgress,
        );
        $this->approvedProposal($project4, $leader4, $supAmjad,
            'مقترح-بودكاست-توجيهي.pdf',
            'الطلاب المستجدون يجدون صعوبة بفهم إجراءات التسجيل والحياة الجامعية من المصادر الرسمية الجافة.',
            'بودكاست بأسلوب حواري خفيف يشرح كل خطوة بلسان طلاب مروا بالتجربة نفسها.',
            'حلقات قصيرة مصنّفة بالموضوع، ضيوف من طلاب متفوقين، أسئلة شائعة مجاوَبة صوتياً.',
        );
        $this->tasks($team4, $leader4, [
            ['تحديد مواضيع الحلقات الأولى', TaskStatusEnum::Done],
            ['تسجيل الحلقة التجريبية', TaskStatusEnum::InProgress],
        ]);
    }
}
