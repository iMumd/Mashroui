<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MeetingService
{
    public function create(array $data, User $creator): Meeting
    {
        $team = Team::findOrFail($data['team_id']);

        if ($creator->id !== $team->supervisor_id && $creator->id !== $team->leader_id) {
            throw ValidationException::withMessages(['team_id' => 'هذا الفريق مش تبعك.']);
        }

        $meeting = Meeting::create([
            'team_id' => $team->id,
            'title' => $data['title'],
            'scheduled_at' => $data['scheduled_at'],
            'google_meet_link' => $data['google_meet_link'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $creator->id,
        ]);

        $recipient = $creator->id === $team->supervisor_id ? $team->leader_id : $team->supervisor_id;

        Notification::create([
            'user_id' => $recipient,
            'type' => 'meeting',
            'message' => "تم تحديد اجتماع جديد: \"{$meeting->title}\".",
        ]);

        return $meeting;
    }
}
