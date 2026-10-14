<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComposeTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_share_templates_and_only_owner_or_admin_edits_and_deletes(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);
        $committee = User::factory()->create(['role' => RoleEnum::Committee]);
        $other = User::factory()->create(['role' => RoleEnum::Committee]);
        $student = User::factory()->create(['role' => RoleEnum::Student]);

        $id = $this->actingAs($committee)->postJson('/api/compose-templates', ['kind' => 'task', 'title' => 'تسليم الفصل الأول', 'body' => 'ارفع الملف'])
            ->assertCreated()->json('id');
        $this->actingAs($admin)->postJson('/api/compose-templates', ['kind' => 'announcement', 'body' => 'تنبيه'])->assertCreated();

        // مشتركة بين الإدارة واللجنة، وتُفلتر بالنوع
        $this->actingAs($admin)->getJson('/api/compose-templates?kind=task')->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'تسليم الفصل الأول');
        $this->actingAs($other)->getJson('/api/compose-templates')->assertOk()->assertJsonCount(2);
        $this->actingAs($student)->getJson('/api/compose-templates')->assertForbidden();
        $this->actingAs($committee)->postJson('/api/compose-templates', ['kind' => 'task'])->assertUnprocessable();

        // التعديل لصاحب القالب أو الإدارة، والنوع ثابت
        $this->actingAs($other)->putJson("/api/compose-templates/{$id}", ['title' => 'x'])->assertForbidden();
        $this->actingAs($committee)->putJson("/api/compose-templates/{$id}", ['title' => 'تسليم الفصل الثاني', 'body' => 'ارفع PDF'])
            ->assertOk()->assertJsonPath('title', 'تسليم الفصل الثاني')->assertJsonPath('body', 'ارفع PDF');
        $this->actingAs($admin)->putJson("/api/compose-templates/{$id}", ['title' => 'معدّل من الإدارة'])->assertOk();
        $this->actingAs($committee)->putJson("/api/compose-templates/{$id}", ['kind' => 'file', 'title' => 'y'])->assertUnprocessable();

        $this->actingAs($other)->deleteJson("/api/compose-templates/{$id}")->assertForbidden();
        $this->actingAs($committee)->deleteJson("/api/compose-templates/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('compose_templates', ['id' => $id]);
    }

    public function test_template_files_upload_download_replace_and_cleanup(): void
    {
        Storage::fake();
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);
        $committee = User::factory()->create(['role' => RoleEnum::Committee]);
        $supervisor = User::factory()->create(['role' => RoleEnum::Supervisor]);

        $res = $this->actingAs($committee)->post('/api/compose-templates', [
            'kind' => 'file', 'title' => 'نموذج التقرير',
            'files' => [UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'), UploadedFile::fake()->create('cover.docx', 50)],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonCount(2, 'files')->assertJsonMissingPath('files.0.path');
        $id = $res->json('id');
        [$keep, $drop] = [$res->json('files.0.id'), $res->json('files.1.id')];

        $this->actingAs($admin)->get("/api/compose-templates/files/{$keep}/download")->assertOk();
        $this->actingAs($supervisor)->get("/api/compose-templates/files/{$keep}/download")->assertForbidden();

        // تعديل بمرفقات: POST + _method=PUT — حذف مرفق وإضافة آخر
        $this->actingAs($committee)->post("/api/compose-templates/{$id}", [
            '_method' => 'PUT', 'title' => 'نموذج التقرير المعدّل', 'remove_file_ids' => [$drop],
            'files' => [UploadedFile::fake()->create('appendix.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonCount(2, 'files')->assertJsonPath('files.1.name', 'appendix.pdf');
        $this->assertDatabaseMissing('compose_template_files', ['id' => $drop]);

        $this->actingAs($committee)->post("/api/compose-templates/{$id}", [
            '_method' => 'PUT', 'title' => 'x', 'files' => array_map(fn ($i) => UploadedFile::fake()->create("f{$i}.pdf", 1, 'application/pdf'), range(1, 4)),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->actingAs($committee)->deleteJson("/api/compose-templates/{$id}")->assertNoContent();
        $this->assertDatabaseCount('compose_template_files', 0);
        $this->assertSame([], Storage::allFiles("compose-templates/{$id}"));
    }

    public function test_supervisor_sees_and_manages_only_own_templates(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);
        $supervisor = User::factory()->create(['role' => RoleEnum::Supervisor]);
        $otherSupervisor = User::factory()->create(['role' => RoleEnum::Supervisor]);

        $this->actingAs($admin)->postJson('/api/compose-templates', ['kind' => 'task', 'title' => 'قالب الإدارة'])->assertCreated();
        $id = $this->actingAs($supervisor)->postJson('/api/compose-templates', ['kind' => 'task', 'title' => 'قالبي'])->assertCreated()->json('id');

        $this->actingAs($supervisor)->getJson('/api/compose-templates')->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'قالبي');
        // قوالب المشرف خاصة به ولا تظهر في مكتبة الإدارة واللجنة
        $this->actingAs($admin)->getJson('/api/compose-templates')->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'قالب الإدارة');

        $this->actingAs($otherSupervisor)->putJson("/api/compose-templates/{$id}", ['title' => 'x'])->assertForbidden();
        $this->actingAs($supervisor)->putJson("/api/compose-templates/{$id}", ['title' => 'قالبي المعدّل'])->assertOk()->assertJsonPath('title', 'قالبي المعدّل');
        $this->actingAs($supervisor)->deleteJson("/api/compose-templates/{$id}")->assertNoContent();
    }
}
