<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sends_announcements_and_files_on_platform_and_lists_them(): void
    {
        Storage::fake();

        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);
        $sup = User::factory()->create(['role' => RoleEnum::Supervisor]);
        $member = User::factory()->create(['role' => RoleEnum::Committee]);
        $other = User::factory()->create(['role' => RoleEnum::Committee]);
        $s1 = User::factory()->create();
        $s2 = User::factory()->create();

        $term = DB::table('academic_terms')->insertGetId(['name' => 'T1', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'D']);
        $spec = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'S', 'degree' => 'diploma']);
        $team = fn ($name) => DB::table('teams')->insertGetId(['name' => $name, 'supervisor_id' => $sup->id, 'specialization_id' => $spec, 'term_id' => $term]);
        [$t1, $t2] = [$team('A'), $team('B')];
        DB::table('team_members')->insert([['team_id' => $t1, 'student_id' => $s1->id], ['team_id' => $t2, 'student_id' => $s2->id]]);

        // إعلان لمجموعتين لنفس المشرف: صف لكل مجموعة، وإشعار واحد للمشرف وإشعار لكل طالب
        $this->actingAs($admin)->postJson('/api/announcements/send', [
            'type' => 'announcement', 'audience' => 'groups', 'team_ids' => [$t1, $t2],
            'title' => 'موعد التسليم', 'body' => 'التسليم الأحد', 'channels' => ['whatsapp'],
        ])->assertCreated()->assertJsonCount(2, 'recipients')->assertJsonPath('channels', ['platform', 'whatsapp']);

        $this->assertSame(2, DB::table('announcements')->count());
        $this->assertSame(1, DB::table('notifications')->where('user_id', $sup->id)->count());
        $this->assertSame(1, DB::table('notifications')->where('user_id', $s1->id)->count());
        $this->assertCount(1, $this->actingAs($sup)->getJson('/api/announcements')->assertOk()->json());
        $this->assertCount(1, $this->actingAs($s2)->getJson('/api/announcements')->json());

        // ملف لعضو لجنة: يصله وحده، ويحمّله، وغيره لا يحمّله
        $file = $this->actingAs($admin)->post('/api/announcements/send', [
            'type' => 'file', 'audience' => 'committee', 'user_ids' => [$member->id], 'title' => 'نموذج التقييم',
            'files' => [UploadedFile::fake()->create('form.pdf', 20, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertCreated()->json();
        $attachment = $file['attachments'][0]['id'];

        $this->assertCount(1, $this->actingAs($member)->getJson('/api/announcements')->json());
        $this->assertCount(0, $this->actingAs($other)->getJson('/api/announcements')->json());
        $this->actingAs($member)->get("/api/announcements/attachments/{$attachment}/download")->assertOk();
        $this->actingAs($s1)->get("/api/announcements/attachments/{$attachment}/download")->assertForbidden();

        // ملف بلا مرفق مرفوض، والطالب لا يرسل
        $this->actingAs($admin)->postJson('/api/announcements/send', ['type' => 'file', 'audience' => 'committee', 'user_ids' => [$member->id], 'title' => 'x'])->assertUnprocessable();
        $this->actingAs($s1)->postJson('/api/announcements/send', ['type' => 'announcement', 'audience' => 'groups', 'team_ids' => [$t1], 'body' => 'x'])->assertForbidden();

        // المرسَلة: مجمّعة بالإرسال، وفلاتر الخادم مستقلة
        $sent = fn ($q = []) => $this->actingAs($admin)->getJson('/api/announcements/sent?'.http_build_query($q))->assertOk()->json();
        $this->assertCount(2, $sent()['data']);
        $this->assertSame(['all' => 2, 'groups' => 1, 'committee' => 1, 'supervisors' => 0], $sent()['meta']['counts']);
        $this->assertCount(1, $sent(['type' => 'file'])['data']);
        $this->assertCount(1, $sent(['audience' => 'groups'])['data']);
        $this->assertCount(1, $sent(['supervisor_id' => $sup->id])['data']);
        $this->assertCount(1, $sent(['team_id' => $t2])['data']);
        $this->assertCount(1, $sent(['user_id' => $member->id])['data']);
        $this->assertCount(1, $sent(['q' => 'الأحد'])['data']);
        $this->assertSame('موعد التسليم', $sent(['audience' => 'groups'])['data'][0]['title']);

        // سحب الإرسال: يختفي من المرسَلة ومن صندوق المستلم، ويُحذف مرفقه
        $this->actingAs($admin)->deleteJson("/api/announcements/batch/{$file['batch']}")->assertNoContent();
        $this->assertCount(1, $sent()['data']);
        $this->assertCount(0, $this->actingAs($member)->getJson('/api/announcements')->json());
        $this->assertSame(0, DB::table('announcement_attachments')->count());
    }

    public function test_admin_rates_supervisors_with_stars_and_notes(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);
        [$a, $b] = [User::factory()->create(['role' => RoleEnum::Supervisor]), User::factory()->create(['role' => RoleEnum::Supervisor])];
        $committee = User::factory()->create(['role' => RoleEnum::Committee]);
        DB::table('academic_terms')->insert(['name' => 'T1', 'is_current' => true]);

        $sent = $this->actingAs($admin)->postJson('/api/announcements/ratings', [
            'ratings' => [['supervisor_id' => $a->id, 'rating' => 5, 'note' => 'تقدّم ممتاز'], ['supervisor_id' => $b->id, 'rating' => 2, 'note' => 'مجموعات متأخرة']],
            'general' => 'نرجو المتابعة قبل المناقشات',
        ])->assertCreated()->json();

        $this->assertSame([5, 2], array_column($sent['recipients'], 'rating'));
        $this->assertStringContainsString('نرجو المتابعة', $sent['recipients'][1]['note']);

        // كل مشرف يرى تقييمه فقط بنجومه، ويصله إشعار
        $inbox = $this->actingAs($b)->getJson('/api/announcements')->assertOk()->json();
        $this->assertCount(1, $inbox);
        $this->assertSame(2, $inbox[0]['rating']);
        $this->assertSame(1, DB::table('notifications')->where('user_id', $a->id)->count());

        // السجل: نوع rating، وفلتر المشرف
        $list = fn ($q) => $this->actingAs($admin)->getJson('/api/announcements/sent?'.http_build_query($q))->json('data');
        $this->assertCount(1, $list(['type' => 'rating']));
        $this->assertCount(0, $list(['type' => 'announcement']));
        $this->assertCount(1, $list(['type' => 'rating', 'supervisor_id' => $a->id]));

        // غير المشرف أو تكرار المشرف مرفوض، وغير الإدارة لا ترسل
        $this->actingAs($admin)->postJson('/api/announcements/ratings', ['ratings' => [['supervisor_id' => $committee->id, 'rating' => 3]]])->assertUnprocessable();
        $this->actingAs($admin)->postJson('/api/announcements/ratings', ['ratings' => [['supervisor_id' => $a->id, 'rating' => 3], ['supervisor_id' => $a->id, 'rating' => 4]]])->assertUnprocessable();
        $this->actingAs($admin)->postJson('/api/announcements/ratings', ['ratings' => [['supervisor_id' => $a->id, 'rating' => 6]]])->assertUnprocessable();
        $this->actingAs($committee)->postJson('/api/announcements/ratings', ['ratings' => [['supervisor_id' => $a->id, 'rating' => 3]]])->assertForbidden();
    }
}
