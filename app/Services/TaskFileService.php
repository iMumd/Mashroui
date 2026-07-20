<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TaskFileService
{
    public function upload(Task $task, UploadedFile $file, User $uploader): TaskFile
    {
        $team = $task->team()->with('members')->first();

        $isMember = $uploader->id === $team->leader_id
            || $team->members->contains('student_id', $uploader->id);

        if (! $isMember) {
            throw ValidationException::withMessages(['task_id' => 'هاي المهمة مش تبعك.']);
        }

        return TaskFile::create([
            'task_id' => $task->id,
            'file_path' => Storage::putFile('task_files', $file),
            'uploaded_by' => $uploader->id,
        ]);
    }
}
