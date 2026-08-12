<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatusEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Scopes\TermScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    // GET /projects/archive — سجلّ المشاريع المكتملة عبر كل الفصول الدراسية (لجنة: الكل، مشرف: مشاريعه فقط)
    public function archive(Request $request)
    {
        Gate::authorize('viewAny', Project::class);

        $query = Project::withoutGlobalScope(TermScope::class)
            ->with(['team:id,name', 'department:id,name', 'specialization:id,name', 'proposal', 'finalReports'])
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

        $projects->getCollection()->transform(fn (Project $project) => $this->publicProject($project));

        return response()->json($projects);
    }

    // GET /projects/public-archive — عام، أرشيف المشاريع المكتملة بفلترة وترقيم صفحات حقيقيين
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
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($data['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($data['degree'] ?? null, fn ($q, $degree) => $q->whereHas('specialization', fn ($sq) => $sq->where('degree', $degree)))
            ->orderByDesc('completed_at')
            ->paginate(9)
            ->withQueryString();

        $projects->getCollection()->transform(fn (Project $project) => $this->publicProject($project));

        return response()->json($projects);
    }

    // GET /projects/public-archive/{project} — عام، تفاصيل مشروع مكتمل واحد
    public function publicArchiveShow(int $project)
    {
        $item = Project::withoutGlobalScope(TermScope::class)
            ->with($this->publicRelations())
            ->where('status', ProjectStatusEnum::Completed)
            ->findOrFail($project);

        return response()->json($this->publicProject($item));
    }

    /** العلاقات اللازمة لعرض عام آمن — بدون بيانات تواصل شخصية (إيميل/رقم جامعي) */
    private function publicRelations(): array
    {
        return ['department:id,name', 'specialization:id,name,degree', 'supervisor:id,name', 'team:id,name', 'team.members.student:id,name', 'academicTerm:id,name'];
    }

    private function publicProject(Project $project): array
    {
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
        ];
    }

    public function update(Request $request, Project $project)
    {
        Gate::authorize('update', $project);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'required', 'string'],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        $project->update($data);

        return response()->json($project);
    }

    public function complete(Request $request, Project $project)
    {
        Gate::authorize('update', $project);

        if ($project->status !== ProjectStatusEnum::InProgress) {
            throw ValidationException::withMessages(['status' => 'المشروع لازم يكون قيد التنفيذ حتى يُنهى.']);
        }

        $project->update([
            'status' => ProjectStatusEnum::Completed,
            'completed_at' => now(),
        ]);

        return response()->json($project);
    }
}
