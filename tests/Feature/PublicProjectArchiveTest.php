<?php

namespace Tests\Feature;

use App\Enums\ProjectStatusEnum;
use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** المشاريع العامة: نجوم ترشيح المناقشين (nominate) تظهر في المخرجات العامة */
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

    public function test_public_project_payload_exposes_discussant_nomination_stars(): void
    {
        [$project, $team] = $this->makeCompletedProject();
        $this->addExaminerSubmission($team, nominate: true);
        $this->addExaminerSubmission($team, nominate: false);

        $this->getJson('/api/projects/featured')
            ->assertOk()
            ->assertJsonPath('data.0.id', $project)
            ->assertJsonPath('data.0.featured', true)
            ->assertJsonPath('data.0.stars.nominations', 1)
            ->assertJsonPath('data.0.stars.discussants', 2);

        $this->getJson("/api/projects/public-archive/{$project}")
            ->assertOk()
            ->assertJsonPath('stars.nominations', 1)
            ->assertJsonPath('stars.discussants', 2);
    }

    public function test_project_without_nominations_reports_zero_stars(): void
    {
        [$project] = $this->makeCompletedProject();

        $this->getJson("/api/projects/public-archive/{$project}")
            ->assertOk()
            ->assertJsonPath('stars.nominations', 0)
            ->assertJsonPath('stars.discussants', 0);
    }
}
