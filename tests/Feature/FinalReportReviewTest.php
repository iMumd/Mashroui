<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinalReportReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_is_archived_only_after_supervisor_then_committee_approve_the_final_report(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);
        $committee = User::factory()->create(['role' => RoleEnum::Committee]);
        $sup = User::factory()->create(['role' => RoleEnum::Supervisor]);
        $otherSup = User::factory()->create(['role' => RoleEnum::Supervisor]);
        $student = User::factory()->create();

        $term = DB::table('academic_terms')->insertGetId(['name' => 'T1', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'D']);
        $spec = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'S', 'degree' => 'bachelor']);
        $team = DB::table('teams')->insertGetId(['name' => 'A', 'supervisor_id' => $sup->id, 'leader_id' => $student->id, 'specialization_id' => $spec, 'term_id' => $term]);
        DB::table('team_members')->insert(['team_id' => $team, 'student_id' => $student->id]);
        $project = DB::table('projects')->insertGetId(['team_id' => $team, 'supervisor_id' => $sup->id, 'name' => 'P', 'department_id' => $dept, 'specialization_id' => $spec, 'term_id' => $term, 'status' => 'in_progress']);
        $report = DB::table('final_reports')->insertGetId(['project_id' => $project, 'pdf_path' => 'final_reports/x.pdf', 'uploaded_by' => $student->id, 'created_at' => now()]);
        $status = fn () => DB::table('final_reports')->where('id', $report)->value('review_status');
        $projectStatus = fn () => DB::table('projects')->where('id', $project)->value('status');

        // الأرشفة المباشرة ممنوعة قبل الاعتماد
        $this->actingAs($admin)->postJson("/api/projects/{$project}/complete")->assertUnprocessable();

        // المرحلة 1: المشرف فقط (لا اللجنة ولا مشرف آخر)
        $this->assertSame('pending_supervisor', $status());
        $this->actingAs($committee)->postJson("/api/final-reports/{$report}/review", ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($otherSup)->postJson("/api/final-reports/{$report}/review", ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($sup)->postJson("/api/final-reports/{$report}/review", ['decision' => 'reject'])->assertUnprocessable(); // الرفض بلا سبب
        $this->actingAs($sup)->postJson("/api/final-reports/{$report}/review", ['decision' => 'approve'])
            ->assertOk()->assertJsonPath('data.review_status', 'pending_committee');
        $this->assertSame('in_progress', $projectStatus());

        // المرحلة 2: لجنة الإشراف (المشرف لا يعتمد) ← المشروع يُؤرشف
        $this->actingAs($sup)->postJson("/api/final-reports/{$report}/review", ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($committee)->postJson("/api/final-reports/{$report}/review", ['decision' => 'approve'])
            ->assertOk()->assertJsonPath('data.review_status', 'approved')->assertJsonPath('data.committee_reviewer.id', $committee->id);
        $this->assertSame('completed', $projectStatus());

        // لا مراجعة بعد الاعتماد
        $this->actingAs($committee)->postJson("/api/final-reports/{$report}/review", ['decision' => 'reject', 'note' => 'x'])->assertUnprocessable();

        // تقرير جديد مرفوض من اللجنة بسبب: لا أرشفة، والملاحظة محفوظة
        $r2 = DB::table('final_reports')->insertGetId(['project_id' => $project, 'pdf_path' => 'final_reports/y.pdf', 'uploaded_by' => $student->id, 'review_status' => 'pending_committee', 'created_at' => now()]);
        $this->actingAs($admin)->postJson("/api/final-reports/{$r2}/review", ['decision' => 'reject', 'note' => 'أكمل الفصل الخامس'])
            ->assertOk()->assertJsonPath('data.review_status', 'rejected')->assertJsonPath('data.review_note', 'أكمل الفصل الخامس');
    }
}
