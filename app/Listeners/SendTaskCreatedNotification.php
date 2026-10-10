<?php

namespace App\Listeners;

use App\Events\TaskCreated;
use App\Models\Notification;

class SendTaskCreatedNotification
{
    public function handle(TaskCreated $event): void
    {
        $task = $event->task;
        $team = $task->team;

        // مهمة لجنة الإشراف (بلا فريق) تُشعر عضو اللجنة المكلّف بها
        $recipients = collect($team ? [$team->leader_id, $team->supervisor_id] : $task->assignees()->pluck('users.id')->all())
            ->filter()
            ->unique()
            ->reject(fn ($id) => $id === $task->created_by);

        foreach ($recipients as $userId) {
            Notification::create([
                'user_id' => $userId,
                'type' => 'task_created',
                'message' => "مهمة جديدة: \"{$task->title}\".",
            ]);
        }
    }
}
