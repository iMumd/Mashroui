<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** الطالب: يعلّق على مهام فريقه كلها، ويرفع الملفات ويغيّر الحالة في المهام المنتسب لها فقط */
class StudentTaskPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_notes_on_team_tasks_but_uploads_and_moves_only_assigned_ones(): void
    {
        Storage::fake();
        $sup = User::factory()->create(['role' => RoleEnum::Supervisor]);
        $me = User::factory()->create();
        $mate = User::factory()->create();
        $outsider = User::factory()->create();

        $term = DB::table('academic_terms')->insertGetId(['name' => 'T1', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'D']);
        $spec = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'S', 'degree' => 'bachelor']);
        $team = DB::table('teams')->insertGetId(['name' => 'A', 'supervisor_id' => $sup->id, 'leader_id' => $me->id, 'specialization_id' => $spec, 'term_id' => $term]);
        DB::table('team_members')->insert([['team_id' => $team, 'student_id' => $me->id], ['team_id' => $team, 'student_id' => $mate->id]]);
        $other = DB::table('teams')->insertGetId(['name' => 'B', 'supervisor_id' => $sup->id, 'leader_id' => $outsider->id, 'specialization_id' => $spec, 'term_id' => $term]);
        DB::table('team_members')->insert(['team_id' => $other, 'student_id' => $outsider->id]);

        $task = fn (array $assignees) => tap(DB::table('tasks')->insertGetId(['team_id' => $team, 'term_id' => $term, 'title' => 'T', 'status' => 'pending', 'created_by' => $sup->id]), function ($id) use ($assignees) {
            foreach ($assignees as $u) {
                DB::table('task_assignee')->insert(['task_id' => $id, 'user_id' => $u->id]);
            }
        });
        $mine = $task([$me]);
        $mates = $task([$mate]);
        $whole = $task([]); // بلا مكلّفين = لكل الفريق

        $h = ['Accept' => 'application/json'];
        $file = fn () => ['file' => UploadedFile::fake()->create('f.pdf', 10, 'application/pdf')];

        // الملاحظات: على مهامي ومهام زملائي، لا على مهام فريق آخر
        $this->actingAs($me)->postJson("/api/tasks/{$mine}/notes", ['note' => 'x'])->assertCreated();
        $this->actingAs($me)->postJson("/api/tasks/{$mates}/notes", ['note' => 'x'])->assertCreated();
        $this->actingAs($outsider)->postJson("/api/tasks/{$mine}/notes", ['note' => 'x'])->assertForbidden();

        // الحالة: المهام المنتسب لها فقط (ومهمة كل الفريق)
        $this->actingAs($me)->patchJson("/api/tasks/{$mine}/status", ['status' => 'in_progress'])->assertOk();
        $this->actingAs($me)->patchJson("/api/tasks/{$whole}/status", ['status' => 'in_progress'])->assertOk();
        $this->actingAs($me)->patchJson("/api/tasks/{$mates}/status", ['status' => 'in_progress'])->assertForbidden();

        // الملفات: المهام المنتسب لها فقط
        $this->actingAs($me)->post("/api/tasks/{$mine}/files", $file(), $h)->assertCreated();
        $this->actingAs($me)->post("/api/tasks/{$mates}/files", $file(), $h)->assertForbidden();
        $this->actingAs($outsider)->post("/api/tasks/{$whole}/files", $file(), $h)->assertForbidden();
    }
}
