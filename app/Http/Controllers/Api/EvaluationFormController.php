<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\EvaluationForm;
use App\Models\EvaluationSubmission;
use App\Models\Team;
use App\Services\EvaluationFormService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * نماذج التقييم (فورم قابل للتعديل) وتعبئتها:
 * الإدارة العامة ولجنة الإشراف: تعديل النماذج، وتعبئة نموذج المناقشين، ورؤية كل التعبئات.
 * المشرف: يعبّئ نموذج المشرف لمجموعاته فقط، ولا يرى تقييمات المناقشين.
 */
class EvaluationFormController extends Controller
{
    private const STAFF = [RoleEnum::SuperAdmin, RoleEnum::Committee];

    // GET /evaluation-forms
    public function index(Request $request)
    {
        abort_unless(in_array($request->user()->role, [...self::STAFF, RoleEnum::Supervisor], true), 403);

        return response()->json(EvaluationForm::with('updatedBy:id,name', 'department:id,name')->orderBy('id')->get());
    }

    // POST /evaluation-forms — نموذج جديد (مشرف أو مناقشين) لدرجة معيّنة أو لكل الدرجات، ولقسم معيّن أو لكل الأقسام
    public function store(Request $request, EvaluationFormService $service)
    {
        abort_unless(in_array($request->user()->role, self::STAFF, true), 403);

        $data = $request->validate([
            'kind' => ['required', Rule::in(['supervisor', 'examiner'])],
            'degree' => ['nullable', Rule::in(['diploma', 'bachelor'])],
            'department_id' => ['nullable', 'exists:departments,id'],
            ...$this->rules(),
        ]);

        $form = EvaluationForm::create([...$data, 'key' => 'custom_'.Str::lower(Str::random(12)), 'updated_by' => $request->user()->id]);
        $service->recomputeAll();

        return response()->json($form->fresh(['updatedBy:id,name', 'department:id,name']), 201);
    }

    // DELETE /evaluation-forms/{form} — فقط إن لم يُعبّأ بعد، حتى لا تضيع تقييمات محفوظة
    public function destroy(Request $request, EvaluationForm $form, EvaluationFormService $service)
    {
        abort_unless(in_array($request->user()->role, self::STAFF, true), 403);
        abort_if($form->submissions()->exists(), 422, 'لا يمكن حذف نموذج له تقييمات محفوظة.');

        $form->delete();
        $service->recomputeAll();

        return response()->noContent();
    }

