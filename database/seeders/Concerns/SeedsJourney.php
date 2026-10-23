<?php

namespace Database\Seeders\Concerns;

use App\Enums\ProjectStatusEnum;
use App\Enums\ProposalStatusEnum;
use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\FinalReport;
use App\Models\Meeting;
use App\Models\MeetingReminder;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Specialization;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\TaskNote;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait SeedsJourney
{
    use UpsertsUsers;

    protected ?int $termId = null;

    protected int $studentSeq = 1000;

    /** @var array<string, Specialization> */
    protected array $specCache = [];

    protected function spec(string $name, ?string $degree = null): Specialization
    {
        $key = $name.'|'.($degree ?? '');
        if (isset($this->specCache[$key])) {
            return $this->specCache[$key];
        }

        $q = Specialization::query()->where('name', $name);
        if ($degree) {
            $q->where('degree', $degree);
        }

        if (! $q->exists()) {
            \Illuminate\Support\Facades\Log::warning('spec missing', ['name' => $name]);
        }

        $this->specCache[$key] = $q->firstOrFail();

        return $this->specCache[$key];
    }

    protected function staff(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    protected function phoneSuffix(int $seq): string
    {
        $prefixes = ['59', '56', '52', '54'];
        $p = $prefixes[$seq % count($prefixes)];
        $num = str_pad((string) (2100000 + ($seq * 137)), 7, '0', STR_PAD_LEFT);

        return $p.$num;
    }

    protected function universityNumber(int $seq): string
    {
        $prefixes = ['120', '121', '122'];
        $pref = $prefixes[$seq % count($prefixes)];
        $num = str_pad((string) (10000 + ($seq * 13)), 5, '0', STR_PAD_LEFT);

        return $pref.$num;
    }

    protected function regionFor(int $seq): string
    {
        $map = ['gaza', 'gaza', 'gaza', 'gaza', 'middle', 'south'];
        return $map[$seq % count($map)];
    }

    protected function member(array $m, Specialization $spec): User
    {
        $gender = $m['gender'] ?? $this->genderOf($m['name']);
        $seq = $this->studentSeq++;
        $email = $m['email'];

        $user = $this->upsertUser($email, [
            'name' => $m['name'],
            'password' => 'password',
            'role' => RoleEnum::Student,
            'gender' => $gender,
            'region' => $m['region'] ?? $this->regionFor($seq),
            'specialization_id' => $spec->id,
            'term_id' => $this->termId,
            'whatsapp' => '970'.$this->phoneSuffix($seq),
            'university_number' => $this->universityNumber($seq),
        ]);

        $fix = [];
        if (($user->specialization_id ?? null) !== $spec->id) {
            $fix['specialization_id'] = $spec->id;
        }
        if (($user->term_id ?? null) !== $this->termId) {
            $fix['term_id'] = $this->termId;
        }
        if (($user->role?->value ?? null) !== RoleEnum::Student->value) {
            $fix['role'] = RoleEnum::Student;
        }
        if ($fix) {
            $user->update($fix);
            $user = $user->fresh();
        }

        return $user;
    }

    protected function nextSection(): string
    {
        $count = Team::withoutGlobalScopes()->withTrashed()->count();
        return (string) (100 + $count + 1);
    }

    /**
     * @return array{0:Team,1:Project}
     */
    protected function makeTeam(string $name, User $supervisor, User $leader, array $members, Specialization $spec, string $projectName, string $description, array $types, ProjectStatusEnum $status, ?int $completedDaysAgo = null): array
    {
        $team = Team::withoutGlobalScopes()->withTrashed()
            ->where('name', $name)
            ->where('term_id', $this->termId)
            ->first();

        if ($team) {
            $project = Project::withoutGlobalScopes()->where('team_id', $team->id)->firstOrFail();
            $project->update([
                'name' => $projectName,
                'description' => $description,
                'types' => $types,
                'status' => $status,
                'supervisor_id' => $supervisor->id,
                'specialization_id' => $spec->id,
                'department_id' => $spec->department_id,
                'term_id' => $this->termId,
                'completed_at' => $status === ProjectStatusEnum::Completed ? ($project->completed_at ?? now()->subDays($completedDaysAgo ?? 14)) : null,
            ]);
            $team->update([
                'supervisor_id' => $supervisor->id,
                'leader_id' => $leader->id,
                'specialization_id' => $spec->id,
                'term_id' => $this->termId,
            ]);

            $this->syncMembers($team, $leader, $members);

            return [$team, $project];
        }

        $team = Team::create([
            'name' => $name,
            'section' => $this->nextSection(),
            'supervisor_id' => $supervisor->id,
            'specialization_id' => $spec->id,
            'term_id' => $this->termId,
            'leader_id' => $leader->id,
        ]);

        $project = Project::create([
            'team_id' => $team->id,
            'supervisor_id' => $supervisor->id,
            'name' => $projectName,
            'description' => $description,
            'types' => $types,
            'department_id' => $spec->department_id,
            'specialization_id' => $spec->id,
            'term_id' => $this->termId,
            'status' => $status,
            'is_featured' => false,
            'completed_at' => $status === ProjectStatusEnum::Completed ? now()->subDays($completedDaysAgo ?? 14) : null,
        ]);

        $this->syncMembers($team, $leader, $members);

        return [$team, $project];
    }

    protected function syncMembers(Team $team, User $leader, array $members): void
    {
        $ids = [];
        // include leader first if not present
        $ids[] = $leader->id;
        foreach ($members as $m) {
            if ($m instanceof User) {
                if ($m->id === $leader->id) continue;
                $ids[] = $m->id;
            }
        }
        $ids = array_values(array_unique($ids));
        // cap to 4
        $ids = array_slice($ids, 0, 4);

        TeamMember::where('team_id', $team->id)->whereNotIn('student_id', $ids)->delete();
        foreach ($ids as $id) {
            TeamMember::updateOrCreate(
                ['team_id' => $team->id, 'student_id' => $id],
                ['is_leader' => $id === $leader->id],
            );
        }
        if ($team->leader_id !== $leader->id) {
            $team->update(['leader_id' => $leader->id]);
        }
    }

    protected function proposalLog(User $actor, Proposal $proposal, string $action, array $meta = [], ?Carbon $at = null): void
    {
        $at ??= now();
        AuditLog::create([
            'user_id' => $actor->id,
            'entity' => 'proposal',
            'entity_id' => $proposal->id,
            'action' => $action,
            'meta' => $meta,
        ])->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }

    protected function proposal(Project $project, User $leader, ?User $supervisor, array $p): void
    {
        $state = $p['state'] ?? 'none';
        if ($state === 'none') {
            return;
        }

        $pdf = $this->placeholderPdf('proposals', $p['file'] ?? ($project->name.'.pdf'));

        $base = [
            'project_id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'problems' => $p['problems'] ?? 'تُلاحظ صعوبات في إنجاز العملية الحالية يدويةً مما يؤدي إلى فقدان الوقت وزيادة الأخطاء.',
            'solutions' => $p['solutions'] ?? 'يقوم النظام على أتمتة العملية وربطها بقاعدة بيانات مركزية مع صلاحيات واضحة لكل مستخدم.',
            'features_value' => $p['features'] ?? 'سهولة الاستخدام، حفظ الوقت، تقليل الأخطاء، قابلية التوسع، وأمان البيانات.',
            'pdf_path' => $pdf,
            'submitted_by' => $leader->id,
        ];

        if ($state === 'pending') {
            $proposal = Proposal::updateOrCreate(['project_id' => $project->id], $base + [
                'status' => ProposalStatusEnum::Pending,
                'rejection_reason' => null,
                'rejection_count' => 0,
                'reviewed_by' => null,
            ]);
            $proposal->forceFill([
                'created_at' => now()->subDays($p['submitted_days'] ?? 10),
                'updated_at' => now()->subDays($p['updated_days'] ?? 9),
            ])->save();
            $this->proposalLog($leader, $proposal, 'submit', ['status' => 'pending'], now()->subDays($p['submitted_days'] ?? 10));
            return;
        }

        if ($state === 'approved') {
            $proposal = Proposal::updateOrCreate(['project_id' => $project->id], $base + [
                'status' => ProposalStatusEnum::Approved,
                'rejection_reason' => null,
                'rejection_count' => 0,
                'reviewed_by' => $supervisor?->id ?? $leader->id,
            ]);
            $proposal->forceFill([
                'created_at' => now()->subDays($p['submitted_days'] ?? 14),
                'updated_at' => now()->subDays($p['review_days'] ?? 6),
            ])->save();
            $submitAt = now()->subDays($p['submitted_days'] ?? 14);
            $reviewAt = now()->subDays($p['review_days'] ?? 6);
            $this->proposalLog($leader, $proposal, 'submit', ['status' => 'pending'], $submitAt);
            $this->proposalLog($supervisor ?? $leader, $proposal, 'approve', ['status' => 'approved'], $reviewAt);
            return;
        }

        if ($state === 'rejected') {
            $reasons = $p['reasons'] ?? [
                'يجب توضيح نطاق المشروع وحدوده بشكل أكثر دقة وربطه بأهداف قابلة للقياس.',
                'نقص في شرح كيفية الاستفادة الفعلية من المشروع على أرض الواقع.',
            ];
            $proposal = Proposal::updateOrCreate(['project_id' => $project->id], $base + [
                'status' => ProposalStatusEnum::Rejected,
                'rejection_reason' => $reasons[array_key_last($reasons)] ?? end($reasons),
                'rejection_count' => count($reasons),
                'reviewed_by' => $supervisor?->id ?? $leader->id,
            ]);
            $proposal->forceFill([
                'created_at' => now()->subDays($p['submitted_days'] ?? 16),
                'updated_at' => now()->subDays($p['review_days'] ?? 5),
            ])->save();
            $t = now()->subDays($p['submitted_days'] ?? 16);
            $this->proposalLog($leader, $proposal, 'submit', ['attempt' => 1], $t->copy()->subDays(8));
            $this->proposalLog($supervisor ?? $leader, $proposal, 'reject', ['reason' => $reasons[0] ?? 'يحتاج تعديلاً'], $t->copy()->subDays(4));
            if (count($reasons) >= 2) {
                $this->proposalLog($leader, $proposal, 'resubmit', ['attempt' => 2], $t->copy()->subDays(2));
                $this->proposalLog($supervisor ?? $leader, $proposal, 'reject', ['reason' => $reasons[1]], now()->subDays($p['review_days'] ?? 5));
            }
            return;
        }
    }

    protected function dueFor(TaskStatusEnum $status, int $i): string
    {
        $i = abs($i);
        return match ($status) {
            TaskStatusEnum::Done => now()->subDays(max(2, 26 - ($i * 3)))->toDateString(),
            TaskStatusEnum::Review => now()->addDays(1 + ($i % 2))->toDateString(),
            TaskStatusEnum::InProgress => ($i % 5 === 4) ? now()->subDays(1 + ($i % 3))->toDateString() : now()->addDays(3 + ($i % 5))->toDateString(),
            TaskStatusEnum::Pending => ($i % 6 === 5) ? now()->subDays(1)->toDateString() : now()->addDays(9 + $i)->toDateString(),
        };
    }

    protected function tasks(Team $team, User $creator, array $items): void
    {
        $members = $team->members()->orderBy('id')->pluck('student_id')->values()->all();
        if (empty($members) && $team->leader_id) {
            $members = [$team->leader_id];
        }

        foreach ($items as $i => $item) {
            $title = $item[0];
            $status = $item[1] instanceof TaskStatusEnum ? $item[1] : TaskStatusEnum::from($item[1]);
            $desc = $item[2] ?? null;
            $assign = $item[3] ?? null;

            $task = Task::withTrashed()->where('team_id', $team->id)->where('title', $title)->first();
            if (! $task) {
                $task = Task::create([
                    'team_id' => $team->id,
                    'term_id' => $this->termId,
                    'title' => $title,
                    'description' => $desc,
                    'status' => $status,
                    'due_date' => $this->dueFor($status, $i),
                    'created_by' => $creator->id,
                ]);
            } else {
                $task->fill([
                    'term_id' => $this->termId,
                    'description' => $desc ?? $task->description,
                    'status' => $status,
                    'due_date' => $this->dueFor($status, $i),
                ])->save();
            }

            if ($assign === null) {
                if (empty($members)) {
                    $assign = [];
                } else {
                    $assign = [$members[$i % count($members)]];
                }
            } else {
                // مرّر فهارس أعضاء الفريق، وحوّلها إلى معرّفات مستخدمين
                $assign = array_values(array_filter(array_map(fn ($idx) => $members[$idx] ?? null, (array) $assign)));
            }

            if (! empty($assign)) {
                $task->assignees()->sync(array_values(array_unique($assign)));
            }

            if (! $task->notes()->exists() && $i % 2 === 0 && $status !== TaskStatusEnum::Pending) {
                $at = now()->subDays(max(1, 10 - $i));
                $studentNote = TaskNote::create([
                    'task_id' => $task->id,
                    'user_id' => $assign[0] ?? ($members[0] ?? $creator->id),
                    'note' => $status === TaskStatusEnum::Done
                        ? 'خلصت المهمة ورفعت المخرجات على المنصة، جاهزة للمراجعة.'
                        : ($status === TaskStatusEnum::Review
                            ? 'رفعت النسخة الحالية للمراجعة، جاهز لأي تعديلات.'
                            : 'بديت فيها، راجعت الشروط وقسمتها لخطوات، وأكملها حالًا.'),
                ]);
                $studentNote->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

                if ($status === TaskStatusEnum::Review && $team->supervisor_id) {
                    $sat = $at->copy()->addHours(4);
                    $sn = TaskNote::create([
                        'task_id' => $task->id,
                        'user_id' => $team->supervisor_id,
                        'note' => 'راجعت النسخة مبدئيًا، هناك ملاحظات بسيطة — راجع قسم التعليقات وأعد الرفع بعد التعديل.',
                    ]);
                    $sn->forceFill(['created_at' => $sat, 'updated_at' => $sat])->save();
                }
            }

            if (! $task->files()->exists() && $i % 3 === 0 && in_array($status, [TaskStatusEnum::Done, TaskStatusEnum::Review], true)) {
                $path = $this->placeholderPdf('task_files', Str::slug($title).'-'.$task->id.'.pdf');
                $f = TaskFile::create([
                    'task_id' => $task->id,
                    'uploaded_by' => $assign[0] ?? ($members[0] ?? $creator->id),
                    'file_path' => $path,
                ]);
                $f->forceFill(['created_at' => now()->subDays(max(1, 8 - $i)), 'updated_at' => now()->subDays(max(1, 8 - $i))])->save();
            }

            if ($status === TaskStatusEnum::Done && ! AuditLog::where('entity', 'task')->where('entity_id', $task->id)->where('action', 'status_changed')->exists()) {
                AuditLog::create([
                    'user_id' => $assign[0] ?? $creator->id,
                    'entity' => 'task',
                    'entity_id' => $task->id,
                    'action' => 'status_changed',
                    'meta' => ['from' => 'in_progress', 'to' => 'done'],
                ])->forceFill(['created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)])->save();
            }
        }
    }

    protected function meetings(Team $team, User $creator, array $items): void
    {
        $links = [
            'https://meet.google.com/msh-journey-'.Str::random(3).'-'.Str::random(3),
            'https://meet.google.com/mkf-demo-'.Str::random(4),
        ];

        foreach ($items as $i => $item) {
            $title = $item[0];
            $offset = $item[1] ?? (-($i + 2));
            $notes = $item[2] ?? 'متابعة التقدم، مراجعة المهام، واتخاذ الإجراءات اللازمة.';
            $when = now()->addDays((int) $offset)->setTime(11, 0, 0);

            $meeting = Meeting::where('team_id', $team->id)->where('title', $title)->first();
            if (! $meeting) {
                $meeting = Meeting::create([
                    'team_id' => $team->id,
                    'title' => $title,
                    'scheduled_at' => $when,
                    'google_meet_link' => $links[$i % count($links)],
                    'notes' => $notes,
                    'created_by' => $creator->id,
                ]);
            } else {
                $meeting->update([
                    'scheduled_at' => $when,
                    'google_meet_link' => $links[$i % count($links)],
                    'notes' => $notes,
                ]);
            }

            if (! $meeting->reminders()->exists()) {
                $remind = $meeting->scheduled_at->copy()->subDay();
                MeetingReminder::create([
                    'meeting_id' => $meeting->id,
                    'remind_at' => $remind,
                    'channel' => $i % 2 === 0 ? 'whatsapp' : 'email',
                    'sent_at' => $remind->isPast() ? $remind->copy()->addMinutes(30) : null,
                ]);
            }
        }
    }

    protected function finalReport(Project $project, User $leader, ?User $supervisor, ?User $committee, array $r): void
    {
        $pdf = $this->placeholderPdf('final_reports', Str::slug($project->name).'-final.pdf');
        $video = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

        $report = FinalReport::where('project_id', $project->id)->first();
        if (! $report) {
            $report = FinalReport::create([
                'project_id' => $project->id,
                'pdf_path' => $pdf,
                'video_url' => $video,
                'review_status' => $r['review'] ?? 'pending_supervisor',
                'uploaded_by' => $leader->id,
            ]);
        } else {
            $report->update([
                'pdf_path' => $pdf,
                'video_url' => $video,
                'review_status' => $r['review'] ?? $report->review_status,
            ]);
        }

        $review = $r['review'] ?? 'pending_supervisor';
        if ($review === 'pending_committee' && $supervisor) {
            $sat = now()->subDays($r['supervisor_days'] ?? 4);
            $report->update([
                'supervisor_reviewed_by' => $supervisor->id,
                'supervisor_reviewed_at' => $sat,
            ]);
        } elseif ($review === 'approved') {
            $sat = now()->subDays($r['supervisor_days'] ?? 6);
            $report->update([
                'supervisor_reviewed_by' => $supervisor?->id ?? $leader->id,
                'supervisor_reviewed_at' => $sat,
            ]);
            if ($committee) {
                $cat = now()->subDays($r['committee_days'] ?? 3);
                $report->update([
                    'committee_reviewed_by' => $committee->id,
                    'committee_reviewed_at' => $cat,
                ]);
            }
            if (! $report->review_note) {
                $report->update(['review_note' => 'ممتاز، التقرير كامل ومتكامل. يوصى بالموافقة عليه.']);
            }
        } elseif ($review === 'rejected') {
            $sat = now()->subDays($r['supervisor_days'] ?? 5);
            $report->update([
                'supervisor_reviewed_by' => $supervisor?->id ?? $leader->id,
                'supervisor_reviewed_at' => $sat,
                'review_note' => $r['note'] ?? 'يجب توسيع فصل النتائج وتحليلها، وإضافة المراجع وفق النمط المتبع.',
            ]);
            if ($committee) {
                $cat = now()->subDays($r['committee_days'] ?? 2);
                $report->update([
                    'committee_reviewed_by' => $committee->id,
                    'committee_reviewed_at' => $cat,
                ]);
            }
        }
    }

    protected function teamAnnouncements(Team $team, array $items): void
    {
        foreach ($items as $i => $a) {
            $title = $a['title'];
            if (Announcement::withoutGlobalScopes()->where('kind', 'team')->where('team_id', $team->id)->where('title', $title)->exists()) {
                continue;
            }
            $author = $a['author'] ?? User::where('role', RoleEnum::Supervisor)->first();
            $days = $a['days'] ?? ($i + 1);
            $ann = Announcement::create([
                'kind' => 'team',
                'team_id' => $team->id,
                'author_id' => $author?->id,
                'title' => $title,
                'body' => $a['body'] ?? '',
                'type' => 'announcement',
                'term_id' => $this->termId,
            ]);
            $at = now()->subDays($days);
            $ann->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
        }
    }

    protected function placeholderPdf(string $dir, string $name): string
    {
        $dir = trim($dir, '/');
        $path = $dir.'/'.Str::slug(pathinfo($name, PATHINFO_FILENAME)).'.'.(pathinfo($name, PATHINFO_EXTENSION) ?: 'pdf');
        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->makeDirectory($dir);
            // minimal valid PDF
            $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R/Resources<</Font<</F1<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>>>>>endobj\n4 0 obj<</Length 44>>stream\nBT/F1 12 Tf 50 800 Td (Mashroui Demo) Tj ET\nendstream\nendobj\nxref\n0 5\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000317 00000 n \ntrailer<</Size 5/Root 1 0 R>>\nstartxref\n410\n%%EOF";
            Storage::disk('public')->put($path, $pdf);
        }
        return $path;
    }
}
