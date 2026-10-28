<?php

namespace App\Support;

use App\Models\Discussion;
use App\Models\User;

/**
 * يمنع تعارضات جدول المناقشات: حجز نفس القاعة بنفس الوقت، أو وجود نفس المناقش على
 * مجموعتين بنفس التاريخ والساعة (حتى لو بقاعتين مختلفتين)، أو إدراج مشرف المشروع ضمن
 * لجنة مناقشة مشروعه. لجنة المناقشة نص حر، فنفكّه إلى أسماء مُنمّطة للمقارنة.
 */
class DiscussionScheduleGuard
{
    /** يفكّ نص اللجنة إلى [اسم مُنمّط => الاسم كما كُتب] */
    public static function examiners(?string $committee): array
    {
        $parts = preg_split('/[,،;؛\/\n\r]+/u', (string) $committee) ?: [];
        $names = [];

        foreach ($parts as $part) {
            $display = trim((string) preg_replace('/\s+/u', ' ', $part));
            if ($display !== '') {
                $names[self::fold($display)] = $display;
            }
        }

        return $names;
    }

    /** تنميط اسم للمقارنة: حروف صغيرة، وإسقاط الألقاب الشائعة (د. / أ.د / م. ...) */
    private static function fold(string $name): string
    {
        $name = mb_strtolower(trim($name));
        // إسقاط الألقاب فقط عندما تكون واضحة: مختصر بنقطة (د. أ. م. أ.د.) أو كلمة كاملة متبوعة بمسافة،
        // حتى لا نقتطع أول حرف من اسم حقيقي مثل "أحمد" أو "منى".
        $name = preg_replace('/^(?:أ\.\s*د\.?|[أدم]\.|دكتور\s+|المهندس\s+|الأستاذ\s+)\s*/u', '', $name);

        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    /** المدة الافتراضية بالدقائق عندما لا تُخزَّن مدة صريحة */
    private const DEFAULT_DURATION = 30;

    /** يحوّل الوقت (HH:MM أو HH:MM:SS) إلى عدد دقائق منذ منتصف الليل */
    private static function toMinutes(?string $time): int
    {
        $parts = explode(':', substr(trim((string) $time), 0, 5));
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);

        return $h * 60 + $m;
    }

    /** يطبّع المدة بالدقائق إلى قيمة موجبة، ويرجع الافتراضي عند غيابها */
    private static function duration($value): int
    {
        $minutes = (int) $value;

        return $minutes > 0 ? $minutes : self::DEFAULT_DURATION;
    }

    /**
     * أخطاء التعارض لفترة مناقشة مقترحة مقابل فترات أخرى — بفحص تداخل الفترات
     * (البداية + المدة) لا بالتطابق التام للوقت، لنفس القاعة ولنفس المناقش.
     *
     * @param  array{place:string,discussion_date:string,discussion_time:string,duration?:int,committee:?string,supervisor_id:?int}  $slot
     * @param  iterable<array{place:string,discussion_date:string,discussion_time:string,duration?:int,committee:?string}>  $others
     * @return array<int,string>
     */
    public function errors(array $slot, iterable $others): array
    {
        $errors = [];
        $date = $slot['discussion_date'];
        $start = self::toMinutes($slot['discussion_time']);
        $end = $start + self::duration($slot['duration'] ?? null);
        $place = trim((string) ($slot['place'] ?? ''));
        $examiners = self::examiners($slot['committee'] ?? '');

        if (! empty($slot['supervisor_id'])) {
            $supervisorName = User::find($slot['supervisor_id'])?->name;
            if ($supervisorName && array_key_exists(self::fold($supervisorName), $examiners)) {
                $errors[] = 'مشرف المشروع لا يمكن أن يكون ضمن لجنة مناقشة مشروعه.';
            }
        }

        foreach ($others as $other) {
            if (($other['discussion_date'] ?? null) !== $date) {
                continue;
            }

            $otherStart = self::toMinutes($other['discussion_time'] ?? '');
            $otherEnd = $otherStart + self::duration($other['duration'] ?? null);

            // تداخل فترتين: البداية قبل نهاية الأخرى والعكس (التلامس عند الحدّ ليس تداخلًا)
            if ($start >= $otherEnd || $otherStart >= $end) {
                continue;
            }

            if ($place !== '' && trim((string) ($other['place'] ?? '')) === $place) {
                $errors[] = "القاعة \"{$place}\" محجوزة بموعد متداخل بنفس التاريخ.";
            }

            foreach (array_intersect_key($examiners, self::examiners($other['committee'] ?? '')) as $display) {
                $errors[] = "المناقش \"{$display}\" عنده مناقشة متداخلة بنفس التاريخ.";
            }
        }

        return array_values(array_unique($errors));
    }

    /** فترات المناقشات الموجودة بنفس اليوم (ضمن السنة الحالية) للمقارنة، مع استثناء مشروع واحد اختياريًا */
    public function existingSlots(string $date, ?int $exceptProjectId = null): array
    {
        return Discussion::query()
            ->where('discussion_date', $date)
            ->when($exceptProjectId, fn ($q) => $q->where('project_id', '!=', $exceptProjectId))
            ->get(['project_id', 'place', 'discussion_date', 'discussion_time', 'duration', 'committee'])
            ->map(fn (Discussion $d) => [
                'place' => $d->place,
                'discussion_date' => $d->discussion_date instanceof \DateTimeInterface ? $d->discussion_date->format('Y-m-d') : (string) $d->discussion_date,
                'discussion_time' => $d->discussion_time,
                'duration' => $d->duration,
                'committee' => $d->committee,
            ])
            ->all();
    }
}
