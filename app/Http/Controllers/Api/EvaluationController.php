<?php

namespace App\Http\Controllers\Api;

use App\Enums\DegreeEnum;
use App\Enums\GenderEnum;
use App\Enums\RegionEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EvaluationController extends Controller
{
    /**
     * GET /evaluations — نتائج تقييمات مشاريع التخرج، قابلة للفلترة حسب الدرجة (دبلوم/بكالوريوس)
     * والجنس (طلاب/طالبات) ومكان التواجد (غزة/جنوب) والمشاريع المميزة. لجنة/مشرف عام: الكل،
     * المشرف: فرقه فقط (عشان يدخل درجات مجموعاته). مقيّدة بالسنة الدراسية الحالية عبر علاقة الفريق.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [RoleEnum::Committee, RoleEnum::SuperAdmin, RoleEnum::Supervisor], true), 403);

        $filters = $request->validate([
            'degree' => ['sometimes', Rule::enum(DegreeEnum::class)],
            'gender' => ['sometimes', Rule::enum(GenderEnum::class)],
            'region' => ['sometimes', Rule::enum(RegionEnum::class)],
            'featured' => ['sometimes', 'boolean'],
            'supervisor_id' => ['sometimes', 'integer', 'exists:users,id'],
        ]);

        $query = Evaluation::query()
            ->whereHas('team') // يطبّق نطاق السنة الدراسية على الفريق
            ->with([
                'student:id,name,gender,region,university_number',
                'supervisor:id,name',
                'team:id,name,section,supervisor_id,specialization_id',
                'team.specialization:id,name,degree',
                'team.project:id,team_id,name,is_featured',
            ]);

        if ($user->role === RoleEnum::Supervisor) {
            $query->where('supervisor_id', $user->id);
        } elseif (isset($filters['supervisor_id'])) {
            $query->where('supervisor_id', $filters['supervisor_id']);
        }

        if (isset($filters['degree'])) {
            $query->whereHas('team.specialization', fn ($q) => $q->where('degree', $filters['degree']));
        }
        if (isset($filters['gender'])) {
            $query->whereHas('student', fn ($q) => $q->where('gender', $filters['gender']));
        }
        if (isset($filters['region'])) {
            $query->whereHas('student', fn ($q) => $q->where('region', $filters['region']));
        }
        if ($request->boolean('featured')) {
            $query->whereHas('team.project', fn ($q) => $q->where('is_featured', true));
        }

        $results = $query->orderByDesc('score')->get()->map(fn (Evaluation $e) => [
            'id' => $e->id,
            'score' => $e->score,
            'supervisor_score' => $e->supervisor_score,
            'committee_score' => $e->committee_score,
            'notes' => $e->notes,
            'student' => [
                'id' => $e->student?->id,
                'name' => $e->student?->name,
                'gender' => $e->student?->gender,
                'region' => $e->student?->region,
                'university_number' => $e->student?->university_number,
            ],
            'supervisor' => $e->supervisor ? ['id' => $e->supervisor->id, 'name' => $e->supervisor->name] : null,
            'team' => $e->team ? [
                'id' => $e->team->id,
                'name' => $e->team->name,
                'section' => $e->team->section,
            ] : null,
            'project' => $e->team?->project ? [
                'id' => $e->team->project->id,
                'name' => $e->team->project->name,
                'is_featured' => $e->team->project->is_featured,
            ] : null,
            'degree' => $e->team?->specialization?->degree,
            'specialization' => $e->team?->specialization?->name,
        ]);

        return response()->json($results->values());
    }

    public function upsert(Request $request, Team $team, User $student)
    {
        Gate::authorize('manage', [Evaluation::class, $team]);

        if (! $team->members()->where('student_id', $student->id)->exists()) {
            abort(422, 'هذا الطالب مش عضو بهذا الفريق.');
        }

        $data = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $evaluation = Evaluation::updateOrCreate(
            ['team_id' => $team->id, 'student_id' => $student->id],
            [
                'supervisor_id' => $request->user()->id,
                'score' => $data['score'],
                'notes' => $data['notes'] ?? null,
            ]
        );

        return response()->json($evaluation);
    }
}
