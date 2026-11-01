<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** التراجع خطوة واحدة عن استيراد الفرق: يحذف ما أُنشئ (طلاب + فرق + مشاريع) ويعلّم الدفعة reverted */
class ImportRevertTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_import_confirm_then_revert_removes_created_entities(): void
    {
        $termId = DB::table('academic_terms')->insertGetId(['name' => 'T', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'قسم']);
        $spec = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'برمجيات', 'degree' => 'diploma']);
        $sup = User::factory()->create(['role' => RoleEnum::Supervisor, 'name' => 'د. مشرف', 'term_id' => $termId, 'email' => 'sup@ucas.edu.ps']);
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);

        $row = fn ($n, $num, $w) => [
            'name' => $n, 'email' => $num.'@smail.ucas.edu.ps', 'university_number' => $num, 'whatsapp' => $w,
            'specialization' => 'برمجيات', 'specialization_id' => $spec, 'section' => '1',
            'supervisor' => 'د. مشرف', 'supervisor_id' => $sup->id,
        ];
        $rows = [$row('طالب أ', '900101', '970599111222'), $row('طالب ب', '900102', '970599111333')];

        $this->actingAs($admin)->postJson('/api/teams/import/confirm', ['term_id' => $termId, 'rows' => $rows, 'filename' => 'teams.xlsx'])
            ->assertCreated();

        $this->assertSame(2, User::where('role', RoleEnum::Student)->where('term_id', $termId)->count());
        $this->assertSame(1, Team::where('term_id', $termId)->count());
        $this->assertSame(1, Project::where('term_id', $termId)->count());
        $this->assertDatabaseHas('import_batches', ['kind' => 'teams', 'status' => 'applied']);

        $this->actingAs($admin)->postJson('/api/teams/import/revert', ['term_id' => $termId])
            ->assertOk()->assertJsonPath('batch.status', 'reverted');

        $this->assertSame(0, User::withTrashed()->where('role', RoleEnum::Student)->where('term_id', $termId)->count());
        $this->assertSame(0, Team::withTrashed()->where('term_id', $termId)->count());
        $this->assertSame(0, Project::where('term_id', $termId)->count());
    }

    public function test_revert_without_applied_batch_fails(): void
    {
        DB::table('academic_terms')->insertGetId(['name' => 'T', 'is_current' => true]);
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);

        $this->actingAs($admin)->postJson('/api/teams/import/revert')->assertStatus(422);
    }
}
