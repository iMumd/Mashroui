<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Team;
use App\Models\User;
use App\Support\CurrentTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AnnouncementController extends Controller
{
    // GET /teams/{team}/announcements — إعلانات فريق
    public function indexForTeam(Team $team)
    {
        Gate::authorize('viewTeam', [Announcement::class, $team]);

        $announcements = Announcement::where('kind', 'team')
            ->where('team_id', $team->id)
            ->with('author:id,name,role')
            ->latest()
            ->get();

        return response()->json($announcements);
    }

    // POST /teams/{team}/announcements — نشر إعلان لفريق
    public function storeForTeam(Request $request, Team $team, CurrentTerm $currentTerm)
    {
        Gate::authorize('createForTeam', [Announcement::class, $team]);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $announcement = Announcement::create([
            'author_id' => $request->user()->id,
            'kind' => 'team',
            'team_id' => $team->id,
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'term_id' => $this->termId($currentTerm),
        ]);

        return response()->json($announcement->load('author:id,name,role'), 201);
    }

    // POST /announcements/supervisor — المشرف العام يقيّم تقدّم مجموعات مشرف بالنجوم وملاحظات
    public function storeForSupervisor(Request $request, CurrentTerm $currentTerm)
    {
        Gate::authorize('createForSupervisor', Announcement::class);

        $data = $request->validate([
            'supervisor_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:2000'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $supervisor = User::findOrFail($data['supervisor_id']);
        if ($supervisor->role !== RoleEnum::Supervisor) {
            throw ValidationException::withMessages(['supervisor_id' => 'المستلم يجب أن يكون مشرفًا.']);
        }

        $announcement = Announcement::create([
            'author_id' => $request->user()->id,
            'kind' => 'supervisor',
            'target_user_id' => $supervisor->id,
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'rating' => $data['rating'],
            'term_id' => $this->termId($currentTerm),
        ]);

        return response()->json($announcement->load('author:id,name,role', 'targetUser:id,name'), 201);
    }

    // GET /announcements — صندوق وارد: الإعلانات التي تخص المستخدم الحالي
    public function inbox(Request $request)
    {
        $user = $request->user();

        $query = Announcement::with('author:id,name,role', 'team:id,name', 'targetUser:id,name')->latest();

        if (in_array($user->role, [RoleEnum::Committee, RoleEnum::SuperAdmin], true)) {
            // اللجنة/المشرف العام يرون كل الإعلانات ضمن السنة الحالية
        } elseif ($user->role === RoleEnum::Supervisor) {
            $supervisedTeamIds = Team::where('supervisor_id', $user->id)->pluck('id');
            $query->where(function ($q) use ($user, $supervisedTeamIds) {
                $q->where(fn ($s) => $s->where('kind', 'team')->whereIn('team_id', $supervisedTeamIds))
                    ->orWhere(fn ($s) => $s->where('kind', 'supervisor')->where('target_user_id', $user->id));
            });
        } else {
            // طالب: إعلانات فرقه فقط
            $myTeamIds = Team::whereHas('members', fn ($m) => $m->where('student_id', $user->id))
                ->orWhere('leader_id', $user->id)
                ->pluck('id');
            $query->where('kind', 'team')->whereIn('team_id', $myTeamIds);
        }

        return response()->json($query->get());
    }

    private function termId(CurrentTerm $currentTerm): int
    {
        $termId = $currentTerm->get();

        if (! $termId) {
            throw ValidationException::withMessages(['term_id' => 'لا يوجد فصل دراسي محدد.']);
        }

        return $termId;
    }
}
