<?php

namespace Database\Seeders;

use App\Enums\DiscussionStatusEnum;
use App\Models\AcademicTerm;
use App\Models\Discussion;
use Illuminate\Database\Seeder;

/**
 * يُدخل أخطاء وتعارضات تجريبية (7 أنواع) على جدول المناقشات (بعد DiscussionScheduleSeeder) لعرض كشف التعارضات في صفحة المواعيد.
 * الصفوف المعدّلة تصير "بانتظار النشر" (pending) — والباقي يبقى منشورًا لكل مشرف.
 * التشغيل: php artisan db:seed --class=DiscussionConflictsSeeder — وللرجوع لجدول نظيف: --class=DiscussionScheduleSeeder
 * لا يُستدعى من DatabaseSeeder عمدًا (بيانات عرض فقط).
 */
class DiscussionConflictsSeeder extends Seeder
{
    public function run(): void
    {
        $termId = AcademicTerm::where('is_current', true)->value('id');
        $list = Discussion::withoutGlobalScopes()->where('term_id', $termId)
            ->with('supervisor:id,name')
            ->orderBy('discussion_date')->orderBy('discussion_time')->get();
        if ($list->count() < 14) {
            return;
        }
        [$a, $b, $c, $d, $e, $f, $g, $h, $i] = [$list[0], $list[2], $list[4], $list[5], $list[6], $list[7], $list->last(), $list[9], $list[11]];
        // أسماء المناقشين قبل " | " (ما بعدها ملاحظة)
        $examiners = fn (Discussion $x) => array_values(array_filter(array_map('trim', preg_split('/[،,]/u', explode(' | ', (string) $x->committee)[0]))));

        $edits = [
            // 1) مناقش بمناقشتين في نفس الوقت بقاعتين مختلفتين: b بنفس وقت a في قاعة أخرى ومع مناقش a الأول
            [$b, ['discussion_time' => $a->discussion_time, 'place' => 'E506', 'committee' => implode('، ', [$examiners($a)[0], collect($examiners($b))->first(fn ($n) => $n !== $examiners($a)[0])])]],
            // 2) المشرف يناقش مجموعته
            [$c, ['committee' => implode('، ', [$c->supervisor?->name, $examiners($c)[1] ?? ''])]],
            // 3) قاعة محجوزة: e بنفس قاعة ووقت d
            [$e, ['discussion_time' => $d->discussion_time, 'place' => $d->place]],
            // 4) مجموعة بكالوريوس في يوم الدبلوم (يوم غير مطابق)
            [$g, ['discussion_date' => $a->discussion_date]],
            // 5) خارج ساعات المكان (غزة 09:00–14:00)
            [$f, ['discussion_time' => '15:00']],
            // 6) مناقش واحد فقط (يلزم مناقشان)
            [$h, ['committee' => $examiners($h)[0]]],
            // 7) نفس المناقش مرتين
            [$i, ['committee' => implode('، ', [$examiners($i)[0], $examiners($i)[0]])]],
        ];

        foreach ($edits as [$row, $data]) {
            $row->update([...$data, 'status' => DiscussionStatusEnum::Pending]);
        }
    }
}
