<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** أي طالب في الفريق ينشئ اجتماعًا لفريقه (الاسم، الموعد، الرابط، الملاحظات)، ولا ينشئ لفريق آخر */
class StudentMeetingTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_member_creates_meeting_for_own_team_only(): void
    {
        $sup = User::factory()->create(['role' => RoleEnum::Supervisor]);
        $leader = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();

        $term = DB::table('academic_terms')->insertGetId(['name' => 'T1', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'D']);
        $spec = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'S', 'degree' => 'bachelor']);
        $team = DB::table('teams')->insertGetId(['name' => 'A', 'supervisor_id' => $sup->id, 'leader_id' => $leader->id, 'specialization_id' => $spec, 'term_id' => $term]);
        DB::table('team_members')->insert([['team_id' => $team, 'student_id' => $leader->id], ['team_id' => $team, 'student_id' => $member->id]]);
        $other = DB::table('teams')->insertGetId(['name' => 'B', 'supervisor_id' => $sup->id, 'leader_id' => $outsider->id, 'specialization_id' => $spec, 'term_id' => $term]);
        DB::table('team_members')->insert(['team_id' => $other, 'student_id' => $outsider->id]);

        $meeting = ['title' => 'اجتماع', 'scheduled_at' => now()->addDay()->toDateTimeString(), 'google_meet_link' => 'https://meet.google.com/abc-defg-hij', 'notes' => 'ملاحظة'];

        $this->actingAs($member)->postJson("/api/teams/{$team}/meetings", $meeting)->assertCreated();
        $this->assertDatabaseHas('meetings', ['team_id' => $team, 'title' => 'اجتماع', 'created_by' => $member->id]);
        $this->actingAs($outsider)->postJson("/api/teams/{$team}/meetings", $meeting)->assertForbidden();
        $this->actingAs($sup)->postJson("/api/teams/{$team}/meetings", $meeting)->assertCreated();
    }
}
