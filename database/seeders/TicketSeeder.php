<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

/** تذاكر شكاوى وملاحظات واقعية من الطلاب والمشرفين مع ردود الدعم (اللجنة/الإدارة العامة) — لا يعمل إن وُجدت تذاكر */
class TicketSeeder extends Seeder
{
    /**
     * role: دور فاتح التذكرة · days: منذ كم يوم فُتحت · closed: هل أُغلقت
     * thread: الرسائل بالترتيب — 'owner' لفاتح التذكرة و'staff' لفريق الدعم
     */
    protected array $tickets = [
        ['role' => 'student', 'subject' => 'تعذّر رفع ملف المقترح', 'days' => 1, 'closed' => false, 'thread' => [
            ['owner', 'عند رفع ملف المقترح تظهر رسالة خطأ في حجم الملف رغم أن حجمه 8 ميغا.'],
            ['staff', 'الحد الأقصى 10 ميغا، جرّب حفظه بصيغة PDF مضغوطة وأعد المحاولة.'],
        ]],
        ['role' => 'supervisor', 'subject' => 'طلب تغيير موعد المناقشة', 'days' => 2, 'closed' => false, 'thread' => [
            ['owner', 'لديّ تعارض في موعد المناقشة مع مناقشة أخرى في نفس الساعة، أرجو نقل إحدى المجموعتين لموعد لاحق.'],
        ]],
        ['role' => 'student', 'subject' => 'خطأ في اسمي بكشف الشعبة', 'days' => 6, 'closed' => true, 'thread' => [
            ['owner', 'اسمي مكتوب بشكل خاطئ في كشف الشعبة، الرجاء التصحيح.'],
            ['staff', 'تم تصحيح الاسم، شكرًا لتنبيهك.'],
        ]],
        ['role' => 'student', 'subject' => 'المهام لا تظهر في لوحة الكانبان', 'days' => 3, 'closed' => false, 'thread' => [
            ['owner', 'المشرف أضاف مهام جديدة لفريقنا لكنها لا تظهر عندي في صفحة المهام.'],
            ['staff', 'هل جرّبت تسجيل الخروج والدخول مرة أخرى؟ أرسل لنا اسم المهمة إن استمرت المشكلة.'],
            ['owner', 'جرّبت ولم تظهر، المهمة بعنوان "تصميم قاعدة البيانات".'],
        ]],
        ['role' => 'supervisor', 'subject' => 'إضافة طالب إلى مجموعة', 'days' => 4, 'closed' => true, 'thread' => [
            ['owner', 'انضم طالب جديد لإحدى مجموعاتي بعد التسجيل المتأخر، أرجو إضافته للمجموعة.'],
            ['staff', 'تمت إضافة الطالب للمجموعة ويمكنه الآن رؤية مهام الفريق.'],
            ['owner', 'شكرًا، تم التأكد.'],
        ]],
        ['role' => 'student', 'subject' => 'ملاحظة على نموذج التقييم', 'days' => 8, 'closed' => true, 'thread' => [
            ['owner', 'نقترح توضيح أوزان بنود التقييم للطلاب قبل موعد المناقشة.'],
            ['staff', 'ملاحظة في مكانها، سيُنشر توزيع الأوزان ضمن الإعلانات هذا الأسبوع.'],
        ]],
        ['role' => 'supervisor', 'subject' => 'عدم وصول إشعارات التقارير النهائية', 'days' => 0, 'closed' => false, 'thread' => [
            ['owner', 'لا تصلني إشعارات عند رفع الطلاب للتقرير النهائي، أكتشف ذلك فقط عند فتح الصفحة.'],
        ]],
        ['role' => 'student', 'subject' => 'استفسار عن تغيير المشرف', 'days' => 5, 'closed' => false, 'thread' => [
            ['owner', 'هل يمكن تغيير مشرف المجموعة بعد اعتماد المقترح؟'],
            ['staff', 'يتم ذلك فقط بطلب رسمي من رئيس القسم مع موافقة المشرف الحالي.'],
        ]],
    ];

    public function run(): void
    {
        if (Ticket::exists()) {
            return;
        }

        $owners = [
            'student' => User::where('role', RoleEnum::Student)->inRandomOrder()->take(count($this->tickets))->get()->values(),
            'supervisor' => User::where('role', RoleEnum::Supervisor)->inRandomOrder()->take(count($this->tickets))->get()->values(),
        ];
        $staff = User::whereIn('role', [RoleEnum::Committee, RoleEnum::SuperAdmin])->get();

        if ($staff->isEmpty()) {
            return;
        }

        $used = ['student' => 0, 'supervisor' => 0];

        foreach ($this->tickets as $data) {
            $pool = $owners[$data['role']];
            if ($pool->isEmpty()) {
                continue;
            }
            $owner = $pool[$used[$data['role']]++ % $pool->count()];
            $responder = $staff->random();

            $at = now()->subDays($data['days'])->setTime(9, 0);
            $ticket = Ticket::create([
                'user_id' => $owner->id,
                'subject' => $data['subject'],
                'status' => $data['closed'] ? TicketStatusEnum::Closed : TicketStatusEnum::Open,
            ]);

            foreach ($data['thread'] as $i => [$who, $body]) {
                $msgAt = $at->copy()->addHours($i * 5);
                $reply = $ticket->replies()->create([
                    'user_id' => $who === 'owner' ? $owner->id : $responder->id,
                    'body' => $body,
                ]);
                $reply->forceFill(['created_at' => $msgAt, 'updated_at' => $msgAt])->save();
            }

            $ticket->forceFill([
                'created_at' => $at,
                'updated_at' => $msgAt,
                'last_reply_at' => $msgAt,
                'closed_at' => $data['closed'] ? $msgAt : null,
                'closed_by' => $data['closed'] ? $responder->id : null,
            ])->save();
        }
    }
}
