<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** لجنة الإشراف تستخدم أدوات "التكليفات والإعلانات" كالإدارة العامة، والمشرف لا */
class CommitteeToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_committee_sends_tasks_and_announcements_and_manages_only_its_own(): void
    {
        Storage::fake();

        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);
        $committee = User::factory()->create(['role' => RoleEnum::Committee]);
        $otherCommittee = User::factory()->create(['role' => RoleEnum::Committee]);
        $sup = User::factory()->create(['role' => RoleEnum::Supervisor]);
        $student = User::factory()->create();

        $term = DB::table('academic_terms')->insertGetId(['name' => 'T1', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'D']);
        $spec = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'S', 'degree' => 'diploma']);
        $team = DB::table('teams')->insertGetId(['name' => 'A', 'supervisor_id' => $sup->id, 'leader_id' => $student->id, 'specialization_id' => $spec, 'term_id' => $term]);
        DB::table('team_members')->insert(['team_id' => $team, 'student_id' => $student->id]);

        // مستلمو التكليفات: اللجنة تقرأ قائمة أعضاء اللجنة والمشرفين
        $this->actingAs($committee)->getJson('/api/users?role=committee')->assertOk()->assertJsonCount(2, 'data');

        // مهمة لعدة مجموعات + مهمة لمشرف
        $task = $this->actingAs($committee)->postJson('/api/tasks/batch', ['team_ids' => [$team], 'title' => 'تسليم الفصل الأول'])
            ->assertCreated()->json('data.0');
        $this->actingAs($committee)->postJson('/api/staff/tasks', ['user_id' => $sup->id, 'title' => 'متابعة المجموعات'])->assertCreated();
        $this->actingAs($sup)->postJson('/api/tasks/batch', ['team_ids' => [$team], 'title' => 'x'])->assertForbidden();
        $this->actingAs($sup)->postJson('/api/staff/tasks', ['user_id' => $committee->id, 'title' => 'x'])->assertForbidden();

        // منشئ المهمة يعلّق عليها ويرفق ملفًا
        $this->actingAs($committee)->postJson("/api/tasks/{$task['id']}/notes", ['note' => 'يرجى الالتزام بالموعد'])->assertCreated();
        $this->actingAs($committee)->post("/api/tasks/{$task['id']}/files", ['file' => UploadedFile::fake()->create('guide.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();

        // إعلان من اللجنة للمجموعات؛ تحذفه هي أو الإدارة العامة، لا عضو لجنة آخر
        $sent = $this->actingAs($committee)->postJson('/api/announcements/send', [
            'type' => 'announcement', 'audience' => 'groups', 'team_ids' => [$team], 'title' => 'تنبيه', 'body' => 'الاجتماع غدًا',
        ])->assertCreated()->json();
        $this->assertCount(1, $this->actingAs($student)->getJson('/api/announcements')->json());
        $this->actingAs($sup)->postJson('/api/announcements/send', ['type' => 'announcement', 'audience' => 'groups', 'team_ids' => [$team], 'title' => 'x', 'body' => 'x'])->assertForbidden();

        $this->actingAs($otherCommittee)->deleteJson("/api/announcements/batch/{$sent['batch']}")->assertForbidden();
        $this->actingAs($committee)->deleteJson("/api/announcements/batch/{$sent['batch']}")->assertNoContent();

        $again = $this->actingAs($committee)->postJson('/api/announcements/send', [
            'type' => 'announcement', 'audience' => 'groups', 'team_ids' => [$team], 'title' => 'تنبيه 2', 'body' => '...',
        ])->json('batch');
        $this->actingAs($admin)->deleteJson("/api/announcements/batch/{$again}")->assertNoContent();
    }
}
