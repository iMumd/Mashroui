<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\AcademicTerm;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use App\Support\CurrentTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * مراسلات الإدارة العامة (إعلان أو ملف) لمجموعات أو لأعضاء لجنة الإشراف أو للمشرفين.
 * الإرسال الواحد = batch: صف لكل مستلم (مجموعة أو شخص) + مرفقات مشتركة + إشعار على المنصة لكل شخص مرة واحدة.
 */
class AnnouncementService
{
    /**
     * @param  array{type: string, audience: string, title: ?string, body: ?string, team_ids?: array, user_ids?: array, channels?: array}  $data
     * @param  UploadedFile[]  $files
     */
    public function send(array $data, array $files, User $author): string
    {
        $batch = (string) Str::uuid();
        $termId = app(CurrentTerm::class)->get() ?? AcademicTerm::where('is_current', true)->value('id');

        if (! $termId) {
            throw ValidationException::withMessages(['term_id' => 'لا يوجد فصل دراسي محدد.']);
        }

        [$rows, $notify] = $data['audience'] === 'groups'
            ? $this->forTeams($data['team_ids'] ?? [])
            : $this->forUsers($data['user_ids'] ?? [], $data['audience'] === 'committee' ? RoleEnum::Committee : RoleEnum::Supervisor);

        $base = [
            'batch' => $batch,
            'author_id' => $author->id,
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? '',
            'channels' => array_values(array_unique(['platform', ...($data['channels'] ?? [])])),
            'term_id' => $termId,
        ];

        DB::transaction(function () use ($rows, $base, $files, $batch) {
            foreach ($rows as $row) {
                Announcement::create([...$base, ...$row]);
            }
            foreach ($files as $file) {
                AnnouncementAttachment::create([
                    'batch' => $batch,
                    'name' => $file->getClientOriginalName(),
                    'path' => Storage::putFile("announcements/{$batch}", $file),
                    'size' => $file->getSize(),
                ]);
            }
        });

        // قناة المنصة: إشعار واحد لكل شخص مهما تعدّدت مجموعاته المستلمة
        $label = $data['type'] === 'file' ? 'ملف جديد من الإدارة' : 'إعلان جديد من الإدارة';
        $notify->reject(fn ($id) => $id === $author->id)->unique()->each(fn ($userId) => Notification::create([
            'user_id' => $userId,
            'type' => $data['type'] === 'file' ? 'admin_file' : 'admin_announcement',
            'message' => $base['title'] ? "{$label}: \"{$base['title']}\"." : "{$label}.",
        ]));

        return $batch;
    }

    /**
     * تقييم المشرفين: إرسال واحد (batch) لعدة مشرفين، لكل مشرف نجوم (1–5) وملاحظة عن تقدّم مجموعاته،
     * مع رسالة عامة اختيارية تُلحق بكل ملاحظة. يصل على المنصة (إشعار + صندوق الإعلانات).
     *
     * @param  array{ratings: array<int, array{supervisor_id: int, rating: int, note: ?string}>, general: ?string, channels?: array}  $data
     */
    public function sendRatings(array $data, User $author): string
    {
        $ids = array_map('intval', array_column($data['ratings'], 'supervisor_id'));
        if (count($ids) !== count(array_unique($ids)) || User::whereIn('id', $ids)->where('role', RoleEnum::Supervisor)->count() !== count($ids)) {
            throw ValidationException::withMessages(['ratings' => 'المستلمون يجب أن يكونوا مشرفين دون تكرار.']);
        }

        $batch = (string) Str::uuid();
        $termId = app(CurrentTerm::class)->get() ?? AcademicTerm::where('is_current', true)->value('id');
        $channels = array_values(array_unique(['platform', ...($data['channels'] ?? [])]));

        DB::transaction(function () use ($data, $author, $batch, $termId, $channels) {
            foreach ($data['ratings'] as $r) {
                Announcement::create([
                    'batch' => $batch,
                    'author_id' => $author->id,
                    'kind' => 'supervisor',
                    'type' => 'rating',
                    'target_user_id' => $r['supervisor_id'],
                    'title' => 'تقييم تقدّم مجموعاتك',
                    'body' => implode("\n\n", array_filter([$r['note'] ?? null, $data['general'] ?? null])) ?: "تقييم الإدارة العامة لتقدّم مجموعاتك: {$r['rating']} من 5",
                    'rating' => $r['rating'],
                    'channels' => $channels,
                    'term_id' => $termId,
                ]);
            }
        });

        foreach ($data['ratings'] as $r) {
            Notification::create([
                'user_id' => $r['supervisor_id'],
                'type' => 'admin_rating',
                'message' => "تقييم جديد من الإدارة لتقدّم مجموعاتك: {$r['rating']} من 5.",
            ]);
        }

        return $batch;
    }

