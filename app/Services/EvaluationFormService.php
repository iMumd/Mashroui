<?php

namespace App\Services;

use App\Enums\DiscussionStatusEnum;
use App\Models\Discussion;
use App\Models\Evaluation;
use App\Models\EvaluationForm;
use App\Models\EvaluationSubmission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * نظام نماذج التقييم: النماذج المطبّقة على مجموعة، تعبئة نموذج، وحساب الدرجة النهائية لكل طالب:
 * جزء المشرف (مجموع نموذج المشرف ÷ قصواه × وزنه) + جزء اللجنة (متوسط نماذج المناقشين ÷ قصواها × وزنها).
 */
class EvaluationFormService
{
    /**
     * نماذج المشرف ثم نماذج المناقشين المطبّقة على المجموعة:
     * نموذج بلا درجة = لكل الدرجات، ونموذج بلا قسم = افتراضي لكل الأقسام.
     * وإن وُجد نموذج خاص بقسم المجموعة (لنفس النوع) تقدّم على الافتراضي.
     */
    public function formsFor(Team $team): Collection
    {
        $degree = $team->specialization?->degree?->value ?? $team->specialization?->degree;
        $departmentId = $team->specialization?->department_id;

        $forms = EvaluationForm::where(fn ($q) => $q->whereNull('degree')->orWhere('degree', $degree))
            ->where(fn ($q) => $q->whereNull('department_id')->orWhere('department_id', $departmentId))
            ->orderByRaw("kind = 'examiner'")
            ->orderBy('id')
            ->get();

        // تخصيص القسم يتقدّم على الافتراضي: ضمن كل نوع، إن وُجد نموذج خاص بقسم المجموعة نتجاهل الافتراضي
        if ($departmentId) {
            $forms = $forms->groupBy('kind')->flatMap(function (Collection $group) {
                $specific = $group->whereNotNull('department_id');

                return $specific->isNotEmpty() ? $specific : $group;
            });
        }

        return $forms->values();
    }

    /** مجموعات يناقشها المستخدم في مناقشات منشورة — لجنة المناقشة نص أسماء: "أ، ب | ملاحظة" */
    public function examinedTeamIds(User $user): Collection
    {
        return Discussion::with('project:id,team_id')
            ->where('status', DiscussionStatusEnum::Confirmed)
            ->where('supervisor_id', '!=', $user->id)
            ->get()
            ->filter(fn ($d) => in_array($user->name, array_map('trim', preg_split('/[،,]/u', explode(' | ', (string) $d->committee)[0])), true))
            ->pluck('project.team_id')->filter()->unique()->values();
    }

    /**
     * يتحقق من الإجابات ويحفظها: كل درجة بين 0 والقصوى لبندها، والطلاب أعضاء المجموعة، والبنود من النموذج نفسه.
     */
    public function submit(EvaluationForm $form, Team $team, User $evaluator, array $data): EvaluationSubmission
    {
        $studentIds = $team->members()->pluck('student_id')->map(fn ($id) => (int) $id)->all();
        $answers = ['project' => [], 'students' => []];

        foreach ($form->sections as $section) {
            foreach ($section['items'] as $item) {
                if ($section['scope'] === 'project') {
                    $a = $data['answers']['project'][$item['id']] ?? null;
                    if ($a !== null && ($a['score'] ?? null) !== null) {
                        $answers['project'][$item['id']] = ['score' => $this->score($a['score'], $item), 'note' => $a['note'] ?? null];
                    }

                    continue;
                }
                foreach ($studentIds as $sid) {
                    $score = $data['answers']['students'][$sid][$item['id']] ?? null;
                    if ($score !== null) {
                        $answers['students'][$sid][$item['id']] = $this->score($score, $item);
                    }
                }
            }
        }

        $submission = EvaluationSubmission::updateOrCreate(
            ['form_id' => $form->id, 'team_id' => $team->id, 'evaluator_id' => $evaluator->id],
            [
                'answers' => $answers,
                'general_notes' => $data['general_notes'] ?? null,
                'nominate' => $form->allow_nomination ? ($data['nominate'] ?? null) : null,
            ]
        );

        $this->recompute($team);

        return $submission;
    }

    private function score(mixed $value, array $item): float
    {
        if (! is_numeric($value) || $value < 0 || $value > $item['max']) {
            throw ValidationException::withMessages(['answers' => "درجة البند «{$item['text']}» يجب أن تكون بين 0 و{$item['max']}."]);
        }

        return round((float) $value, 2);
    }

    /** مجموع طالب في تعبئة واحدة = بنود المشروع (مشتركة) + بنوده هو */
    public function studentTotal(EvaluationForm $form, array $answers, int $studentId): float
    {
        $total = 0;
        foreach ($form->sections as $section) {
            foreach ($section['items'] as $item) {
                $total += $section['scope'] === 'project'
                    ? ($answers['project'][$item['id']]['score'] ?? 0)
                    : ($answers['students'][$studentId][$item['id']] ?? $answers['students'][(string) $studentId][$item['id']] ?? 0);
            }
        }

        return $total;
    }

    /** يعيد حساب الدرجة النهائية لكل طلاب المجموعة ويحفظها في evaluations */
    public function recompute(Team $team): void
    {
        $team->loadMissing('specialization');
        $forms = $this->formsFor($team)->groupBy('kind');
        $submissions = EvaluationSubmission::where('team_id', $team->id)->whereIn('form_id', $forms->flatten()->pluck('id'))->get()->groupBy('form_id');

        $part = function (?EvaluationForm $form, int $sid) use ($submissions) {
            $subs = $form ? $submissions->get($form->id, collect()) : collect();
            $max = $form?->maxPerStudent();
            if (! $max || $subs->isEmpty()) {
                return null;
            }

            return round($subs->avg(fn ($s) => $this->studentTotal($form, $s->answers, $sid)) / $max * $form->weight, 2);
        };

        // جزء كل نوع = مجموع حصص نماذجه المعبّأة (null إن لم يُعبّأ أيّ منها)
        $sum = function (string $kind, int $sid) use ($forms, $part) {
            $parts = $forms->get($kind, collect())->map(fn ($f) => $part($f, $sid))->reject(fn ($v) => $v === null);

            return $parts->isEmpty() ? null : round($parts->sum(), 2);
        };

        foreach ($team->members()->pluck('student_id') as $sid) {
            $sup = $sum('supervisor', (int) $sid);
            $com = $sum('examiner', (int) $sid);
            if ($sup === null && $com === null) {
                continue;
            }

            Evaluation::updateOrCreate(
                ['team_id' => $team->id, 'student_id' => $sid],
                [
                    'supervisor_id' => $team->supervisor_id,
                    'supervisor_score' => $sup,
                    'committee_score' => $com,
                    'score' => (int) round(min(100, ($sup ?? 0) + ($com ?? 0))),
                ]
            );
        }
    }

    /** بعد إضافة نموذج أو حذفه: كل مجموعة لها تعبئات */
    public function recomputeAll(): void
    {
        Team::whereIn('id', EvaluationSubmission::distinct()->pluck('team_id'))->get()->each(fn (Team $t) => $this->recompute($t));
    }

    /** بعد تعديل نموذج (أوزان/بنود) تُحدَّث الدرجات النهائية لكل مجموعة لها تعبئات عليه */
    public function recomputeForForm(EvaluationForm $form): void
    {
        Team::whereIn('id', $form->submissions()->distinct()->pluck('team_id'))->get()->each(fn (Team $t) => $this->recompute($t));
    }
}
