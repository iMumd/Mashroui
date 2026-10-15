<?php

namespace App\Http\Controllers\Api;

use App\Enums\DegreeEnum;
use App\Enums\GenderEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProposalResource;
use App\Models\AuditLog;
use App\Models\Proposal;
use App\Services\ProposalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class ProposalController extends Controller
{
    // سبب الرفض الذي يبدأ بهذه البادئة يعني "طلب تعديل" (الحالة في الخادم تبقى rejected ويعيد الفريق التقديم)
    private const CHANGES_PREFIX = 'مطلوب تعديل: ';

    /**
     * GET /proposals — كل مقترحات السنة الحالية مع الفريق والمشرف وعدد مرات الرفض والتقارير النهائية.
     * فلاتر من الخادم: status (pending|approved|rejected|changes)، degree، gender، supervisor_id، search.
     * لجنة/مشرف عام: الكل، المشرف: مقترحات فرقه فقط.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Proposal::class);
        $user = $request->user();
        abort_unless(in_array($user->role, [RoleEnum::Committee, RoleEnum::SuperAdmin, RoleEnum::Supervisor], true), 403);

        $f = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected', 'changes'])],
            'degree' => ['sometimes', Rule::enum(DegreeEnum::class)],
            'gender' => ['sometimes', Rule::enum(GenderEnum::class)],
            'supervisor_id' => ['sometimes', 'integer'],
            'search' => ['sometimes', 'string', 'max:100'],
        ]);

        $changes = self::CHANGES_PREFIX.'%';
        $query = Proposal::query()
            ->whereHas('project.team') // نطاق السنة الدراسية عبر المشروع والفريق
            ->with([
                'project.team.specialization:id,name,degree',
                'project.team.supervisor:id,name',
                'project.team.members.student:id,name,gender,region',
                'project.finalReports.uploadedBy:id,name',
            ]);

        if ($user->role === RoleEnum::Supervisor) {
            $query->whereHas('project.team', fn ($q) => $q->where('supervisor_id', $user->id));
        } elseif (isset($f['supervisor_id'])) {
            $query->whereHas('project.team', fn ($q) => $q->where('supervisor_id', $f['supervisor_id']));
        }

        match ($f['status'] ?? null) {
            'changes' => $query->where('status', 'rejected')->where('rejection_reason', 'like', $changes),
            'rejected' => $query->where('status', 'rejected')->where(fn ($q) => $q->whereNull('rejection_reason')->orWhere('rejection_reason', 'not like', $changes)),
            'pending', 'approved' => $query->where('status', $f['status']),
            default => null,
        };

        if (isset($f['degree'])) {
            $query->whereHas('project.team.specialization', fn ($q) => $q->where('degree', $f['degree']));
        }
        if (isset($f['gender'])) {
            $query->whereHas('project.team.members.student', fn ($q) => $q->where('gender', $f['gender']));
        }
        if (! empty($f['search'])) {
            $term = '%'.$f['search'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)
                ->orWhereHas('project.team', fn ($t) => $t->where('name', 'like', $term)->orWhere('section', 'like', $term))
                ->orWhereHas('project.team.supervisor', fn ($s) => $s->where('name', 'like', $term)));
        }

        $rows = $query->latest()->get()->map(function (Proposal $p) {
            $team = $p->project?->team;
            $reason = (string) $p->rejection_reason;
            $isChanges = $p->status->value === 'rejected' && str_starts_with($reason, self::CHANGES_PREFIX);

            return [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'status' => $isChanges ? 'changes' : $p->status->value,
                'rejection_reason' => $isChanges ? mb_substr($reason, mb_strlen(self::CHANGES_PREFIX)) : $p->rejection_reason,
                'rejection_count' => $p->rejection_count,
                'created_at' => $p->created_at,
                'updated_at' => $p->updated_at,
                'project_id' => $p->project_id,
                'types' => $p->project?->types ?? [],
                'team' => $team ? ['id' => $team->id, 'name' => $team->name, 'section' => $team->section] : null,
                'degree' => $team?->specialization?->degree?->value,
                'specialization' => $team?->specialization?->name,
                'supervisor' => $team?->supervisor ? ['id' => $team->supervisor->id, 'name' => $team->supervisor->name] : null,
                'members' => $team ? $team->members->map(fn ($m) => [
                    'id' => $m->student?->id,
                    'name' => $m->student?->name,
                    'gender' => $m->student?->gender?->value,
                ])->values() : [],
                'final_reports' => $p->project ? $p->project->finalReports->sortByDesc('created_at')->map(fn ($r) => [
                    'id' => $r->id,
                    'video_url' => $r->video_url,
                    'cover_url' => $r->cover_url,
                    'created_at' => $r->created_at,
                    'uploaded_by' => $r->uploadedBy?->name,
                ])->values() : [],
            ];
        });

        return response()->json($rows);
    }

    public function show(Proposal $proposal)
    {
        Gate::authorize('view', $proposal);

        return new ProposalResource($proposal->load('project', 'submittedBy', 'reviewedBy'));
    }

    // سجل المراجعات: كل محاولة تقديم/إعادة تقديم مع سبب الرفض إن وُجد، بترتيب زمني
    public function history(Proposal $proposal)
    {
        Gate::authorize('view', $proposal);

        $entries = AuditLog::where('entity', 'proposal')
            ->where('entity_id', $proposal->id)
            ->whereIn('action', ['submit', 'resubmit', 'reject'])
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (AuditLog $log) => [
                'action' => $log->action,
                'meta' => $log->meta,
                'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                'created_at' => $log->created_at,
            ]);

        return response()->json($entries);
    }

    public function download(Proposal $proposal)
    {
        Gate::authorize('view', $proposal);

        $extension = pathinfo($proposal->pdf_path, PATHINFO_EXTENSION) ?: 'pdf';

        return Storage::download($proposal->pdf_path, "{$proposal->name}.{$extension}");
    }

    // بيرجع رابط مؤقّت (موقّع) صالح لـ5 دقائق — الفرونت-إند بيفتحه بتبويب جديد كتنقّل عادي بالمتصفح
    // بدون أي معالجة JS/blob، عشان يتفادى مشاكل حاجب النوافذ المنبثقة والإضافات مع blob: URLs
    public function downloadLink(Proposal $proposal)
    {
        Gate::authorize('view', $proposal);

        $url = URL::temporarySignedRoute('proposals.download-signed', now()->addMinutes(5), ['proposal' => $proposal->id]);

        return response()->json(['url' => $url]);
    }

    // بيتفتح مباشرة بالمتصفح (توقيع الرابط هو التفويض، بدون توكن) — Content-Disposition: inline عشان يتعاين بالتبويب مباشرة
    public function downloadSigned(Proposal $proposal)
    {
        $extension = pathinfo($proposal->pdf_path, PATHINFO_EXTENSION) ?: 'pdf';

        return Storage::response($proposal->pdf_path, "{$proposal->name}.{$extension}");
    }

    public function store(Request $request, ProposalService $service)
    {
        Gate::authorize('create', Proposal::class);

        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'problems' => ['nullable', 'string'],
            'solutions' => ['nullable', 'string'],
            'features_value' => ['nullable', 'string'],
            'types' => ['required', 'array', 'min:1', 'max:3'],
            'types.*' => ['string', 'distinct', 'max:50'],
            'pdf' => ['required', 'file', 'mimes:pdf,doc,docx,ppt,pptx', 'max:102400'],
        ]);

        $proposal = $service->submit($data, $request->file('pdf'), $request->user());

        return response()->json($proposal, 201);
    }

    public function update(Request $request, Proposal $proposal, ProposalService $service)
    {
        Gate::authorize('update', $proposal);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'problems' => ['nullable', 'string'],
            'solutions' => ['nullable', 'string'],
            'features_value' => ['nullable', 'string'],
            // مطلوب فقط لمشروع قديم بلا نوع؛ غير ذلك يُتجاهل (التعديل للمشرف)
            'types' => [Rule::requiredIf(empty($proposal->project?->types)), 'array', 'min:1', 'max:3'],
            'types.*' => ['string', 'distinct', 'max:50'],
            'pdf' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx', 'max:102400'],
        ]);

        $proposal = $service->resubmit($proposal, $data, $request->file('pdf'));

        return response()->json($proposal);
    }

    public function approve(Proposal $proposal, ProposalService $service, Request $request)
    {
        Gate::authorize('review', $proposal);

        return response()->json($service->approve($proposal, $request->user()));
    }

    public function reject(Request $request, Proposal $proposal, ProposalService $service)
    {
        Gate::authorize('review', $proposal);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        return response()->json($service->reject($proposal, $request->user(), $data['rejection_reason']));
    }
}
