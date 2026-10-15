<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinalReportCoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_uploads_cover_with_final_report_and_it_shows_in_public_archive(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $sup = User::factory()->create(['role' => RoleEnum::Supervisor]);
        $student = User::factory()->create();
        $term = DB::table('academic_terms')->insertGetId(['name' => 'T1', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'D']);
        $spec = DB::table('specializations')->insertGetId(['department_id' => $dept, 'name' => 'S', 'degree' => 'bachelor']);
        $team = DB::table('teams')->insertGetId(['name' => 'A', 'supervisor_id' => $sup->id, 'leader_id' => $student->id, 'specialization_id' => $spec, 'term_id' => $term]);
        DB::table('team_members')->insert(['team_id' => $team, 'student_id' => $student->id]);
        $project = DB::table('projects')->insertGetId(['team_id' => $team, 'supervisor_id' => $sup->id, 'name' => 'P', 'department_id' => $dept, 'specialization_id' => $spec, 'term_id' => $term, 'status' => 'in_progress']);

        $payload = [
            'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
            'video_url' => 'https://www.youtube.com/watch?v=TLkA0RELQ1g',
        ];

        // الصورة ليست صورة ← مرفوضة
        $this->actingAs($student)->post("/api/projects/{$project}/final-reports", $payload + ['cover' => UploadedFile::fake()->create('x.pdf', 10)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('cover');

        // التقرير مع صورة الغلاف ← تُحفظ على قرص public ويُعاد رابطها
        $res = $this->actingAs($student)->post("/api/projects/{$project}/final-reports", $payload + ['cover' => UploadedFile::fake()->image('cover.jpg', 1600, 1000)], ['Accept' => 'application/json'])
            ->assertCreated();
        $path = DB::table('final_reports')->where('id', $res->json('id'))->value('cover_path');
        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith($path, $res->json('cover_url'));

        // بلا صورة ← مسموح (اختيارية)
        $this->actingAs($student)->post("/api/projects/{$project}/final-reports", $payload, ['Accept' => 'application/json'])->assertCreated();

        // المعرض العام يعرض آخر صورة غلاف للمشروع المؤرشف
        DB::table('projects')->where('id', $project)->update(['status' => 'completed', 'completed_at' => now()]);
        $this->getJson("/api/projects/public-archive/{$project}")->assertOk()->assertJsonPath('video_url', $payload['video_url']);
        $this->assertStringEndsWith($path, $this->getJson("/api/projects/public-archive/{$project}")->json('cover_url'));
    }
}
