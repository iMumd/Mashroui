<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** تعيين أفضل مشروع تخرج في الصفحة الرئيسية (is_featured): لجنة الإشراف والإدارة العامة فقط، لا المشرف */
class ProjectFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_committee_and_super_admin_feature_projects(): void
    {
        $term = DB::table('academic_terms')->insertGetId(['name' => 'T1', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'D']);
        $spec = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'S', 'degree' => 'bachelor']);
        $sup = User::factory()->create(['role' => RoleEnum::Supervisor]);
        $committee = User::factory()->create(['role' => RoleEnum::Committee, 'specialization_id' => $spec]);
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);
        $leader = User::factory()->create();
        $team = DB::table('teams')->insertGetId(['name' => 'A', 'supervisor_id' => $sup->id, 'leader_id' => $leader->id, 'specialization_id' => $spec, 'term_id' => $term]);
        $project = DB::table('projects')->insertGetId(['team_id' => $team, 'supervisor_id' => $sup->id, 'name' => 'P', 'department_id' => $dept, 'specialization_id' => $spec, 'term_id' => $term]);
        $featured = fn () => (bool) DB::table('projects')->where('id', $project)->value('is_featured');

        $this->actingAs($sup)->patchJson("/api/projects/{$project}", ['is_featured' => true])->assertForbidden();
        $this->assertFalse($featured());

        $this->actingAs($committee)->patchJson("/api/projects/{$project}", ['is_featured' => true])->assertOk();
        $this->assertTrue($featured());

        $this->actingAs($admin)->patchJson("/api/projects/{$project}", ['is_featured' => false])->assertOk();
        $this->assertFalse($featured());

        // المشرف ما زال يعدّل اسم مشروعه
        $this->actingAs($sup)->patchJson("/api/projects/{$project}", ['name' => 'P2'])->assertOk();
    }
}
