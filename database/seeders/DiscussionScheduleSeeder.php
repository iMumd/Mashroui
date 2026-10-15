<?php

namespace Database\Seeders;

use App\Enums\DiscussionStatusEnum;
use App\Enums\RoleEnum;
use App\Models\AcademicTerm;
use App\Models\Discussion;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * جدول مناقشات منشور (مؤكَّد) لمشاريع الفصل الحالي — يظهر في "جدول مناقشات المشرف" لكل مشرف.
 * نفس قيود صفحة المواعيد: الدبلوم في يوم (15 دقيقة) والبكالوريوس في يوم آخر (30 دقيقة)، الترتيب بالشعبة،
 * قاعات وساعات مكان تواجد كل مجموعة، مناقشان من غير المشرف، ولا أحد في مناقشتين متداخلتين ولو بقاعتين مختلفتين.
 * يحدّث موعد المشروع إن وُجد (updateOrCreate) فيمكن تشغيله أكثر من مرة.
 */
class DiscussionScheduleSeeder extends Seeder
{
    private const DAYS = ['diploma' => '2027-01-24', 'bachelor' => '2027-01-25'];

    private const DURATIONS = ['diploma' => 15, 'bachelor' => 30];

    private const ROOMS = ['gaza' => ['E504', 'E506', 'E304'], 'middle' => ['E504', 'E506', 'E304'], 'south' => ['S101', 'S102']];

    // ساعات كل مكان تواجد [من، إلى] بالدقائق — نفس الافتراضي في الواجهة
    private const WINDOWS = ['gaza' => [540, 840], 'middle' => [540, 840], 'south' => [600, 780]];

    public function run(): void
    {
        $termId = AcademicTerm::where('is_current', true)->value('id');
        $supervisors = User::where('role', RoleEnum::Supervisor)->orderBy('id')->get(['id', 'name']);
        if (! $termId || $supervisors->count() < 3) {
            return;
        }

        $projects = Project::withoutGlobalScopes()
            ->where('term_id', $termId)
            ->whereNotNull('team_id')
            ->with(['team' => fn ($q) => $q->withoutGlobalScopes()->with('specialization', 'members.student')])
            ->get()
            ->filter(fn ($p) => $p->team)
            ->sortBy(fn ($p) => [$p->team->specialization?->degree?->value ?? 'diploma', (int) $p->team->section]);

        $busy = [];   // "date|person أو room" => [[من، إلى]]
        $load = $supervisors->mapWithKeys(fn ($s) => [$s->id => 0])->all();
        // بالمرجع: الحجوزات تُضاف داخل الحلقة (fn تلتقط القيمة وقت الإنشاء فقط)
        $free = function ($key, $a, $b) use (&$busy) {
            return collect($busy[$key] ?? [])->every(fn ($i) => $b <= $i[0] || $i[1] <= $a);
        };

        foreach ($projects as $project) {
            $team = $project->team;
            $degree = $team->specialization?->degree?->value === 'bachelor' ? 'bachelor' : 'diploma';
            $region = $this->regionOf($team);
            $date = self::DAYS[$degree];
            $dur = self::DURATIONS[$degree];
            [$from, $to] = self::WINDOWS[$region];
            $supId = $project->supervisor_id ?? $team->supervisor_id;

            for ($a = $from; $a + $dur <= $to; $a += $dur) {
                $b = $a + $dur;
                if (! $free("$date|$supId", $a, $b)) {
                    continue;
                }
                $room = collect(self::ROOMS[$region])->first(fn ($r) => $free("$date|room:$r", $a, $b));
                $examiners = $supervisors
                    ->filter(fn ($s) => $s->id !== $supId && $free("$date|{$s->id}", $a, $b))
                    ->sortBy(fn ($s) => $load[$s->id])
                    ->take(2)->values();
                if (! $room || $examiners->count() < 2) {
                    continue;
                }

                foreach ([$supId, ...$examiners->pluck('id')] as $pid) {
                    $busy["$date|$pid"][] = [$a, $b];
                }
                $busy["$date|room:$room"][] = [$a, $b];
                $examiners->each(function ($s) use (&$load) {
                    $load[$s->id]++;
                });

                Discussion::withoutGlobalScopes()->updateOrCreate(
                    ['project_id' => $project->id],
                    [
                        'supervisor_id' => $supId,
                        'place' => $room,
                        'discussion_date' => $date,
                        'discussion_time' => sprintf('%02d:%02d', intdiv($a, 60), $a % 60),
                        'committee' => $examiners->pluck('name')->implode('، '),
                        'status' => DiscussionStatusEnum::Confirmed,
                        'term_id' => $termId,
                    ],
                );
                break;
            }
        }
    }

    /** المكان الغالب لأعضاء الفريق (غزة عند غياب البيانات) — نفس groupRegion في الواجهة */
    private function regionOf($team): string
    {
        $counts = $team->members->map(fn ($m) => $m->student?->region?->value)->filter()->countBy();

        return collect(['gaza', 'middle', 'south'])->reduce(fn ($best, $r) => ($counts[$r] ?? 0) > ($counts[$best] ?? 0) ? $r : $best, 'gaza');
    }
}
