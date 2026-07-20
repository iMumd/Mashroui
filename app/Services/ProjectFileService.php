<?php

namespace App\Services;

use App\Events\FileUploaded;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProjectFileService
{
    public function upload(array $data, UploadedFile $file, User $leader): ProjectFile
    {
        $project = Project::findOrFail($data['project_id']);

        if ($project->team->leader_id !== $leader->id) {
            throw ValidationException::withMessages(['project_id' => 'هذا المشروع مش تبع فريقك.']);
        }

        $stage = $data['stage'] ?? null;

        $version = ProjectFile::where('project_id', $project->id)
            ->where('stage', $stage)
            ->max('version') + 1;

        $projectFile = ProjectFile::create([
            'project_id' => $project->id,
            'stage' => $stage,
            'file_path' => Storage::putFile('project_files', $file),
            'version' => $version,
            'uploaded_by' => $leader->id,
            'uploaded_at' => now(),
        ]);

        FileUploaded::dispatch($projectFile, $leader);

        return $projectFile;
    }
}
