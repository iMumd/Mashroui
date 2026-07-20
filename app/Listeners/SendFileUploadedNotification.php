<?php

namespace App\Listeners;

use App\Events\FileUploaded;
use App\Models\FinalReport;
use App\Models\Notification;
use App\Models\ProjectFile;
use App\Models\TaskFile;
use App\Models\User;

class SendFileUploadedNotification
{
    public function handle(FileUploaded $event): void
    {
        match (true) {
            $event->file instanceof ProjectFile => $this->notifyProjectFile($event->file),
            $event->file instanceof FinalReport => $this->notifyFinalReport($event->file),
            $event->file instanceof TaskFile => $this->notifyTaskFile($event->file, $event->uploader),
            default => null,
        };
    }

    private function notifyProjectFile(ProjectFile $file): void
    {
        Notification::create([
            'user_id' => $file->project->supervisor_id,
            'type' => 'project_file_uploaded',
            'message' => 'تم رفع ملف جديد على مشروعك.',
        ]);
    }

    private function notifyFinalReport(FinalReport $file): void
    {
        Notification::create([
            'user_id' => $file->project->supervisor_id,
            'type' => 'final_report_uploaded',
            'message' => 'تم رفع التقرير النهائي.',
        ]);
    }

    private function notifyTaskFile(TaskFile $file, User $uploader): void
    {
        $team = $file->task->team;

        $recipients = collect([$team->leader_id, $team->supervisor_id])
            ->filter()
            ->unique()
            ->reject(fn ($id) => $id === $uploader->id);

        foreach ($recipients as $userId) {
            Notification::create([
                'user_id' => $userId,
                'type' => 'task_file_uploaded',
                'message' => "تم رفع ملف تسليم على المهمة \"{$file->task->title}\".",
            ]);
        }
    }
}