    // PUT /evaluation-forms/{form} — تعديل الحقول وطريقة التقييم والوزن
    public function update(Request $request, EvaluationForm $form, EvaluationFormService $service)
    {
        abort_unless(in_array($request->user()->role, self::STAFF, true), 403);

        $data = $request->validate($this->rules());

        // نماذج المشرف + نماذج المناقشين = 100 لكل درجة: الأوزان تُعرض للمستخدم، ولا يُفرض التطابق حتى يعدّلها كلها
        $form->update([...$data, 'updated_by' => $request->user()->id]);
        $service->recomputeForForm($form);

        return response()->json($form->fresh(['updatedBy:id,name', 'department:id,name']));
    }

    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'method' => ['required', Rule::in(['points', 'scale'])],
            'weight' => ['required', 'integer', 'min:0', 'max:100'],
            'allow_nomination' => ['boolean'],
            'sections' => ['required', 'array', 'min:1', 'max:10'],
            'sections.*.id' => ['required', 'string', 'max:40', 'distinct'],
            'sections.*.title' => ['required', 'string', 'max:300'],
            'sections.*.scope' => ['required', Rule::in(['project', 'student'])],
            'sections.*.items' => ['required', 'array', 'min:1', 'max:30'],
            'sections.*.items.*.id' => ['required', 'string', 'max:40'],
            'sections.*.items.*.text' => ['required', 'string', 'max:1000'],
            'sections.*.items.*.max' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    // GET /teams/{team}/evaluation — ورقة تقييم المجموعة: النماذج المطبّقة + التعبئات + الدرجات المحسوبة
    public function sheet(Request $request, Team $team, EvaluationFormService $service)
    {
        $user = $request->user();
        $isStaff = in_array($user->role, self::STAFF, true);
        $isSuperAdmin = $user->role === RoleEnum::SuperAdmin;
        $own = $user->id === $team->supervisor_id;
        // عضوية لجنة مناقشة هذه المجموعة (بالاسم ضمن لجنة المناقشة المؤكّدة)
        $inCommittee = $service->examinedTeamIds($user)->contains($team->id);
        // المشرف المناقِش لهذه المجموعة يعبّئ نموذج المناقشين لها (وليس لمجموعاته هو)
        $examines = ! $isStaff && $inCommittee;
        // المشرف: نموذج المشرف لمجموعاته، ونماذج المناقشين لمجموعاته (اطلاع) وللمجموعات التي يناقشها (تعبئة)
        abort_unless($isStaff || $own || $examines, 403);

        $team->load('specialization:id,name,degree,department_id', 'supervisor:id,name,email,whatsapp,employee_number', 'project:id,team_id,name,is_featured', 'members.student:id,name,university_number');
        $forms = $service->formsFor($team)->when(! $isStaff, fn ($f) => $f->filter(fn ($form) => $own || $form->kind === 'examiner')->values());
        $examinerForms = $forms->where('kind', 'examiner')->pluck('id');

        $submissions = EvaluationSubmission::with('evaluator:id,name,role')
            ->where('team_id', $team->id)
            ->whereIn('form_id', $forms->pluck('id'))
            ->when(! $isStaff, fn ($q) => $q->where(fn ($q) => $q->where('evaluator_id', $user->id)->orWhereIn('form_id', $examinerForms)))
            ->get();

        $results = Evaluation::where('team_id', $team->id)->get(['student_id', 'score', 'supervisor_score', 'committee_score']);

        return response()->json([
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'section' => $team->section,
                'degree' => $team->specialization?->degree,
                'specialization' => $team->specialization?->name,
                'supervisor' => $team->supervisor ? ['id' => $team->supervisor->id, 'name' => $team->supervisor->name, 'email' => $team->supervisor->email, 'whatsapp' => $team->supervisor->whatsapp, 'employee_number' => $team->supervisor->employee_number] : null,
                'project' => $team->project?->name,
                'project_id' => $team->project?->id,
                'featured' => (bool) $team->project?->is_featured,
                // تمييز المشروع: الإدارة واللجنة ومشرف المجموعة (PATCH /projects/{id})
                'can_feature' => $isStaff || $own,
                'members' => $team->members->map(fn ($m) => ['id' => $m->student_id, 'name' => $m->student?->name, 'university_number' => $m->student?->university_number])->values(),
            ],
            'forms' => $forms->values(),
            'submissions' => $submissions,
            // المناقش يرى علامة لجنة المناقشة فقط
            'results' => $isStaff || $own ? $results : $results->map(fn ($r) => ['student_id' => $r->student_id, 'committee_score' => $r->committee_score]),
            // النماذج التي يعبّئها المستخدم؛ الباقي للعرض فقط
            // الإدارة العامة: نموذج المناقشين فقط إن كانت ضمن لجنة مناقشة المجموعة (وإلا يُخفى)؛ نموذج المشرف يبقى متاحًا للتصحيح
            // لجنة الإشراف: نموذجها هي (المناقشين) فقط — لا تعبّئ نموذج المشرف
            // المشرف: نموذج المشرف لمجموعته + نموذج المناقشين للمجموعات التي يناقشها
            'editable' => $isStaff
                ? ($isSuperAdmin
                    ? $forms->filter(fn ($f) => $f->kind !== 'examiner' || $inCommittee)->pluck('id')->values()
                    : $forms->where('kind', 'examiner')->pluck('id')->values())
                : $forms->filter(fn ($f) => ($f->kind === 'supervisor' && $own) || ($f->kind === 'examiner' && $examines))->pluck('id')->values(),
        ]);
    }

    // GET /evaluations/examining — مجموعات يناقشها المشرف مع علامة لجنة المناقشة لكل طالب
    public function examining(Request $request, EvaluationFormService $service)
    {
        $teams = Team::with('specialization:id,name,degree', 'supervisor:id,name', 'project:id,team_id,name', 'members.student:id,name,university_number')
            ->whereIn('id', $service->examinedTeamIds($request->user()))
            ->get();
        $scores = Evaluation::whereIn('team_id', $teams->pluck('id'))->get(['team_id', 'student_id', 'committee_score'])->groupBy('team_id');

        return response()->json($teams->map(fn (Team $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'section' => $t->section,
            'degree' => $t->specialization?->degree,
            'supervisor' => $t->supervisor ? ['id' => $t->supervisor->id, 'name' => $t->supervisor->name] : null,
            'project' => $t->project ? ['id' => $t->project->id, 'name' => $t->project->name] : null,
            'members' => $t->members->map(fn ($m) => ['id' => $m->student_id, 'name' => $m->student?->name])->values(),
            'results' => $scores->get($t->id, collect())->map(fn ($r) => ['student_id' => $r->student_id, 'committee_score' => $r->committee_score])->values(),
        ])->values());
    }

    // PUT /teams/{team}/evaluation/{form} — تعبئة نموذج (المقيّم = المستخدم الحالي)
    public function submit(Request $request, Team $team, EvaluationForm $form, EvaluationFormService $service)
    {
        $user = $request->user();
        $isStaff = in_array($user->role, self::STAFF, true);
        $isSuperAdmin = $user->role === RoleEnum::SuperAdmin;
        $inCommittee = $service->examinedTeamIds($user)->contains($team->id);
        // نموذج المشرف: مشرف المجموعة أو الإدارة العامة (للتصحيح) — لجنة الإشراف لا تعبّئ نموذج المشرف
        // نموذج المناقشين: لجنة الإشراف دائمًا؛ الإدارة العامة فقط إن كانت ضمن لجنة مناقشة المجموعة؛ أو المشرف المناقِش لها
        $canSubmit = $form->kind === 'supervisor'
            ? ($isSuperAdmin || $user->id === $team->supervisor_id)
            : (($isStaff && ! $isSuperAdmin) || $inCommittee);
        abort_unless($canSubmit, 403);
        abort_unless($service->formsFor($team->loadMissing('specialization'))->contains('id', $form->id), 422, 'هذا النموذج لا يخص درجة هذه المجموعة.');

        $data = $request->validate([
            'answers' => ['required', 'array'],
            'answers.project' => ['nullable', 'array'],
            'answers.students' => ['nullable', 'array'],
            'general_notes' => ['nullable', 'string', 'max:3000'],
            'nominate' => ['nullable', 'boolean'],
        ]);

        $submission = $service->submit($form, $team, $user, $data);

        return response()->json($submission->load('evaluator:id,name,role'));
    }
}
