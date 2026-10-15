<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_are_applied_on_the_server_and_counts_ignore_status(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);
        $supA = User::factory()->create(['role' => RoleEnum::Supervisor]);
        $supB = User::factory()->create(['role' => RoleEnum::Supervisor]);
        $student = User::factory()->create(['name' => 'سارة أحمد']);

        $term = DB::table('academic_terms')->insertGetId(['name' => 'T1', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'D']);
        $spec = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'S', 'degree' => 'diploma']);
        $teamA = DB::table('teams')->insertGetId(['name' => 'A', 'supervisor_id' => $supA->id, 'specialization_id' => $spec, 'term_id' => $term]);
        $teamB = DB::table('teams')->insertGetId(['name' => 'B', 'supervisor_id' => $supB->id, 'specialization_id' => $spec, 'term_id' => $term]);

        $task = fn ($team, $title, $status, $due) => DB::table('tasks')->insertGetId([
            'team_id' => $team, 'title' => $title, 'status' => $status, 'due_date' => $due, 'created_by' => $supA->id,
        ]);
        $late = $task($teamA, 'تقرير متأخر', 'pending', now()->subDays(3)->toDateString());
        $task($teamA, 'تصميم الواجهة', 'done', now()->subDays(3)->toDateString());
        $soon = $task($teamB, 'عرض تقديمي', 'in_progress', now()->addDays(2)->toDateString());
        $task($teamB, 'مراجعة', 'review', null);
        DB::table('task_assignee')->insert(['task_id' => $soon, 'user_id' => $student->id]);

        $ids = fn ($query) => collect($this->actingAs($admin)->getJson('/api/tasks?'.http_build_query($query))->assertOk()->json('data'))->pluck('id')->all();

        $this->assertCount(4, $ids([]));
        $this->assertSame([$late], $ids(['status' => 'late']));
        $this->assertCount(2, $ids(['team_id' => $teamA]));
        $this->assertCount(2, $ids(['supervisor_id' => $supB->id]));
        $this->assertSame([$soon], $ids(['due' => 'week']));
        $this->assertCount(1, $ids(['due' => 'none']));
        $this->assertSame([$soon], $ids(['q' => 'سارة']));

        $counts = $this->actingAs($admin)->getJson('/api/tasks?status=done&team_id='.$teamA)->json('meta.counts');
        $this->assertSame(['pending' => 1, 'in_progress' => 0, 'review' => 0, 'done' => 1, 'late' => 1, 'all' => 2], $counts);

        $this->actingAs($student)->getJson('/api/tasks')->assertForbidden();
    }

    public function test_admin_sends_tasks_to_committee_members_and_filters_them(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);
        $member = User::factory()->create(['role' => RoleEnum::Committee]);
        $other = User::factory()->create(['role' => RoleEnum::Committee]);
        $sup = User::factory()->create(['role' => RoleEnum::Supervisor]);

        $term = DB::table('academic_terms')->insertGetId(['name' => 'T1', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'D']);
        $spec = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'S', 'degree' => 'diploma']);
        $team = DB::table('teams')->insertGetId(['name' => 'A', 'supervisor_id' => $sup->id, 'specialization_id' => $spec, 'term_id' => $term]);
        DB::table('tasks')->insert(['team_id' => $team, 'title' => 'مهمة مجموعة', 'status' => 'pending', 'created_by' => $sup->id]);

        $id = $this->actingAs($admin)->postJson('/api/staff/tasks', ['user_id' => $member->id, 'title' => 'مراجعة المقترحات'])
            ->assertCreated()->json('data.id');
        $this->actingAs($admin)->postJson('/api/staff/tasks', ['user_id' => $other->id, 'title' => 'جدول المناقشات'])->assertCreated();
        $supTask = $this->actingAs($admin)->postJson('/api/staff/tasks', ['user_id' => $sup->id, 'title' => 'تقرير المشرف'])
            ->assertCreated()->json('data.id');
        $student = User::factory()->create();
        $this->actingAs($admin)->postJson('/api/staff/tasks', ['user_id' => $student->id, 'title' => 'x'])->assertUnprocessable();
        // لجنة الإشراف ترسل أيضًا (CommitteeToolsTest)؛ المشرف لا
        $this->actingAs($sup)->postJson('/api/staff/tasks', ['user_id' => $other->id, 'title' => 'x'])->assertForbidden();

        $count = fn ($query) => count($this->actingAs($admin)->getJson('/api/tasks?'.http_build_query($query))->assertOk()->json('data'));
        $this->assertSame(4, $count([]));
        $this->assertSame(2, $count(['audience' => 'committee']));
        $this->assertSame(1, $count(['audience' => 'supervisors']));
        $this->assertSame(2, $count(['audience' => 'groups'])); // مهمة المجموعة + مهمة المشرف نفسه
        $this->assertSame(2, $count(['supervisor_id' => $sup->id])); // مهام مجموعاته + مهامه هو
        $this->assertSame(1, $count(['team_id' => $team]));

        // المشرف يرى مهامه المسندة من الإدارة فقط مهما أرسل من فلاتر، ويحرّك حالتها ويضيف ملاحظة
        $supIds = collect($this->actingAs($sup)->getJson('/api/tasks?audience=groups')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$supTask], $supIds);
        $this->actingAs($sup)->patchJson("/api/tasks/{$supTask}/status", ['status' => 'review'])->assertOk();
        $this->actingAs($sup)->postJson("/api/tasks/{$supTask}/notes", ['note' => 'تم'])->assertSuccessful();
        $this->assertSame(1, $count(['audience' => 'committee', 'assignee_id' => $member->id]));
        $this->assertSame(1, $count(['team_id' => $team]));

        $this->assertNotNull(DB::table('notifications')->where('user_id', $member->id)->first());

        // عضو اللجنة يحرّك حالة مهمته
        $this->actingAs($member)->patchJson("/api/tasks/{$id}/status", ['status' => 'in_progress'])->assertOk();
        $this->assertSame('in_progress', DB::table('tasks')->where('id', $id)->value('status'));
        $this->actingAs($member)->patchJson("/api/tasks/{$id}/status", ['status' => 'review'])->assertOk();
        $this->actingAs($member)->postJson("/api/tasks/{$id}/notes", ['note' => 'بدأت'])->assertSuccessful();
        $this->actingAs($admin)->postJson("/api/tasks/{$id}/notes", ['note' => 'متابعة'])->assertSuccessful();
        $this->actingAs($other)->postJson("/api/tasks/{$id}/notes", ['note' => 'x'])->assertUnprocessable();
        // عضو لجنة آخر غير مكلّف لا يحرّكها
        $this->actingAs($other)->patchJson("/api/tasks/{$id}/status", ['status' => 'done'])->assertUnprocessable();
    }

    public function test_batch_task_for_groups_notifies_each_supervisor_once(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);
        $supA = User::factory()->create(['role' => RoleEnum::Supervisor]);
        $supB = User::factory()->create(['role' => RoleEnum::Supervisor]);

        $term = DB::table('academic_terms')->insertGetId(['name' => 'T1', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'D']);
        $spec = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'S', 'degree' => 'diploma']);
        $team = fn ($name, $sup) => DB::table('teams')->insertGetId(['name' => $name, 'supervisor_id' => $sup->id, 'specialization_id' => $spec, 'term_id' => $term]);
        $teams = [$team('A1', $supA), $team('A2', $supA), $team('B1', $supB)];

        $this->actingAs($admin)->postJson('/api/tasks/batch', ['team_ids' => $teams, 'title' => 'التقرير'])
            ->assertCreated()->assertJsonCount(3, 'data');

        $this->assertSame(3, DB::table('tasks')->count());
        $this->assertSame(1, DB::table('notifications')->where('user_id', $supA->id)->count());
        $this->assertStringContainsString('2 من مجموعاتك', DB::table('notifications')->where('user_id', $supA->id)->value('message'));
        $this->assertSame(1, DB::table('notifications')->where('user_id', $supB->id)->count());

        // مجموعة واحدة: يصل المشرف إشعار أيضًا، والإدارة تنشئ مهمة لمجموعة مفردة بلا رفض
        $this->actingAs($admin)->postJson("/api/teams/{$teams[2]}/tasks", ['title' => 'مفردة'])->assertCreated();
        $this->assertSame(2, DB::table('notifications')->where('user_id', $supB->id)->count());

        $this->actingAs($supA)->postJson('/api/tasks/batch', ['team_ids' => $teams, 'title' => 'x'])->assertForbidden();
    }
}
