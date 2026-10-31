<?php

namespace Tests\Feature;

use App\Enums\ProjectStatusEnum;
use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * المشاريع العامة والطاقم:
 * - العرض العام لا يكشف تفاصيل التقييم (نجوم المناقشين).
 * - معرض الواجهة يعرض كل المشاريع المميّزة من الإدارة (is_featured) بغضّ النظر عن الترشيح.
 * - نجوم ترشيح المناقشين تظهر للطاقم فقط عبر /progress.
 */
class PublicProjectArchiveTest extends TestCase
{
    use RefreshDatabase;

    /** يبني مشروعًا مكتملًا ويُرجع [project_id, team_id, spec_id] */
    private function makeCompletedProject(bool $featured = true): array
    {
        $term = DB::table('academic_terms')->insertGetId(['name' => 'T1', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'D']);
        $spec = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'S', 'degree' => 'bachelor']);
        $sup = User::factory()->create(['role' => RoleEnum::Supervisor]);
        $leader = User::factory()->create();
        $team = DB::table('teams')->insertGetId(['name' => 'A', 'supervisor_id' => $sup->id, 'leader_id' => $leader->id, 'specialization_id' => $spec, 'term_id' => $term]);
        $project = DB::table('projects')->insertGetId([
            'team_id' => $team, 'supervisor_id' => $sup->id, 'name' => 'P', 'department_id' => $dept,
            'specialization_id' => $spec, 'term_id' => $term, 'status' => ProjectStatusEnum::Completed->value,
            'is_featured' => $featured, 'completed_at' => now(),
        ]);

        return [$project, $team, $spec];
    }

    /** يسجّل تعبئة نموذج مناقشين لمجموعة مع قيمة الترشيح */
    private function addExaminerSubmission(int $teamId, bool $nominate): void
    {
        $formId = DB::table('evaluation_forms')->where('kind', 'examiner')->where('degree', 'bachelor')->value('id');
        $evaluator = User::factory()->create(['role' => RoleEnum::Committee]);
        DB::table('evaluation_submissions')->insert([
            'form_id' => $formId, 'team_id' => $teamId, 'evaluator_id' => $evaluator->id,
            'answers' => json_encode(['project' => [], 'students' => []]), 'nominate' => $nominate,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_public_project_payload_hides_rating_details(): void
    {
        [$project, $team] = $this->makeCompletedProject();
        $this->addExaminerSubmission($team, nominate: true);

        // معرض الواجهة: المشروع ظاهر لكن بلا تفاصيل تقييم (نجوم/حالة تمييز)
        $this->getJson('/api/projects/featured')
            ->assertOk()
            ->assertJsonPath('data.0.id', $project)
            ->assertJsonMissingPath('data.0.stars')
            ->assertJsonMissingPath('data.0.featured');

        $this->getJson("/api/projects/public-archive/{$project}")
            ->assertOk()
            ->assertJsonMissingPath('stars')
            ->assertJsonMissingPath('featured');
    }

    public function test_public_archive_lists_only_featured_projects(): void
    {
        [$featured] = $this->makeCompletedProject(featured: true);
        [$plain] = $this->makeCompletedProject(featured: false);

        $this->getJson('/api/projects/public-archive')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $featured);

        // المشروع المكتمل غير المميّز غير متاح في العرض العام
        $this->getJson("/api/projects/public-archive/{$plain}")->assertNotFound();
    }

    public function test_front_gallery_lists_all_featured_projects(): void
    {
        // مميّز ورشّحه مناقش
        [$nominated, $nominatedTeam] = $this->makeCompletedProject(featured: true);
        $this->addExaminerSubmission($nominatedTeam, nominate: true);

        // مميّز بلا ترشيح من المناقشين — يظهر في المعرض كذلك
        [$plainFeatured, $plainTeam] = $this->makeCompletedProject(featured: true);
        $this->addExaminerSubmission($plainTeam, nominate: false);

        // غير مميّز — لا يظهر في المعرض
        $this->makeCompletedProject(featured: false);

        $this->getJson('/api/projects/featured')
            ->assertOk()
            ->assertJsonPath('total', 2);

        $ids = collect($this->getJson('/api/projects/featured')->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($nominated));
        $this->assertTrue($ids->contains($plainFeatured));
    }

    public function test_staff_progress_exposes_discussant_nomination_stars(): void
    {
        [, $team] = $this->makeCompletedProject();
        $this->addExaminerSubmission($team, nominate: true);
        $this->addExaminerSubmission($team, nominate: false);

        $committee = User::factory()->create(['role' => RoleEnum::Committee]);

        $this->actingAs($committee)
            ->getJson('/api/progress')
            ->assertOk()
            ->assertJsonPath('0.team.project.nomination_stars.nominations', 1)
            ->assertJsonPath('0.team.project.nomination_stars.discussants', 2);
    }
}
