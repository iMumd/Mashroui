<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\Team;
use App\Models\User;
use App\Rules\AllowedDocumentTypes;
use App\Services\AnnouncementService;
use App\Support\CurrentTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

    // GET /announcements — صندوق وارد: الإعلانات والملفات التي تخص المستخدم الحالي (قناة المنصة)
    // الإرسال الواحد لعدة مجموعات للمشرف نفسه يظهر مرة واحدة (فريد بالـbatch)
    public function inbox(Request $request)
    {
        $items = $this->inboxQuery($request->user())
            ->with('author:id,name,role', 'team:id,name,section', 'targetUser:id,name', 'attachments')
            ->latest()
            ->get()
            ->unique(fn (Announcement $a) => $a->batch ?? "row-{$a->id}")
            ->values();

        return response()->json($items);
    }

    // POST /announcements/send — الإدارة العامة ترسل إعلانًا أو ملفًا لمجموعات أو لأعضاء لجنة أو لمشرفين
    public function send(Request $request, AnnouncementService $service)
    {
        abort_unless(in_array($request->user()->role, [RoleEnum::SuperAdmin, RoleEnum::Committee], true), 403); // الإدارة العامة ولجنة الإشراف

        $data = $request->validate([
            'type' => ['required', Rule::in(['announcement', 'file'])],
            'audience' => ['required', Rule::in(['groups', 'committee', 'supervisors'])],
            'title' => ['nullable', 'string', 'max:200', 'required_if:type,file'],
            'body' => ['nullable', 'string', 'max:2000', 'required_if:type,announcement'],
            'team_ids' => ['required_if:audience,groups', 'array'],
            'team_ids.*' => ['integer'],
            'user_ids' => ['required_unless:audience,groups', 'array'],
            'user_ids.*' => ['integer'],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(['platform', 'whatsapp', 'email'])],
            'files' => ['required_if:type,file', 'array', 'max:5'],
            'files.*' => ['file', 'mimes:'.AllowedDocumentTypes::MIMES, 'max:10240'],
        ]);

        // الإرسال للجنة الإشراف للإدارة العامة وحدها؛ اللجنة ترسل للمشرفين والمجموعات
        abort_if($request->user()->role === RoleEnum::Committee && $data['audience'] === 'committee', 403, 'لجنة الإشراف ترسل للمشرفين والمجموعات فقط.');

        $batch = $service->send($data, $request->file('files', []), $request->user());

        $group = Announcement::with('author:id,name,role', 'team:id,name,section', 'targetUser:id,name,role', 'attachments')
            ->where('batch', $batch)->get();

        return response()->json($service->present($group), 201);
    }

    // POST /announcements/ratings — المشرف العام يقيّم تقدّم مجموعات كل مشرف بالنجوم وملاحظة (إرسال واحد لعدة مشرفين)
    public function sendRatings(Request $request, AnnouncementService $service)
    {
        abort_unless($request->user()->role === RoleEnum::SuperAdmin, 403);

        $data = $request->validate([
            'ratings' => ['required', 'array', 'min:1'],
            'ratings.*.supervisor_id' => ['required', 'integer'],
            'ratings.*.rating' => ['required', 'integer', 'min:1', 'max:5'],
            'ratings.*.note' => ['nullable', 'string', 'max:1000'],
            'general' => ['nullable', 'string', 'max:1000'],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(['platform', 'whatsapp', 'email'])],
        ]);

        $batch = $service->sendRatings($data, $request->user());

        $group = Announcement::with('author:id,name,role', 'targetUser:id,name,role', 'attachments')->where('batch', $batch)->get();

        return response()->json($service->present($group), 201);
    }

    // GET /announcements/sent — المراسلات المرسَلة مجمّعة (للإدارة العامة ولجنة الإشراف) مع فلاتر الخادم
    public function sent(Request $request, AnnouncementService $service)
    {
        abort_unless(in_array($request->user()->role, [RoleEnum::SuperAdmin, RoleEnum::Committee], true), 403);

        $filters = $request->validate([
            'type' => ['nullable', Rule::in(['announcement', 'file', 'rating'])],
            'audience' => ['nullable', Rule::in(['groups', 'committee', 'supervisors'])],
            'team_id' => ['nullable', 'integer'],
            'supervisor_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json($service->sent($filters));
    }

    // DELETE /announcements/batch/{batch} — سحب إرسال كامل (كل مستلميه ومرفقاته)
    public function destroyBatch(Request $request, string $batch, AnnouncementService $service)
    {
        // الإدارة العامة تحذف أي إرسال، ولجنة الإشراف ما أرسلته فقط
        $user = $request->user();
        abort_unless($user->role === RoleEnum::SuperAdmin || ($user->role === RoleEnum::Committee && Announcement::where('batch', $batch)->where('author_id', $user->id)->exists()), 403);
        abort_unless(Announcement::where('batch', $batch)->exists(), 404);

        $service->deleteBatch($batch);

        return response()->json(null, 204);
    }

    // GET /announcements/attachments/{attachment}/download — لمستلمي الإرسال والإدارة واللجنة
    public function downloadAttachment(Request $request, AnnouncementAttachment $attachment)
    {
        $user = $request->user();
        $allowed = in_array($user->role, [RoleEnum::SuperAdmin, RoleEnum::Committee], true)
            || $this->inboxQuery($user)->where('batch', $attachment->batch)->exists();

        abort_unless($allowed, 403);

        return Storage::download($attachment->path, $attachment->name);
    }

    /** ما يصل المستخدم: الإدارة ترى الكل، وغيرها يرى ما وُجّه له أو لفرقه */
    private function inboxQuery(User $user)
    {
        $query = Announcement::query();

        if ($user->role === RoleEnum::SuperAdmin) {
            return $query;
        }

        if ($user->role === RoleEnum::Committee) {
            return $query->where('kind', 'user')->where('target_user_id', $user->id);
        }

        if ($user->role === RoleEnum::Supervisor) {
            $supervisedTeamIds = Team::where('supervisor_id', $user->id)->pluck('id');

            return $query->where(fn ($q) => $q
                ->where(fn ($s) => $s->where('kind', 'team')->whereIn('team_id', $supervisedTeamIds))
                ->orWhere(fn ($s) => $s->whereIn('kind', ['supervisor', 'user'])->where('target_user_id', $user->id)));
        }

        // طالب: إعلانات فرقه فقط
        $myTeamIds = Team::whereHas('members', fn ($m) => $m->where('student_id', $user->id))
            ->orWhere('leader_id', $user->id)
            ->pluck('id');

        return $query->where('kind', 'team')->whereIn('team_id', $myTeamIds);
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