    /** المستلمون مجموعات: صف لكل مجموعة، والإشعار لطلابها ومشرفها */
    private function forTeams(array $teamIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $teamIds)));
        $teams = Team::with('members:id,team_id,student_id')->whereIn('id', $ids)->get();

        if (! $ids || $teams->count() !== count($ids)) {
            throw ValidationException::withMessages(['team_ids' => 'إحدى المجموعات غير موجودة في الفصل الحالي.']);
        }

        return [
            $teams->map(fn (Team $t) => ['kind' => 'team', 'team_id' => $t->id])->all(),
            $teams->flatMap(fn (Team $t) => [$t->supervisor_id, ...$t->members->pluck('student_id')])->filter(),
        ];
    }

    /** المستلمون أشخاص (أعضاء لجنة أو مشرفون): صف وإشعار لكل شخص */
    private function forUsers(array $userIds, RoleEnum $role): array
    {
        $ids = array_values(array_unique(array_map('intval', $userIds)));

        if (! $ids || User::whereIn('id', $ids)->where('role', $role)->count() !== count($ids)) {
            throw ValidationException::withMessages(['user_ids' => $role === RoleEnum::Committee
                ? 'المستلمون يجب أن يكونوا أعضاء في لجنة الإشراف.'
                : 'المستلمون يجب أن يكونوا مشرفين.']);
        }

        return [
            array_map(fn ($id) => ['kind' => 'user', 'target_user_id' => $id], $ids),
            collect($ids),
        ];
    }

    /**
     * المراسلات المرسَلة مجمّعة بالـbatch، بفلاتر مستقلة تُطبَّق في الخادم:
     * type, audience (groups|committee|supervisors), team_id, supervisor_id, user_id, q.
     */
    public function sent(array $f): array
    {
        $rows = fn () => Announcement::query()->whereNotNull('batch')
            ->when($f['type'] ?? null, fn ($q, $t) => $q->where('type', $t));

        $audience = fn (Builder $q, string $a) => match ($a) {
            'groups' => $q->where('kind', 'team'),
            'committee' => $q->where('kind', 'user')->whereHas('targetUser', fn ($u) => $u->where('role', RoleEnum::Committee)),
            'supervisors' => $q->whereIn('kind', ['user', 'supervisor'])->whereHas('targetUser', fn ($u) => $u->where('role', RoleEnum::Supervisor)),
        };

        // الفلاتر تحدد أي batch يظهر؛ ثم يُجلب كل مستلمي الـbatch ليُعرض الإرسال كاملًا
        $matching = fn (?string $a) => $rows()
            ->when($a, fn ($q) => $audience($q, $a))
            ->when($f['team_id'] ?? null, fn ($q, $id) => $q->where('team_id', $id))
            ->when($f['user_id'] ?? null, fn ($q, $id) => $q->where('target_user_id', $id))
            ->when($f['supervisor_id'] ?? null, fn ($q, $id) => $q->where(fn ($q) => $q
                ->whereHas('team', fn ($t) => $t->where('supervisor_id', $id))
                ->orWhere('target_user_id', $id)))
            ->when($f['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('title', 'like', "%{$term}%")->orWhere('body', 'like', "%{$term}%")))
            ->distinct()->pluck('batch');

        $batches = $matching($f['audience'] ?? null);

        $items = Announcement::with('author:id,name,role', 'team:id,name,section', 'targetUser:id,name,role', 'attachments')
            ->whereIn('batch', $batches)
            ->latest()
            ->get()
            ->groupBy('batch')
            ->map(fn (Collection $group) => $this->present($group))
            ->sortByDesc('created_at')
            ->values();

        return [
            'data' => $items,
            'meta' => ['counts' => [
                'all' => $matching(null)->count(),
                'groups' => $matching('groups')->count(),
                'committee' => $matching('committee')->count(),
                'supervisors' => $matching('supervisors')->count(),
            ]],
        ];
    }

    /** إرسال واحد للعرض: المحتوى + المرسل + التاريخ + الجهة + المستلمون + المرفقات + القنوات */
    public function present(Collection $group): array
    {
        $first = $group->first();
        $isTeams = $first->kind === 'team';

        return [
            'batch' => $first->batch,
            'type' => $first->type,
            'title' => $first->title,
            'body' => $first->body,
            'channels' => $first->channels ?? ['platform'],
            'created_at' => $first->created_at,
            'author' => $first->author ? ['id' => $first->author->id, 'name' => $first->author->name] : null,
            'audience' => $isTeams ? 'groups' : ($first->targetUser?->role === RoleEnum::Committee ? 'committee' : 'supervisors'),
            // تقييم المشرفين: لكل مستلم نجومه وملاحظته
            'recipients' => $group->map(fn (Announcement $a) => $isTeams
                ? ['id' => $a->team_id, 'name' => $a->team ? ($a->team->section ? "شعبة {$a->team->section}" : $a->team->name) : '—']
                : ['id' => $a->target_user_id, 'name' => $a->targetUser?->name ?? '—', ...($a->type === 'rating' ? ['rating' => $a->rating, 'note' => $a->body] : [])])->values(),
            'attachments' => $first->attachments->map(fn ($f) => ['id' => $f->id, 'name' => $f->name, 'size' => $f->size])->values(),
        ];
    }

    /** حذف إرسال كامل: كل صفوفه ومرفقاته */
    public function deleteBatch(string $batch): void
    {
        $attachments = AnnouncementAttachment::where('batch', $batch)->get();

        DB::transaction(function () use ($batch, $attachments) {
            Announcement::where('batch', $batch)->delete();
            AnnouncementAttachment::where('batch', $batch)->delete();
        });

        Storage::delete($attachments->pluck('path')->all());
    }
}
