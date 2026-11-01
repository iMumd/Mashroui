<?php

namespace Database\Seeders;

use App\Enums\DiscussionStatusEnum;
use App\Models\AcademicTerm;
use App\Models\Discussion;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class DiscussionScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $term = AcademicTerm::where('is_current', true)->firstOrFail();

        // المشاريع المقترحة لا تدخل جدول المناقشات
        $projects = Project::withoutGlobalScopes()
            ->where('term_id', $term->id)
            ->where('status', '!=', 'proposed')
            ->get();

        $places = [
            'قاعة المناقشات - مبنى أ',
            'قاعة المناقشات - مبنى ب',
            'قاعة الاجتماعات الكبرى - مبنى أ',
            'قاعة التدريب - مبنى ب',
        ];

        $times = ['09:00', '09:30', '10:30', '11:00', '12:00', '13:00'];

        // لجنة المناقشة الواقعية: مناقشان من المشرفين (غير مشرف المجموعة) يعبّئان نموذج المناقشين كـ "مناقِشين"،
        // ورئيس اللجنة من لجنة الإشراف. المناقشون المشرفون non-staff فلا يرون درجات بعضهم (قاعدة الرؤية).
        $supervisors = User::where('role', 'supervisor')->where('term_id', $term->id)->get(['id', 'name', 'specialization_id']);
        $committeePool = User::where('role', 'committee')->where('term_id', $term->id)->get(['id', 'name']);

        $dates = [now()->addDays(10)->toDateString(), now()->addDays(11)->toDateString(), now()->addDays(12)->toDateString(), now()->addDays(14)->toDateString()];

        $idx = 0;
        foreach ($projects as $project) {
            if (Discussion::withoutGlobalScopes()->where('project_id', $project->id)->exists()) {
                continue;
            }

            $supervisor = User::find($project->supervisor_id);
            if (! $supervisor) {
                continue;
            }

            // اختيار حتمي لكل مشروع: مناقشان من نفس التخصص أولًا ثم غيرهم، مع استبعاد مشرف المجموعة
            mt_srand($project->id * 104729);
            $peers = $supervisors->where('id', '!=', $project->supervisor_id);
            $examiners = $peers->where('specialization_id', $project->specialization_id)->shuffle()
                ->concat($peers->where('specialization_id', '!=', $project->specialization_id)->shuffle())
                ->take(2)->pluck('name');
            $head = $committeePool->shuffle()->first();
            $names = $examiners->when($head, fn ($c) => $c->push($head->name))->filter()->values();
            // احتياط إن ندر عدد المشرفين: لجنة من لجنة الإشراف
            if ($names->count() < 2) {
                $names = $committeePool->shuffle()->take(3)->pluck('name');
            }
            $committeeStr = $names->implode('، ');

            Discussion::create([
                'project_id' => $project->id,
                'supervisor_id' => $project->supervisor_id,
                'place' => $places[$idx % count($places)],
                'discussion_date' => $dates[$idx % count($dates)],
                'discussion_time' => $times[$idx % count($times)],
                'committee' => $committeeStr,
                'whatsapp' => $supervisor->whatsapp,
                'status' => DiscussionStatusEnum::Confirmed,
                'term_id' => $term->id,
            ]);

            $idx++;
        }
    }
}
