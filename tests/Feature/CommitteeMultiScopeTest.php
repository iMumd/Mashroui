<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** عضو لجنة الإشراف: حتى 5 تخصصات أو قسم كامل، ويرى مجموعات نطاقه فقط */
class CommitteeMultiScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_committee_scope_by_specializations_or_whole_department(): void
    {
        $term = DB::table('academic_terms')->insertGetId(['name' => 'T1', 'is_current' => true]);
        $it = DB::table('departments')->insertGetId(['name' => 'IT']);
        $eng = DB::table('departments')->insertGetId(['name' => 'ENG']);
        $web = DB::table('specializations')->insertGetId(['department_id' => $it, 'name' => 'Web', 'degree' => 'diploma']);
        $se = DB::table('specializations')->insertGetId(['department_id' => $it, 'name' => 'SE', 'degree' => 'bachelor']);
        $civil = DB::table('specializations')->insertGetId(['department_id' => $eng, 'name' => 'Civil', 'degree' => 'diploma']);
        $more = collect(range(1, 4))->map(fn ($i) => DB::table('specializations')->insertGetId(['department_id' => $eng, 'name' => "X$i", 'degree' => 'diploma']))->all();

        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);
        $sup = User::factory()->create(['role' => RoleEnum::Supervisor]);
        $teams = collect([$web, $se, $civil])->mapWithKeys(fn ($spec) => [$spec => DB::table('teams')->insertGetId([
            'name' => "T$spec", 'supervisor_id' => $sup->id, 'leader_id' => User::factory()->create(['role' => RoleEnum::Student, 'term_id' => $term])->id,
            'specialization_id' => $spec, 'term_id' => $term,
        ])]);

        // الحساب الجديد يُلزم بتغيير كلمة المرور؛ نتجاوزه هنا لاختبار النطاق
        $created = fn ($response) => tap(User::find($response->assertCreated()->json('user.id')))->update(['must_change_password' => false]);
        $make = fn (array $extra) => $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'C', 'email' => uniqid('c').'@ucas.edu.ps', 'role' => 'committee', ...$extra,
        ]);

        // أكثر من 5 تخصصات، أو تخصصات + قسم معًا: مرفوض
        $make(['specialization_ids' => [$web, $se, ...$more]])->assertUnprocessable()->assertJsonValidationErrors('specialization_ids');
        $make(['specialization_ids' => [$web], 'scope_department_id' => $it])->assertUnprocessable();

        // تخصصان: يرى مجموعتيهما فقط
        $two = $created($make(['specialization_ids' => [$web, $civil]]));
        $this->assertEqualsCanonicalizing([$web, $civil], $two->committeeSpecializationIds());
        $this->assertEqualsCanonicalizing([$teams[$web], $teams[$civil]], collect($this->actingAs($two)->getJson('/api/teams')->json('data'))->pluck('id')->all());

        // قسم كامل: كل تخصصات القسم
        $dept = $created($make(['scope_department_id' => $it]));
        $this->assertEqualsCanonicalizing([$teams[$web], $teams[$se]], collect($this->actingAs($dept)->getJson('/api/teams')->json('data'))->pluck('id')->all());

        // التعديل إلى بلا نطاق: يرى الكل
        $this->actingAs($admin)->patchJson("/api/users/{$two->id}", ['specialization_ids' => [], 'scope_department_id' => null])->assertOk()
            ->assertJsonPath('data.specialization_ids', []);
        $this->actingAs(User::find($two->id))->getJson('/api/teams')->assertJsonCount(3, 'data');
    }
}
