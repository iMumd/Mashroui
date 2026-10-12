<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** عضو لجنة الإشراف المرتبط بتخصص يرى مجموعات ومشاريع وطلاب ومشرفي تخصصه فقط */
class CommitteeSpecializationScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_committee_member_sees_only_his_specialization_data(): void
    {
        $term = DB::table('academic_terms')->insertGetId(['name' => 'T1', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'D']);
        $web = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'Web', 'degree' => 'diploma']);
        $se = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'SE', 'degree' => 'bachelor']);

        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);
        $webCommittee = User::factory()->create(['role' => RoleEnum::Committee, 'specialization_id' => $web]);
        $freeCommittee = User::factory()->create(['role' => RoleEnum::Committee]);
        $supWeb = User::factory()->create(['role' => RoleEnum::Supervisor, 'specialization_id' => $web]);
        $supSe = User::factory()->create(['role' => RoleEnum::Supervisor, 'specialization_id' => $se]);
        $s1 = User::factory()->create(['role' => RoleEnum::Student, 'specialization_id' => $web, 'term_id' => $term]);
        $s2 = User::factory()->create(['role' => RoleEnum::Student, 'specialization_id' => $se, 'term_id' => $term]);

        $tWeb = DB::table('teams')->insertGetId(['name' => 'A', 'supervisor_id' => $supWeb->id, 'leader_id' => $s1->id, 'specialization_id' => $web, 'term_id' => $term]);
        $tSe = DB::table('teams')->insertGetId(['name' => 'B', 'supervisor_id' => $supSe->id, 'leader_id' => $s2->id, 'specialization_id' => $se, 'term_id' => $term]);
        DB::table('team_members')->insert([['team_id' => $tWeb, 'student_id' => $s1->id], ['team_id' => $tSe, 'student_id' => $s2->id]]);

        // المجموعات والتقدّم
        $this->actingAs($webCommittee)->getJson('/api/teams')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $tWeb);
        $this->assertSame([$tWeb], collect($this->actingAs($webCommittee)->getJson('/api/progress')->json())->pluck('team.id')->all());

        // الطلاب والمشرفون
        $this->assertSame([$s1->id], collect($this->actingAs($webCommittee)->getJson('/api/users?role=student')->json('data'))->pluck('id')->all());
        $this->assertSame([$supWeb->id], collect($this->actingAs($webCommittee)->getJson('/api/users?role=supervisor')->json('data'))->pluck('id')->all());

        // بلا تخصص والإدارة العامة: الكل
        $this->actingAs($freeCommittee)->getJson('/api/teams')->assertJsonCount(2, 'data');
        $this->actingAs($admin)->getJson('/api/teams')->assertJsonCount(2, 'data');
    }
}
