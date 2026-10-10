<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\EvaluationForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EvaluationFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_forms_are_seeded_editable_and_final_grade_combines_supervisor_and_examiners(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);
        $committee = User::factory()->create(['role' => RoleEnum::Committee]);
        $sup = User::factory()->create(['role' => RoleEnum::Supervisor]);
        $otherSup = User::factory()->create(['role' => RoleEnum::Supervisor]);
        [$s1, $s2] = [User::factory()->create(), User::factory()->create()];

        $term = DB::table('academic_terms')->insertGetId(['name' => 'T1', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'D']);
        $spec = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'S', 'degree' => 'bachelor']);
        $team = DB::table('teams')->insertGetId(['name' => 'A', 'supervisor_id' => $sup->id, 'specialization_id' => $spec, 'term_id' => $term]);
        DB::table('team_members')->insert([['team_id' => $team, 'student_id' => $s1->id], ['team_id' => $team, 'student_id' => $s2->id]]);

        // النماذج الثلاثة منقولة من ملفات الكلية: مشرف 10×5، مناقشين 6×5 مشروع + 4×5 طالب
        $forms = EvaluationForm::all()->keyBy('key');
        $this->assertSame(['supervisor', 'examiner_bachelor', 'examiner_diploma'], $forms->keys()->all());
        $this->assertSame(50, $forms['supervisor']->maxPerStudent());
        $this->assertSame(50, $forms['examiner_bachelor']->maxPerStudent());

        // ورقة المجموعة: الإدارة والمشرف يريان نموذج المشرف + مناقشين البكالوريوس (المشرف يعدّل نموذجه فقط)
        $sheet = $this->actingAs($admin)->getJson("/api/teams/{$team}/evaluation")->assertOk()->json();
        $this->assertSame(['supervisor', 'examiner_bachelor'], array_column($sheet['forms'], 'key'));
        $this->assertSame(['supervisor', 'examiner_bachelor'], array_column($this->actingAs($sup)->getJson("/api/teams/{$team}/evaluation")->json('forms'), 'key'));
        $this->actingAs($otherSup)->getJson("/api/teams/{$team}/evaluation")->assertForbidden();

        // المشرف يعطي s1 الدرجة الكاملة (50/50 → 50) و s2 نصفها (25 → 25)
        $supItems = collect($forms['supervisor']->sections[0]['items'])->pluck('id');
        $this->actingAs($sup)->putJson("/api/teams/{$team}/evaluation/{$forms['supervisor']->id}", ['answers' => ['students' => [
            $s1->id => $supItems->mapWithKeys(fn ($id) => [$id => 5])->all(),
            $s2->id => $supItems->mapWithKeys(fn ($id) => [$id => 2.5])->all(),
        ]]])->assertOk();
        // المشرف لا يعبّئ نموذج المناقشين
        $this->actingAs($sup)->putJson("/api/teams/{$team}/evaluation/{$forms['examiner_bachelor']->id}", ['answers' => []])->assertForbidden();

        // مناقشان: الأول الدرجة الكاملة، الثاني صفر للمشروع وكامل لبنود الطالب → المتوسط (50 + 20) / 2 = 35
        $ex = $forms['examiner_bachelor'];
        $proj = collect($ex->sections[0]['items'])->pluck('id');
        $stu = collect($ex->sections[1]['items'])->pluck('id');
        $fill = fn ($p, $s) => ['answers' => [
            'project' => $proj->mapWithKeys(fn ($id) => [$id => ['score' => $p, 'note' => 'ممتاز']])->all(),
            'students' => [$s1->id => $stu->mapWithKeys(fn ($id) => [$id => $s])->all(), $s2->id => $stu->mapWithKeys(fn ($id) => [$id => $s])->all()],
        ], 'nominate' => true, 'general_notes' => 'مشروع قوي'];
        $this->actingAs($admin)->putJson("/api/teams/{$team}/evaluation/{$ex->id}", $fill(5, 5))->assertOk();
        $this->actingAs($committee)->putJson("/api/teams/{$team}/evaluation/{$ex->id}", $fill(0, 5))->assertOk();

        $grade = fn ($student) => DB::table('evaluations')->where('student_id', $student->id)->first();
        $this->assertEquals(50, $grade($s1)->supervisor_score);
        $this->assertEquals(35, $grade($s1)->committee_score);
        $this->assertSame(85, (int) $grade($s1)->score);
        $this->assertSame(60, (int) $grade($s2)->score);

        // درجة أكبر من القصوى مرفوضة
        $this->actingAs($admin)->putJson("/api/teams/{$team}/evaluation/{$ex->id}", ['answers' => ['project' => [$proj[0] => ['score' => 9]]]])->assertUnprocessable();

        // تعديل النموذج (لجنة الإشراف): وزن المشرف 40 وطريقة "مقياس" → الدرجات تُعاد حسابها
        $form = $forms['supervisor']->toArray();
        $this->actingAs($committee)->putJson("/api/evaluation-forms/{$forms['supervisor']->id}", [...$form, 'weight' => 40, 'method' => 'scale'])
            ->assertOk()->assertJsonPath('method', 'scale');
        $this->assertEquals(40, $grade($s1)->supervisor_score);
        $this->assertSame(75, (int) $grade($s1)->score);

        // المشرف لا يعدّل النماذج
        $this->actingAs($sup)->putJson("/api/evaluation-forms/{$forms['supervisor']->id}", $form)->assertForbidden();

        // النتائج تعرض الجزأين
        $this->actingAs($admin)->getJson('/api/evaluations')->assertOk()->assertJsonPath('0.committee_score', '35.00');

        // نموذج مناقشين إضافي للبكالوريوس (وزن 10، بند طالب واحد من 10) — يُضاف لجزء اللجنة
        $extra = ['kind' => 'examiner', 'degree' => 'bachelor', 'title' => 'العرض العملي', 'method' => 'points', 'weight' => 10,
            'sections' => [['id' => 's1', 'title' => 'العرض', 'scope' => 'student', 'items' => [['id' => 'i1', 'text' => 'جودة العرض', 'max' => 10]]]]];
        $this->actingAs($sup)->postJson('/api/evaluation-forms', $extra)->assertForbidden();
        $new = $this->actingAs($committee)->postJson('/api/evaluation-forms', $extra)->assertCreated()->json();
        $this->assertSame(35.0, (float) $grade($s1)->committee_score);
        $this->assertContains($new['id'], array_column($this->actingAs($admin)->getJson("/api/teams/{$team}/evaluation")->json('forms'), 'id'));

        $this->actingAs($admin)->putJson("/api/teams/{$team}/evaluation/{$new['id']}", ['answers' => ['students' => [$s1->id => ['i1' => 10]]]])->assertOk();
        $this->assertEquals(45, $grade($s1)->committee_score);
        $this->assertSame(85, (int) $grade($s1)->score);

        // الحذف ممنوع بعد التعبئة، ومسموح لنموذج لم يُعبّأ
        $this->actingAs($admin)->deleteJson("/api/evaluation-forms/{$new['id']}")->assertUnprocessable();
        $empty = $this->actingAs($admin)->postJson('/api/evaluation-forms', [...$extra, 'degree' => null])->assertCreated()->json('id');
        $this->actingAs($admin)->deleteJson("/api/evaluation-forms/{$empty}")->assertNoContent();
        $this->assertDatabaseMissing('evaluation_forms', ['id' => $empty]);

        // مشرف آخر مناقش في مناقشة منشورة: يرى المجموعة ونماذج المناقشين وعلامة اللجنة للاطلاع فقط
        $project = DB::table('projects')->insertGetId(['team_id' => $team, 'supervisor_id' => $sup->id, 'name' => 'P', 'department_id' => $dept, 'specialization_id' => $spec, 'term_id' => $term]);
        $disc = ['project_id' => $project, 'supervisor_id' => $sup->id, 'place' => 'E504', 'discussion_date' => '2027-01-24', 'discussion_time' => '09:00', 'committee' => "{$otherSup->name}، {$committee->name} | ملاحظة", 'status' => 'pending', 'term_id' => $term];
        DB::table('discussions')->insert($disc);
        $this->actingAs($otherSup)->getJson("/api/teams/{$team}/evaluation")->assertForbidden(); // غير منشورة بعد
        DB::table('discussions')->update(['status' => 'confirmed']);

        $this->assertSame([$team], array_column($this->actingAs($otherSup)->getJson('/api/evaluations/examining')->assertOk()->json(), 'id'));
        $this->assertSame([], $this->actingAs($sup)->getJson('/api/evaluations/examining')->json());
        $exSheet = $this->actingAs($otherSup)->getJson("/api/teams/{$team}/evaluation")->assertOk()->json();
        $this->assertSame(['examiner_bachelor', $new['key']], array_column($exSheet['forms'], 'key'));
        $this->assertSame([], $exSheet['editable']);
        $this->assertNotEmpty($exSheet['submissions']);
        $this->assertSame(['student_id', 'committee_score'], array_keys($exSheet['results'][0]));
        $this->actingAs($otherSup)->putJson("/api/teams/{$team}/evaluation/{$ex->id}", $fill(5, 5))->assertForbidden();

        // مشرف المجموعة يرى علامة اللجنة أيضًا، ويعدّل نموذجه فقط
        $own = $this->actingAs($sup)->getJson("/api/teams/{$team}/evaluation")->json();
        $this->assertSame([$forms['supervisor']->id], $own['editable']);
        $this->assertContains('examiner_bachelor', array_column($own['forms'], 'key'));
    }
}
