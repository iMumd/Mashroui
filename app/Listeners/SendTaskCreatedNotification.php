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

        $recipients = collect([$team->leader_id, $team->supervisor_id])
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
