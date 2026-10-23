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

        $committeePool = User::where('role', 'committee')->get()->pluck('name')->all();

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

            // pick 2-3 committee members
            $picked = collect($committeePool)->shuffle()->take(3)->values()->all();
            if (count($picked) < 2) {
                $picked = ['د. سامي أبو ناصر', 'د. إيمان الحسن', 'م. رامي قاسم'];
            }
            $committeeStr = implode('، ', $picked);

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
