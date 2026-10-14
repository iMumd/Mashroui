<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** أي عضو في الفريق (لا الحساب الأساسي فقط) يسلّم المقترح والتقرير النهائي، وطالب من فريق آخر لا */
class TeamMemberSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_team_member_submits_proposal_and_final_report(): void
    {
        Storage::fake();
        $sup = User::factory()->create(['role' => RoleEnum::Supervisor]);
        $leader = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();

        $term = DB::table('academic_terms')->insertGetId(['name' => 'T1', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'D']);
        $spec = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'S', 'degree' => 'bachelor']);
        $team = DB::table('teams')->insertGetId(['name' => 'A', 'supervisor_id' => $sup->id, 'leader_id' => $leader->id, 'specialization_id' => $spec, 'term_id' => $term]);
        DB::table('team_members')->insert([['team_id' => $team, 'student_id' => $leader->id], ['team_id' => $team, 'student_id' => $member->id]]);
        $project = DB::table('projects')->insertGetId(['team_id' => $team, 'supervisor_id' => $sup->id, 'name' => 'P', 'department_id' => $dept, 'specialization_id' => $spec, 'term_id' => $term, 'status' => 'in_progress']);
        $other = DB::table('teams')->insertGetId(['name' => 'B', 'supervisor_id' => $sup->id, 'leader_id' => $outsider->id, 'specialization_id' => $spec, 'term_id' => $term]);
        DB::table('team_members')->insert(['team_id' => $other, 'student_id' => $outsider->id]);

        $proposal = fn () => ['project_id' => $project, 'name' => 'مشروع', 'types' => ['ويب'], 'pdf' => UploadedFile::fake()->create('p.pdf', 10, 'application/pdf')];

        $this->actingAs($outsider)->post('/api/proposals', $proposal(), ['Accept' => 'application/json'])->assertUnprocessable();
        $this->actingAs($member)->post('/api/proposals', $proposal(), ['Accept' => 'application/json'])->assertCreated();

        // التقرير النهائي متاح دون انتظار اعتماد المقترح
        $report = fn () => ['file' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'), 'video_url' => 'https://www.youtube.com/watch?v=x'];
        $this->actingAs($outsider)->post("/api/projects/{$project}/final-reports", $report(), ['Accept' => 'application/json'])->assertStatus(422);
        $this->actingAs($member)->post("/api/projects/{$project}/final-reports", $report(), ['Accept' => 'application/json'])->assertCreated();
        $this->assertDatabaseHas('final_reports', ['project_id' => $project, 'uploaded_by' => $member->id]);
    }
}
