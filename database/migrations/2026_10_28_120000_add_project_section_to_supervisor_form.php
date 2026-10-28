<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// يضيف لنموذج تقييم مشروع التخرج من قبل المشرف المباشر قسمًا مشتركًا "تقييم المشروع ككل"
// (scope=project، 5 بنود) قبل قسم "تقييم الطالب" (scope=student) — مطابقًا لبنية نماذج لجنة المناقشة.
// القصوى لكل بند 5. الدرجة النهائية نسبية (studentTotal / maxPerStudent * weight) فلا يتأثّر الوزن.
// نُبقي بنود الطالب العشرة بنصّها الأصلي ومعرّفها s-sup حتى تبقى التعبئات السابقة مرتبطة.
return new class extends Migration
{
    private array $studentItems = [
        'الالتزام بحضور اللقاءات',
        'التزام الطالب بالمواعيد المحددة له في تنفيذ مهامه وتسليم مراحل المشروع في وقتها المحدد',
        'الاستجابة لتعليمات وتوجيهات المشرف وتطبيقها بشكل واضح',
        'التواصل المنتظم مع المشرف وكتابة تقارير مرحلية واضحة',
        'مشاركة الطالب في الأفكار والتقدم الفعلي في العمل وجودة الإنجاز على مدار الفصل وتحسن قدراته تدريجيًا خلال فترة المشروع',
        'مشاركة الطالب بإيجابية وتعاون مع زملائه',
        'قدرة الطالب على حل المشكلات والبحث الذاتي والعمل المستقل دون اعتماد مفرط على المشرف',
        'حسن اختيار واستخدام البرامج والتقنيات المناسبة',
        'إتقان تنفيذ العناصر السمعية والبصرية أو البرمجية',
        'اكتمال المشروع في الموعد المحدد وتحقيقه للأهداف المرجوة',
    ];

    public function up(): void
    {
        $item = fn ($text) => ['id' => substr(md5($text), 0, 10), 'text' => $text, 'max' => 5];

        $sections = [
            [
                'id' => 's-sup-proj',
                'title' => 'أولًا: تقييم المشروع ككل (درجة مشتركة لكل طلبة المجموعة)',
                'scope' => 'project',
                'items' => array_map($item, [
                    'وضوح فكرة المشروع وأهدافه وملاءمتها للمشكلة المطروحة',
                    'جودة التنفيذ التقني والفني للمشروع وتكامل مكوّناته',
                    'اكتمال المشروع في الموعد المحدد ومطابقته للخطة المعتمدة',
                    'جودة التقرير النهائي وتوثيق مراحل العمل',
                    'الابتكار والقيمة العملية والأثر العام للمشروع',
                ]),
            ],
            [
                'id' => 's-sup',
                'title' => 'ثانيًا: تقييم الطالب (خاص بكل طالب شارك في تنفيذ المشروع)',
                'scope' => 'student',
                'items' => array_map($item, $this->studentItems),
            ],
        ];

        DB::table('evaluation_forms')->where('key', 'supervisor')->update([
            'sections' => json_encode($sections, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $item = fn ($text) => ['id' => substr(md5($text), 0, 10), 'text' => $text, 'max' => 5];

        $sections = [[
            'id' => 's-sup',
            'title' => 'محاور التقييم',
            'scope' => 'student',
            'items' => array_map($item, $this->studentItems),
        ]];

        DB::table('evaluation_forms')->where('key', 'supervisor')->update([
            'sections' => json_encode($sections, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }
};
