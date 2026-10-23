<?php

namespace Database\Seeders;

use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Task;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $leader = User::where('email', 'leader@smail.ucas.edu.ps')->first();
        $student = User::where('email', 'student@smail.ucas.edu.ps')->first();
        $supervisor = User::where('email', 'supervisor@ucas.edu.ps')->first();
        $committee = User::where('email', 'committee@ucas.edu.ps')->first();
        $admin = User::where('role', 'super_admin')->first();

        $term = \App\Models\AcademicTerm::where('is_current', true)->first();
        $termId = $term?->id;
        $teams = Team::withoutGlobalScopes()->where('term_id', $termId)->get();

        // For test accounts
        if ($leader) {
            $this->notif($leader->id, 'task_created', 'تم إسناد مهمة جديدة: "بناء وحدات الأصناف والمخزون" إلى فريقك.', 2);
            $this->notif($leader->id, 'meeting', 'هناك اجتماع مقرر مع المشرف غدًا في تمام الساعة 11:00 صباحًا.', 1);
            $this->notif($leader->id, 'announcement', 'إعلان جديد من المشرف: تذكير برفع ملفات المهام.', 3);
            $this->notif($leader->id, 'report_review', 'تم رفع تقريرك النهائي للمراجعة، بانتظار مراجعة المشرف.', 4, false);
        }
        if ($student) {
            $this->notif($student->id, 'task_created', 'لديك مهمة جديدة ضمن فريق إدارة المخزون الذكي.', 2);
            $this->notif($student->id, 'meeting', 'اجتماع متابعة مقرر مع المشرف قريبًا.', 1);
            $this->notif($student->id, 'announcement', 'إعلان فريق جديد: جاهزية للعرض المرحلي.', 2);
        }
        if ($supervisor) {
            $this->notif($supervisor->id, 'proposal_review', 'هناك مقترح مشروع بانتظار المراجعة.', 3);
            $this->notif($supervisor->id, 'task_created', 'مهمة لجنة الإشراف: تحديث خطة المتابعة الأسبوعية.', 2);
            $this->notif($supervisor->id, 'meeting', 'اجتماع مع فريقك مقرر هذا الأسبوع.', 1, false);
        }
        if ($committee) {
            $this->notif($committee->id, 'task_created', 'مهمة لجنة: متابعة المجموعات المتأخرة في الإنجاز.', 1, false);
            $this->notif($committee->id, 'announcement', 'إعلان لجنة جديد: جدول اجتماعات لجنة الإشراف.', 2);
            $this->notif($committee->id, 'ticket', 'تذكرة دعم جديدة بانتظار المراجعة.', 1, false);
        }
        if ($admin) {
            $this->notif($admin->id, 'ticket', 'هناك تذاكر دعم لم يتم الرد عليها بعد.', 1, false);
            $this->notif($admin->id, 'announcement', 'تم إرسال إشعارات تقييم للمشرفين.', 3);
        }

        // Generate for all users
        foreach ($teams as $team) {
            foreach ($team->members as $member) {
                if ($member->id === $leader?->id || $member->id === $student?->id) continue;
                $this->notif($member->id, 'task_created', 'لديك مهمة جديدة ضمن فريق '.$team->name.'.', rand(1,5));
                if (rand(0,1)) {
                    $this->notif($member->id, 'meeting', 'اجتماع مقرر مع المشرف لمتابعة التقدم.', rand(1,4));
                }
                if (rand(0,1)) {
                    $this->notif($member->id, 'announcement', 'إعلان جديد من فريقك.', rand(1,6));
                }
            }
            if ($team->supervisor_id && $team->supervisor_id !== $supervisor?->id) {
                $this->notif($team->supervisor_id, 'task_created', 'يجب متابعة مهام فريق '.$team->name.'.', rand(1,4));
            }
        }

        // Committee members
        foreach (\App\Models\User::where('role', 'committee')->get() as $c) {
            if ($c->id === $committee?->id) continue;
            $this->notif($c->id, 'task_created', 'مهمة لجنة الإشراف بانتظار الإنجاز.', rand(1,5));
            $this->notif($c->id, 'ticket', 'تذكرة دعم جديدة.', rand(1,3));
        }
    }

    protected function notif($userId, $type, $message, $daysAgo, $read = null): void
    {
        if (! $userId) return;
        if (Notification::where('user_id', $userId)->where('type', $type)->where('message', $message)->exists()) {
            return;
        }
        $n = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'message' => $message,
            'is_read' => $read ?? (rand(0,2) === 0),
        ]);
        $n->forceFill(['created_at' => now()->subDays($daysAgo)->subHours(rand(0,6)), 'updated_at' => now()->subDays($daysAgo)])->save();
    }
}
