<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** تقدّم المشاريع يعيد آخر نشاط لكل طالب (آخر استخدام لتوكن دخوله) */
class ProgressLastActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_includes_student_last_activity(): void
    {
        $term = DB::table('academic_terms')->insertGetId(['name' => 'T1', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'D']);
        $spec = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'S', 'degree' => 'bachelor']);
        $sup = User::factory()->create(['role' => RoleEnum::Supervisor]);
        $student = User::factory()->create();
        $team = DB::table('teams')->insertGetId(['name' => 'A', 'supervisor_id' => $sup->id, 'leader_id' => $student->id, 'specialization_id' => $spec, 'term_id' => $term]);
        DB::table('team_members')->insert(['team_id' => $team, 'student_id' => $student->id]);
        $student->createToken('t')->accessToken->forceFill(['last_used_at' => '2026-10-14 10:00:00'])->save();

        $res = $this->actingAs($sup)->getJson('/api/progress')->assertOk()->json();
        $this->assertStringStartsWith('2026-10-14', (string) $res[0]['team']['members'][0]['student']['tokens_max_last_used_at']);
    }
}
