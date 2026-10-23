<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        // لا حاجة لعناصر category/priority: جدول التذاكر لا يحتوي عليهما

        $leader = User::where('email', 'leader@smail.ucas.edu.ps')->first();
        $student = User::where('email', 'student@smail.ucas.edu.ps')->first();
        $supervisor = User::where('email', 'supervisor@ucas.edu.ps')->first();
        $hassan = User::where('email', 'h.zein@ucas.edu.ps')->first();
        $sami = User::where('email', 'r.jabr@ucas.edu.ps')->first();
        $committee = User::where('role', RoleEnum::Committee)->get();
        $admin = User::where('role', RoleEnum::SuperAdmin)->first();
        $supportPool = $committee->merge($admin ? collect([$admin]) : collect())->filter();

        $data = [
            [
                'owner' => $leader,
                'subject' => 'مشكلة في رفع ملف المهمة',
                'category' => 'technical',
                'priority' => 'high',
                'messages' => [
                    ['from' => 'owner', 'body' => 'عند محاولة رفع ملف المهمة "بناء وحدات الأصناف والمخزون" يظهر خطأ غير واضح، لا يتم رفع الملف رغم صغر حجمه.', 'days' => 6],
                    ['from' => 'support', 'body' => 'نشكرك على التواصل. جرب رفع الملف بصيغة PDF أو DOCX، وتأكد من عدم وجود رموز خاصة في اسم الملف. سنراجع الخادم في حال استمر الخطأ.', 'days' => 5],
                    ['from' => 'owner', 'body' => 'جربت الصيغ المقترحة ويعمل الآن، شكراً لكم.', 'days' => 4],
                ],
                'status' => 'closed',
                'close_days' => 3,
            ],
            [
                'owner' => $student,
                'subject' => 'عدم ظهور مهمة جديدة في اللوحة',
                'category' => 'technical',
                'priority' => 'medium',
                'messages' => [
                    ['from' => 'owner', 'body' => 'تم إسناد مهمة لي لكن لا تظهر في "مهامي" رغم تحديث الصفحة.', 'days' => 5],
                    ['from' => 'support', 'body' => 'يبدو أن التصفية مفعلة. جرب إلغاء فلتر "الحالة" أو مسح التصفية لرؤية جميع المهام.', 'days' => 4],
                    ['from' => 'owner', 'body' => 'بعد مسح الفلتر ظهرت المهمة، شكراً للتوضيح.', 'days' => 3],
                ],
                'status' => 'closed',
                'close_days' => 2,
            ],
            [
                'owner' => $leader,
                'subject' => 'طلب توضيح حول حالة المقترح',
                'category' => 'academic',
                'priority' => 'medium',
                'messages' => [
                    ['from' => 'owner', 'body' => 'هل يمكن توضيح سبب بقاء مقترح المشروع "قيد الانتظار" منذ أكثر من أسبوع؟ هل يحتاج تعديل؟', 'days' => 7],
                    ['from' => 'support', 'body' => 'المقترح قيد مراجعة المشرف حالياً. عند إبداء رأي المشرف سيظهر تحديث مباشرة في صفحة المقترح.', 'days' => 6],
                ],
                'status' => 'open',
            ],
            [
                'owner' => $student,
                'subject' => 'شكوى بخصوص وقت اجتماع المناقشة',
                'category' => 'academic',
                'priority' => 'high',
                'messages' => [
                    ['from' => 'owner', 'body' => 'تم تحديد وقت اجتماع مع الفريق متعارض مع محاضرة إلزامية، هل يمكن إعادة النظر في الوقت؟', 'days' => 4],
                    ['from' => 'support', 'body' => 'يرجى إرسال إثبات التعارض مع تفاصيل المحاضرة، وسيتم التواصل مع المشرف ولجنة الإشراف للنظر بالطلب.', 'days' => 3],
                ],
                'status' => 'open',
            ],
            [
                'owner' => $supervisor,
                'subject' => 'اقتراح إضافة تصنيف للمهام',
                'category' => 'suggestion',
                'priority' => 'low',
                'messages' => [
                    ['from' => 'owner', 'body' => 'اقتراح بإضافة تصنيفات للمهام (تحليل / برمجة / توثيق) لتسهيل المتابعة.', 'days' => 6],
                    ['from' => 'support', 'body' => 'تم تسجيل الاقتراح وسيتم دراسته في اجتماع لجنة الإشراف القادم.', 'days' => 5],
                    ['from' => 'owner', 'body' => 'ممتن لتبني الاقتراح إن أمكن.', 'days' => 4],
                ],
                'status' => 'closed',
                'close_days' => 3,
            ],
            [
                'owner' => User::where('email', 'omar.shahada@smail.ucas.edu.ps')->first() ?? $leader,
                'subject' => 'عدم استلام إشعار بتعيين مهمة',
                'category' => 'technical',
                'priority' => 'medium',
                'messages' => [
                    ['from' => 'owner', 'body' => 'تم إسنادي لمهمة لكن لم يصلني إشعار في الجرس، بينما زملائي وصلهم إشعارات.', 'days' => 5],
                    ['from' => 'support', 'body' => 'تم فحص النظام، الإشعارات تعمل بشكل طبيعي. جرب تحديث الجلسة أو مسح الكاش للتأكد.', 'days' => 4],
                ],
                'status' => 'open',
            ],
            [
                'owner' => User::where('email', 'mohammed.haj@smail.ucas.edu.ps')->first() ?? $student,
                'subject' => 'طلب تعديل اسم الفريق',
                'category' => 'academic',
                'priority' => 'medium',
                'messages' => [
                    ['from' => 'owner', 'body' => 'نود تعديل اسم فريقنا ليتوافق أكثر مع موضوع المشروع، هل ذلك ممكن؟', 'days' => 8],
                    ['from' => 'support', 'body' => 'تعديل اسم الفريق يحتاج موافقة المشرف وأحيانًا لجنة الإشراف، يرجى تقديم طلب رسمي مع مبرر التعديل.', 'days' => 7],
                    ['from' => 'owner', 'body' => 'سأرفع الطلب مع مبرره للمشرف مباشرة.', 'days' => 6],
                ],
                'status' => 'closed',
                'close_days' => 5,
            ],
            [
                'owner' => User::where('email', 'sara.dajani@smail.ucas.edu.ps')->first() ?? $student,
                'subject' => 'مشكلة في عرض ملف التقرير النهائي',
                'category' => 'technical',
                'priority' => 'high',
                'messages' => [
                    ['from' => 'owner', 'body' => 'عند الضغط على ملف التقرير النهائي يظهر خطأ 404، رغم أنه تم رفعه بنجاح سابقًا.', 'days' => 3],
                    ['from' => 'support', 'body' => 'تم التحقق، سنعيد ربط الملف وإعلامك فورًا.', 'days' => 2],
                    ['from' => 'owner', 'body' => 'الملف أصبح ظاهرًا الآن، شكراً جزيلاً.', 'days' => 1],
                ],
                'status' => 'closed',
                'close_days' => 1,
            ],
            [
                'owner' => $hassan ?? $supervisor,
                'subject' => 'استفسار حول معايير تقييم المشاريع',
                'category' => 'academic',
                'priority' => 'medium',
                'messages' => [
                    ['from' => 'owner', 'body' => 'أرجو التوضيح حول أوزان معايير التقييم في استمارة لجان المناقشة.', 'days' => 7],
                    ['from' => 'support', 'body' => 'أوزان التقييم موجودة في دليل مشروع التخرج، ويمكنك الاطلاع عليها من قسم "المكتبة" أو التواصل مع اللجنة مباشرة.', 'days' => 6],
                ],
                'status' => 'open',
            ],
            [
                'owner' => User::where('email', 'rana.abusabha@smail.ucas.edu.ps')->first() ?? $student,
                'subject' => 'شكوى حول تأخر الرد على الاستفسار',
                'category' => 'complaint',
                'priority' => 'high',
                'messages' => [
                    ['from' => 'owner', 'body' => 'تم إرسال استفسار منذ أيام ولم يتم الرد بعد، الأمر يؤثر على سير عمل الفريق.', 'days' => 4],
                    ['from' => 'support', 'body' => 'نعتذر عن التأخر، سيتم الرد على استفسارك خلال اليوم.', 'days' => 2],
                    ['from' => 'owner', 'body' => 'تم الرد، شكراً على المتابعة.', 'days' => 1],
                ],
                'status' => 'closed',
                'close_days' => 0,
            ],
            [
                'owner' => $sami ?? $supervisor,
                'subject' => 'طلب إضافة عضو للجنة مناقشة',
                'category' => 'academic',
                'priority' => 'medium',
                'messages' => [
                    ['from' => 'owner', 'body' => 'أرجو النظر في إضافة عضو لجنة لمناقشة أحد الفرق لوجود تضارب في المواعيد.', 'days' => 6],
                    ['from' => 'support', 'body' => 'سيتم النظر بالطلب والتواصل معك قبل اعتماد الجدول النهائي.', 'days' => 5],
                ],
                'status' => 'open',
            ],
            [
                'owner' => User::where('email', 'lina.kaheel@smail.ucas.edu.ps')->first() ?? $student,
                'subject' => 'عدم القدرة على الوصول لصفحة التقييم',
                'category' => 'technical',
                'priority' => 'medium',
                'messages' => [
                    ['from' => 'owner', 'body' => 'عند الدخول لصفحة التقييم يظهر خطأ "غير مصرح" رغم أنني من أعضاء الفريق.', 'days' => 3],
                    ['from' => 'support', 'body' => 'تم التحقق من الصلاحيات وسيتم تصحيحها، جرب الدخول بعد قليل.', 'days' => 2],
                    ['from' => 'owner', 'body' => 'تم الحل، أصبح بإمكاني الوصول الآن.', 'days' => 1],
                ],
                'status' => 'closed',
                'close_days' => 0,
            ],
            [
                'owner' => $leader,
                'subject' => 'استفسار حول رفع الفيديو للتقرير النهائي',
                'category' => 'academic',
                'priority' => 'low',
                'messages' => [
                    ['from' => 'owner', 'body' => 'هل يجب رفع رابط فيديو على يوتيوب أم يمكن رفع ملف الفيديو مباشرة؟', 'days' => 9],
                    ['from' => 'support', 'body' => 'يتم رفع رابط الفيديو (YouTube، Google Drive مع صلاحية المشاهدة) في خانة "رابط الفيديو" حسب تعليمات النظام.', 'days' => 8],
                    ['from' => 'owner', 'body' => 'مفهوم، شكراً للتوضيح.', 'days' => 7],
                ],
                'status' => 'closed',
                'close_days' => 6,
            ],
            [
                'owner' => User::where('email', 'ahmad.abuleila@smail.ucas.edu.ps')->first() ?? $student,
                'subject' => 'طلب توضيح حول حالة التقرير المرفوض',
                'category' => 'academic',
                'priority' => 'high',
                'messages' => [
                    ['from' => 'owner', 'body' => 'ظهر تقريرنا بحالة "مرفوض" مع ملاحظة، نريد توضيحاً مفصلاً حول ما يجب تعديله بالضبط.', 'days' => 4],
                    ['from' => 'support', 'body' => 'الملاحظة واضحة، يرجى مراجعة قسم "أخطاء يجب تصحيحها" والتعديل ورفع النسخة المعدلة.', 'days' => 3],
                ],
                'status' => 'open',
            ],
        ];

        foreach ($data as $i => $item) {
            if (! $item['owner']) continue;
            if (Ticket::where('user_id', $item['owner']->id)->where('subject', $item['subject'])->exists()) {
                continue;
            }

            $ticket = Ticket::create([
                'user_id' => $item['owner']->id,
                'subject' => $item['subject'],
                'status' => $item['status'],
                'last_reply_at' => null,
            ]);

            $first = null;
            foreach ($item['messages'] as $m => $msg) {
                $authorId = $msg['from'] === 'owner' ? $item['owner']->id : ($supportPool->random()?->id ?? $admin?->id ?? $item['owner']->id);
                $reply = TicketReply::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $authorId,
                    'body' => $msg['body'],
                ]);
                $at = now()->subDays($msg['days'])->subHours(rand(0, 4));
                $reply->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
                if ($m === 0) $first = $reply;
                $ticket->update(['last_reply_at' => $at]);
            }

            if ($item['status'] === 'closed') {
                $ticket->update([
                    'status' => 'closed',
                    'closed_at' => now()->subDays($item['close_days'] ?? 0),
                    'closed_by' => $supportPool->random()?->id ?? $admin?->id,
                ]);
            }

            $ticket->forceFill([
                'created_at' => $first ? $first->created_at : now()->subDays(7),
                'updated_at' => $ticket->last_reply_at ?? now(),
            ])->save();
        }

        // أرشفة أقدم تذكرة مغلقة كنموذج للأرشيف
        $old = Ticket::where('status', 'closed')->whereNull('archived_at')->orderBy('created_at')->first();
        if ($old) {
            $old->update([
                'archived_at' => now()->subDays(2),
                'archived_by' => $admin?->id,
            ]);
        }
    }
}
