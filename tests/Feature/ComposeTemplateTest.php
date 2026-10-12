<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComposeTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_share_templates_and_only_owner_or_admin_deletes(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);
        $committee = User::factory()->create(['role' => RoleEnum::Committee]);
        $other = User::factory()->create(['role' => RoleEnum::Committee]);
        $supervisor = User::factory()->create(['role' => RoleEnum::Supervisor]);

        $id = $this->actingAs($committee)->postJson('/api/compose-templates', ['kind' => 'task', 'title' => 'تسليم الفصل الأول', 'body' => 'ارفع الملف'])
            ->assertCreated()->json('id');
        $this->actingAs($admin)->postJson('/api/compose-templates', ['kind' => 'announcement', 'body' => 'تنبيه'])->assertCreated();

        // مشتركة بين الإدارة واللجنة، وتُفلتر بالنوع
        $this->actingAs($admin)->getJson('/api/compose-templates?kind=task')->assertOk()->assertJsonCount(1)->assertJsonPath('0.title', 'تسليم الفصل الأول');
        $this->actingAs($other)->getJson('/api/compose-templates')->assertOk()->assertJsonCount(2);
        $this->actingAs($supervisor)->getJson('/api/compose-templates')->assertForbidden();
        $this->actingAs($committee)->postJson('/api/compose-templates', ['kind' => 'task'])->assertUnprocessable();

        $this->actingAs($other)->deleteJson("/api/compose-templates/{$id}")->assertForbidden();
        $this->actingAs($committee)->deleteJson("/api/compose-templates/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('compose_templates', ['id' => $id]);
    }
}
