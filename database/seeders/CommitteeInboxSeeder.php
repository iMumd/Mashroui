<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\AcademicTerm;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\Notification;
use App\Models\Task;
use App\Models\TaskNote;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * صندوق لجنة الإشراف ببيانات حقيقية بدل البيانات التجريبية في الواجهة:
 * مهام من الإدارة العامة لأعضاء اللجنة ("مهامي") مع ملاحظات، وإعلانات وملفات (مرفقات PDF مخزّنة فعلًا) في "الإعلانات والملفات".
 * لا يعمل إن وُجدت مراسلات أو مهام للجنة مسبقًا.
 */
class CommitteeInboxSeeder extends Seeder
{
    /** status · due: بعد كم يوم (سالب = مضى) · notes: [من, نص] حيث 'me' عضو اللجنة و'admin' الإدارة العامة */
    protected array $tasks = [
        ['title' => 'مراجعة مقترحات تخصص البرمجيات', 'description' => 'مراجعة المقترحات المعلّقة لتخصص برمجيات وقواعد بيانات والبت فيها قبل نهاية الأسبوع.', 'status' => 'pending', 'due' => 4, 'notes' => []],
        ['title' => 'إعداد جدول مناقشات الدبلوم', 'description' => 'توزيع مجموعات الدبلوم على القاعات وتحديد المناقشين لكل مجموعة.', 'status' => 'in_progress', 'due' => 7, 'notes' => [
            ['me', 'تم توزيع 9 مجموعات من أصل 15، المتبقي مجموعات الفرع الجنوبي.'],
            ['admin', 'يرجى مراعاة عدم تعارض المناقشين بين القاعات.'],
        ]],
        ['title' => 'متابعة المجموعات المتأخرة في الإنجاز', 'description' => 'التواصل مع مشرفي المجموعات التي تقل نسبة إنجازها عن 35% ورفع تقرير مختصر.', 'status' => 'in_progress', 'due' => 2, 'notes' => [
            ['me', 'تم التواصل مع مشرفَين حتى الآن.'],
        ]],
        ['title' => 'تدقيق نماذج تقييم البكالوريوس', 'description' => 'مراجعة بنود نموذج لجنة المناقشة للبكالوريوس واقتراح التعديلات.', 'status' => 'review', 'due' => -1, 'notes' => [
            ['me', 'أرسلت التعديلات المقترحة على بندي العرض والتوثيق.'],
        ]],
        ['title' => 'اعتماد التقارير النهائية الجاهزة', 'description' => 'اعتماد التقارير التي وافق عليها المشرفون لأرشفة مشاريعها.', 'status' => 'done', 'due' => -3, 'notes' => []],
    ];

    /** type · days: منذ كم يوم أُرسل · files: أسماء المرفقات */
    protected array $announcements = [
        ['type' => 'announcement', 'title' => 'اجتماع لجنة الإشراف الأسبوعي', 'body' => 'يُعقد اجتماع اللجنة يوم الأحد الساعة 11:00 في قاعة E504 لمناقشة جدول المناقشات ونتائج التقييم.', 'days' => 0, 'files' => []],
        ['type' => 'file', 'title' => 'نموذج تقييم مشاريع التخرج المحدَّث', 'body' => 'مرفق النموذج المعتمد لهذا الفصل — يُرجى استخدامه في جميع المناقشات.', 'days' => 1, 'files' => ['نموذج-التقييم-2026.pdf']],
        ['type' => 'announcement', 'title' => 'موعد تسليم التقارير النهائية', 'body' => 'آخر موعد لتسليم التقارير النهائية للطلاب هو نهاية الأسبوع القادم، وتبدأ مراجعة اللجنة بعد موافقة المشرفين.', 'days' => 3, 'files' => []],
        ['type' => 'file', 'title' => 'دليل أرشفة المشاريع', 'body' => 'خطوات اعتماد التقرير النهائي وأرشفة المشروع وشروط التسليم الصحيح.', 'days' => 5, 'files' => ['دليل-الأرشفة.pdf', 'شروط-التسليم.pdf']],
    ];

    public function run(): void
    {
        $admin = User::where('role', RoleEnum::SuperAdmin)->first();
        $members = User::where('role', RoleEnum::Committee)->get();
        $termId = AcademicTerm::where('is_current', true)->value('id');

        if (! $admin || $members->isEmpty() || ! $termId) {
            return;
        }
        if (Announcement::where('kind', 'user')->whereIn('target_user_id', $members->pluck('id'))->exists()
            || Task::whereNull('team_id')->whereHas('assignees', fn ($q) => $q->where('role', RoleEnum::Committee))->exists()) {
            return;
        }

        // المهام: مهمة مستقلة لكل عضو (كـ POST /staff/tasks)
        foreach ($members as $member) {
            foreach ($this->tasks as $i => $t) {
                $at = now()->subDays(8 - $i)->setTime(9, 0);
                $task = Task::create([
                    'team_id' => null,
                    'term_id' => $termId,
                    'title' => $t['title'],
                    'description' => $t['description'],
                    'due_date' => now()->addDays($t['due'])->toDateString(),
                    'status' => TaskStatusEnum::from($t['status']),
                    'created_by' => $admin->id,
                ]);
                $task->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
                $task->assignees()->sync([$member->id]);

                foreach ($t['notes'] as $n => [$who, $note]) {
                    $noteAt = $at->copy()->addDays($n + 1)->addHours(2);
                    TaskNote::create(['task_id' => $task->id, 'user_id' => $who === 'me' ? $member->id : $admin->id, 'note' => $note])
                        ->forceFill(['created_at' => $noteAt, 'updated_at' => $noteAt])->save();
                }
            }
        }

        // الإعلانات والملفات: إرسال واحد (batch) لكل أعضاء اللجنة (كـ POST /announcements/send) مع مرفقات مخزّنة
        foreach ($this->announcements as $a) {
            $batch = (string) Str::uuid();
            $at = now()->subDays($a['days'])->setTime(10, 0);

            foreach ($members as $member) {
                Announcement::create([
                    'batch' => $batch,
                    'author_id' => $admin->id,
                    'kind' => 'user',
                    'type' => $a['type'],
                    'target_user_id' => $member->id,
                    'title' => $a['title'],
                    'body' => $a['body'],
                    'channels' => ['platform'],
                    'term_id' => $termId,
                ])->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
            }

            foreach ($a['files'] as $name) {
                $path = "announcements/{$batch}/".Str::random(20).'.pdf';
                Storage::put($path, $this->pdf());
                AnnouncementAttachment::create(['batch' => $batch, 'name' => $name, 'path' => $path, 'size' => Storage::size($path)]);
            }

            $label = $a['type'] === 'file' ? 'ملف جديد من الإدارة' : 'إعلان جديد من الإدارة';
            foreach ($members as $member) {
                Notification::create([
                    'user_id' => $member->id,
                    'type' => $a['type'] === 'file' ? 'admin_file' : 'admin_announcement',
                    'message' => "{$label}: \"{$a['title']}\".",
                ]);
            }
        }
    }

    /** ملف PDF صالح من صفحة واحدة (الخطوط الأساسية في PDF لاتينية، فالمحتوى إنجليزي) */
    private function pdf(): string
    {
        $text = 'UCAS - Masar Graduation Projects | Committee document | '.now()->toDateString();
        $stream = "BT /F1 16 Tf 60 760 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$obj}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref
0 '.(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }
}
