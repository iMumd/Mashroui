<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskBatchAssigneesTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_assigns_chosen_students_per_group_and_overview_filters_by_degree(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);
        $sup = User::factory()->create(['role' => RoleEnum::Supervisor]);
        [$a1, $a2, $b1, $outsider] = User::factory()->count(4)->create()->all();

        $term = DB::table('academic_terms')->insertGetId(['name' => 'T1', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'D']);
        $dip = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'Web', 'degree' => 'diploma']);
        $bac = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'SE', 'degree' => 'bachelor']);
        $teamA = DB::table('teams')->insertGetId(['name' => 'A', 'supervisor_id' => $sup->id, 'leader_id' => $a1->id, 'specialization_id' => $dip, 'term_id' => $term]);
        $teamB = DB::table('teams')->insertGetId(['name' => 'B', 'supervisor_id' => $sup->id, 'leader_id' => $b1->id, 'specialization_id' => $bac, 'term_id' => $term]);
        DB::table('team_members')->insert([
            ['team_id' => $teamA, 'student_id' => $a1->id], ['team_id' => $teamA, 'student_id' => $a2->id], ['team_id' => $teamB, 'student_id' => $b1->id],
        ]);

        // مكلّف من خارج المجموعات المحددة مرفوض
        $this->actingAs($admin)->postJson('/api/tasks/batch', ['team_ids' => [$teamA, $teamB], 'title' => 'x', 'assignee_ids' => [$outsider->id]])->assertUnprocessable();

        // A: الطالب المحدد فقط، B: بلا محدَّدين = كل أعضائها
        $tasks = collect($this->actingAs($admin)->postJson('/api/tasks/batch', ['team_ids' => [$teamA, $teamB], 'title' => 'الفصل الأول', 'assignee_ids' => [$a2->id]])
            ->assertCreated()->json('data'))->keyBy('team_id');
        $this->assertSame([$a2->id], DB::table('task_assignee')->where('task_id', $tasks[$teamA]['id'])->pluck('user_id')->all());
        $this->assertSame(0, DB::table('task_assignee')->where('task_id', $tasks[$teamB]['id'])->count());

        // فلاتر الدرجة والتخصص
        $this->actingAs($admin)->getJson('/api/tasks?degree=diploma')->assertJsonCount(1, 'data')->assertJsonPath('data.0.team_id', $teamA);
        $this->actingAs($admin)->getJson("/api/tasks?specialization_id={$bac}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.team_id', $teamB);
    }
}
