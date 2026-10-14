<?php

namespace App\Services;

use App\Enums\NotificationChannelEnum;
use App\Events\MeetingCreated;
use App\Models\Meeting;
use App\Models\MeetingReminder;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac\TeamMembership;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class MeetingService
{
    /**
     * وقت الاجتماع يوصل كنص محلي (فلسطين) بدون منطقة زمنية — لازم نفسّره صراحة كـ Asia/Hebron
     * ونحوّله لـ UTC قبل التخزين، وإلا بينخزن كأنه UTC ويطلع مزاح عدة ساعات عند العرض.
     */
    private const LOCAL_TIMEZONE = 'Asia/Hebron';

    public function create(array $data, User $creator): Meeting
    {
        $team = Team::findOrFail($data['team_id']);

        // مشرف الفريق أو أي طالب فيه
        if ($creator->id !== $team->supervisor_id && ! app(TeamMembership::class)->isStudentOf($creator, $team)) {
            throw ValidationException::withMessages(['team_id' => 'هذا الفريق مش تبعك.']);
        }

        $scheduledAt = Carbon::parse($data['scheduled_at'], self::LOCAL_TIMEZONE)->utc();

        $meeting = Meeting::create([
            'team_id' => $team->id,
            'title' => $data['title'],
            'scheduled_at' => $scheduledAt,
            'google_meet_link' => $data['google_meet_link'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $creator->id,
        ]);

        // تذكير واحد فقط، قبل الاجتماع بـ24 ساعة بالضبط — بدون تذكيرات إضافية متلاحقة
        foreach ([NotificationChannelEnum::Email, NotificationChannelEnum::Whatsapp] as $channel) {
            MeetingReminder::create([
                'meeting_id' => $meeting->id,
                'remind_at' => $meeting->scheduled_at->clone()->subDay(),
                'channel' => $channel,
            ]);
        }

        MeetingCreated::dispatch($meeting);

        return $meeting;
    }
}
