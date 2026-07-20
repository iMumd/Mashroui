<?php

namespace App\Services;

use App\Events\DiscussionScheduled;
use App\Models\Discussion;
use App\Models\Project;
use App\Support\CurrentTerm;
use Illuminate\Validation\ValidationException;

class DiscussionService
{
    public function __construct(private CurrentTerm $currentTerm) {}

    public function create(array $data): Discussion
    {
        $termId = $this->currentTerm->get();

        if (! $termId) {
            throw ValidationException::withMessages(['term_id' => 'لا يوجد فصل دراسي محدد.']);
        }

        $project = Project::with('team')->findOrFail($data['project_id']);

        $discussion = Discussion::create([
            'project_id' => $project->id,
            'supervisor_id' => $data['supervisor_id'],
            'place' => $data['place'],
            'discussion_date' => $data['discussion_date'],
            'discussion_time' => $data['discussion_time'],
            'committee' => $data['committee'],
            'whatsapp' => $data['whatsapp'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'term_id' => $termId,
        ]);

        DiscussionScheduled::dispatch($discussion);

        return $discussion;
    }

    public function update(Discussion $discussion, array $data): Discussion
    {
        $discussion->update($data);

        return $discussion;
    }
}
