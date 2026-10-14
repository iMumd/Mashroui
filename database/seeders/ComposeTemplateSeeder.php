<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\ComposeTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

/** قوالب دائمة جاهزة للإعلانات والملفات والتكليفات (مكتبة الإدارة العامة ولجنة الإشراف) — آمن للتكرار */
class ComposeTemplateSeeder extends Seeder
{
    /** author: admin | committee */
    protected array $templates = [
        ['kind' => 'announcement', 'author' => 'admin', 'title' => 'موعد مناقشات مشاريع التخرج', 'body' => "تعلن الإدارة العامة أن مناقشات مشاريع التخرج تبدأ يوم {اليوم} الموافق {التاريخ} في {القاعة}.\nيرجى حضور جميع أعضاء المجموعة قبل الموعد بـ 15 دقيقة، وإحضار نسخة مطبوعة من التقرير النهائي."],
        ['kind' => 'announcement', 'author' => 'admin', 'title' => 'آخر موعد لتسليم المقترحات', 'body' => "نذكّر جميع المجموعات بأن آخر موعد لرفع مقترح المشروع على منصة مسار هو {التاريخ} الساعة 11:59 مساءً.\nالمقترحات المتأخرة لن تُعتمد إلا بموافقة لجنة الإشراف."],
        ['kind' => 'announcement', 'author' => 'committee', 'title' => 'اجتماع المتابعة الأسبوعي', 'body' => "يُعقد اجتماع المتابعة الأسبوعي مع المشرفين يوم {اليوم} الساعة {الساعة} لمراجعة تقدّم المجموعات والتكليفات المتأخرة."],
        ['kind' => 'announcement', 'author' => 'committee', 'title' => 'تحديث بيانات المجموعات', 'body' => "يرجى من جميع المجموعات مراجعة بيانات الأعضاء والمشروع على المنصة، والتواصل مع المشرف لتصحيح أي خطأ قبل {التاريخ}."],

        ['kind' => 'file', 'author' => 'admin', 'title' => 'نموذج التقرير النهائي المعتمد', 'body' => 'مرفق نموذج التقرير النهائي المعتمد من الكلية. يُرجى الالتزام بالتنسيق (الخط Cairo/Times، الهوامش، ترقيم الفصول) عند التسليم.'],
        ['kind' => 'file', 'author' => 'admin', 'title' => 'دليل كتابة مقترح المشروع', 'body' => 'مرفق دليل كتابة مقترح المشروع: المشكلة، الأهداف، الفئة المستهدفة، المنهجية، والخطة الزمنية.'],
        ['kind' => 'file', 'author' => 'committee', 'title' => 'معايير تقييم المناقشة', 'body' => 'مرفق نموذج معايير تقييم المناقشة وأوزانها، للاطلاع والاستعداد قبل موعد المناقشة.'],

        ['kind' => 'task', 'author' => 'admin', 'title' => 'تسليم الفصل الأول (المقدمة والدراسة السابقة)', 'body' => "المطلوب: رفع الفصل الأول بصيغة PDF.\n- المقدمة ومشكلة البحث\n- الدراسات والأنظمة السابقة\n- مراجعة المشرف قبل التسليم"],
        ['kind' => 'task', 'author' => 'admin', 'title' => 'تسليم التحليل والتصميم', 'body' => "المطلوب: رفع مخططات التحليل والتصميم.\n- مخطط حالات الاستخدام\n- مخطط قاعدة البيانات (ERD)\n- واجهات المستخدم المبدئية"],
        ['kind' => 'task', 'author' => 'committee', 'title' => 'عرض النسخة التجريبية للمشرف', 'body' => 'المطلوب: عرض نسخة تجريبية عاملة من المشروع على المشرف، وإرفاق رابط الفيديو أو المستودع.'],
        ['kind' => 'task', 'author' => 'committee', 'title' => 'رفع التقرير النهائي والعرض التقديمي', 'body' => "المطلوب: رفع التقرير النهائي (PDF) والعرض التقديمي (PPTX) قبل موعد المناقشة بأسبوع على الأقل."],
    ];

    public function run(): void
    {
        $authors = [
            'admin' => User::where('role', RoleEnum::SuperAdmin)->value('id'),
            'committee' => User::where('role', RoleEnum::Committee)->value('id') ?? User::where('role', RoleEnum::SuperAdmin)->value('id'),
        ];

        if (! $authors['admin']) {
            return;
        }

        foreach ($this->templates as $t) {
            ComposeTemplate::firstOrCreate(
                ['kind' => $t['kind'], 'title' => $t['title']],
                ['body' => $t['body'], 'created_by' => $authors[$t['author']]],
            );
        }
    }
}
