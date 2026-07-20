<?php

namespace App\Services;

use App\Enums\NotificationChannelEnum;
use App\Events\MeetingCreated;
use App\Models\Meeting;
use App\Models\MeetingReminder;
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

        MeetingReminder::create([
            'meeting_id' => $meeting->id,
            'remind_at' => $meeting->scheduled_at->clone()->subDay(),
            'channel' => NotificationChannelEnum::Email,
        ]);

        MeetingReminder::create([
            'meeting_id' => $meeting->id,
            'remind_at' => $meeting->scheduled_at->clone()->subHours(6),
            'channel' => NotificationChannelEnum::Email,
        ]);

        MeetingCreated::dispatch($meeting);

        return $meeting;
    }
}
