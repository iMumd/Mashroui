<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatusEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\EvaluationSubmission;
use App\Models\Project;
use App\Models\Scopes\TermScope;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    // GET /projects/archive — سجلّ المشاريع المكتملة ضمن السنة الدراسية الحالية (لجنة: الكل، مشرف: مشاريعه فقط)
    public function archive(Request $request)
    {
        Gate::authorize('viewAny', Project::class);

        $query = Project::with(['team:id,name', 'department:id,name', 'specialization:id,name', 'proposal', 'finalReports'])
            ->where('status', ProjectStatusEnum::Completed)
            ->orderByDesc('completed_at');

        if ($request->user()->role === RoleEnum::Supervisor) {
            $query->where('supervisor_id', $request->user()->id);
        }

        return response()->json($query->get());
    }

    // GET /projects/{id} — مقيّد بنفس النطاق الزمني الكامل (بما فيه فصول سابقة) بعكس الربط الضمني الافتراضي
    public function show(Request $request, int $project)
    {
        $item = Project::withoutGlobalScope(TermScope::class)
            ->with(['team:id,name', 'department:id,name', 'specialization:id,name', 'proposal', 'finalReports'])
            ->findOrFail($project);

        Gate::authorize('view', $item);

        return response()->json($item);
    }

    // GET /projects/featured — عام، مشاريع الواجهة الرئيسية
    public function featured()
    {
        $projects = Project::withoutGlobalScope(TermScope::class)
            ->with($this->publicRelations())
            ->where('is_featured', true)
            ->orderByDesc('id')
            ->paginate(6);

        $stats = $this->nominationStats($projects->getCollection()->pluck('team_id')->all());
        $projects->getCollection()->transform(fn (Project $project) => $this->publicProject($project, $stats));

        return response()->json($projects);
    }

    // GET /projects/public-archive — عام، أرشيف المشاريع المميّزة المكتملة (التي ميّزتها اللجنة/الإدارة) بفلترة وترقيم صفحات
    public function publicArchive(Request $request)
    {
        $data = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'department_id' => ['sometimes', 'exists:departments,id'],
            'degree' => ['sometimes', Rule::in(['diploma', 'bachelor'])],
        ]);

        $projects = Project::withoutGlobalScope(TermScope::class)
            ->with($this->publicRelations())
            ->where('status', ProjectStatusEnum::Completed)
            ->where('is_featured', true)
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($data['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($data['degree'] ?? null, fn ($q, $degree) => $q->whereHas('specialization', fn ($sq) => $sq->where('degree', $degree)))
            ->orderByDesc('completed_at')
            ->paginate(6)
            ->withQueryString();

        $stats = $this->nominationStats($projects->getCollection()->pluck('team_id')->all());
        $projects->getCollection()->transform(fn (Project $project) => $this->publicProject($project, $stats));

        return response()->json($projects);
    }

    // GET /projects/public-archive/{project} — عام، تفاصيل مشروع مميّز مكتمل واحد (غير المميّزة غير متاحة للعرض العام)
    public function publicArchiveShow(int $project)
    {
        $item = Project::withoutGlobalScope(TermScope::class)
            ->with($this->publicRelations())
            ->where('status', ProjectStatusEnum::Completed)
            ->where('is_featured', true)
            ->findOrFail($project);

        return response()->json($this->publicProject($item, $this->nominationStats([$item->team_id])));
    }

    /** العلاقات اللازمة لعرض عام آمن — بدون بيانات تواصل شخصية (إيميل/رقم جامعي) */
    private function publicRelations(): array
    {
        return ['department:id,name', 'specialization:id,name,degree', 'supervisor:id,name', 'team:id,name', 'team.members.student:id,name', 'academicTerm:id,name', 'finalReports:id,project_id,video_url,created_at'];
    }

    /** إحصاء ترشيحات المناقشين لكل مجموعة: كم مناقشًا رشّح المشروع (nominate) من إجمالي من قيّموه بنموذج المناقشين */
    private function nominationStats(array $teamIds): array
    {
        $teamIds = array_values(array_filter($teamIds));
        if (! $teamIds) {
            return [];
        }

        return EvaluationSubmission::whereIn('team_id', $teamIds)
            ->whereHas('form', fn ($q) => $q->where('kind', 'examiner'))
            ->selectRaw('team_id, COUNT(*) as discussants, COALESCE(SUM(nominate), 0) as nominations')
            ->groupBy('team_id')
            ->get()
            ->keyBy('team_id')
            ->map(fn ($r) => ['discussants' => (int) $r->discussants, 'nominations' => (int) $r->nominations])
            ->all();
    }

    private function publicProject(Project $project, array $stats = []): array
    {
        $star = $stats[$project->team_id] ?? ['discussants' => 0, 'nominations' => 0];

        return [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'department' => $project->department ? ['id' => $project->department->id, 'name' => $project->department->name] : null,
            'specialization' => $project->specialization ? [
                'id' => $project->specialization->id,
                'name' => $project->specialization->name,
                'degree' => $project->specialization->degree?->value,
            ] : null,
            'team_name' => $project->team?->name,
            'supervisor_name' => $project->supervisor?->name,
            'members' => $project->team?->members->pluck('student.name')->filter()->values() ?? [],
            'term' => $project->academicTerm?->name,
            'completed_at' => $project->completed_at?->toDateString(),
            'video_url' => $project->finalReports->whereNotNull('video_url')->sortByDesc('created_at')->first()?->video_url,
            'featured' => (bool) $project->is_featured,
            'stars' => ['nominations' => $star['nominations'], 'discussants' => $star['discussants']],
        ];
    }

    public function update(Request $request, Project $project)
    {
        // نوع المشروع للمشرف فقط؛ التمييز في الرئيسية للجنة والإدارة؛ باقي الحقول للمشرف واللجنة والإدارة
        if ($request->has('types')) {
            Gate::authorize('updateTypes', $project);
        }
        if ($request->has('is_featured')) {
            Gate::authorize('feature', $project);
        }
        if ($request->hasAny(['name', 'description'])) {
            Gate::authorize('update', $project);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'required', 'string'],
            'is_featured' => ['sometimes', 'boolean'],
            'types' => ['sometimes', 'array', 'max:3'],
            'types.*' => ['string', 'distinct', 'max:50'],
        ]);

        $project->update($data);

        return response()->json($project);
    }

    public function complete(Request $request, Project $project, ProjectService $service)
    {
        Gate::authorize('update', $project);

        return response()->json($service->complete($project));
    }
}
