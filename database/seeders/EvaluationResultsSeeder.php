<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\Discussion;
use App\Models\EvaluationSubmission;
use App\Models\Team;
use App\Models\User;
use App\Services\EvaluationFormService;
use Illuminate\Database\Seeder;

/**
 * نتائج تقييم تجريبية لمجموعات الفصل الحالي (المشاريع غير المقترحة فقط):
 * تعبئة نموذج المشرف (من مشرف المجموعة) ونموذج المناقشين (من مناقشَي جدول المناقشات، وإلا أعضاء اللجنة)
 * ثم حساب الدرجة النهائية لكل طالب. لتنوّع الحالات: كل سابع مجموعة بلا تقييم، وكل خامسة بتقييم المشرف فقط.
 */
class EvaluationResultsSeeder extends Seeder
{
    public function run(EvaluationFormService $service): void
    {
        $committee = User::where('role', RoleEnum::Committee)->orderBy('id')->get(['id', 'name']);
        $users = User::whereIn('role', [RoleEnum::Supervisor, RoleEnum::Committee])->get(['id', 'name'])->keyBy('name');

        $teams = Team::with('specialization', 'members', 'project')
            ->whereNotNull('supervisor_id')
            ->orderBy('section')
            ->orderBy('id')
            ->get()
            ->filter(fn (Team $t) => $t->members->isNotEmpty() && $t->project && $t->project->status->value !== 'proposed')
            ->values();

        foreach ($teams as $i => $team) {
            if ($i % 7 === 6) {
                continue;
            }
            mt_srand($team->id * 7919);
            $level = mt_rand(55, 98) / 100; // مستوى المجموعة العام
            $forms = $service->formsFor($team);

            $this->fill($forms->firstWhere('kind', 'supervisor'), $team, [$team->supervisor_id], $level);
            if ($i % 5 !== 4) {
                $this->fill($forms->firstWhere('kind', 'examiner'), $team, $this->examiners($team, $users, $committee), $level);
            }
            $service->recompute($team);
        }
    }

    /** مناقشا المجموعة من جدول المناقشات ("أ، ب | ملاحظة")، وإلا أول عضوين في لجنة الإشراف */
    private function examiners(Team $team, $users, $committee): array
    {
        $names = (string) Discussion::where('project_id', $team->project?->id)->value('committee');
        $ids = collect(preg_split('/[،,]/u', explode(' | ', $names)[0]))->map(fn ($n) => $users->get(trim($n))?->id)->filter()->unique()->values();

        return ($ids->isNotEmpty() ? $ids : $committee->pluck('id'))->take(2)->all();
    }

    private function fill($form, Team $team, array $evaluatorIds, float $level): void
    {
        if (! $form) {
            return;
        }
        $score = fn (array $item) => max(0, min($item['max'], round($item['max'] * ($level + mt_rand(-12, 10) / 100) * 2) / 2));

        foreach ($evaluatorIds as $evaluatorId) {
            $answers = ['project' => [], 'students' => []];
            foreach ($form->sections as $section) {
                foreach ($section['items'] as $item) {
                    if ($section['scope'] === 'project') {
                        $answers['project'][$item['id']] = ['score' => $score($item), 'note' => null];
                    } else {
                        foreach ($team->members as $m) {
                            $answers['students'][$m->student_id][$item['id']] = $score($item);
                        }
                    }
                }
            }

            EvaluationSubmission::updateOrCreate(
                ['form_id' => $form->id, 'team_id' => $team->id, 'evaluator_id' => $evaluatorId],
                ['answers' => $answers, 'general_notes' => null, 'nominate' => null]
            );
        }
    }
}
