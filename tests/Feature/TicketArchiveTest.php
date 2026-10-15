<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_archives_only_after_replying_and_can_unarchive_and_delete(): void
    {
        $committee = User::factory()->create(['role' => RoleEnum::Committee]);
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);
        $student = User::factory()->create(['role' => RoleEnum::Student]);

        $id = $this->actingAs($student)->postJson('/api/tickets', ['subject' => 'مشكلة', 'body' => 'تفاصيل'])->assertCreated()->json('id');

        // قبل رد الدعم: لا أرشفة، والطالب لا يؤرشف ولا يحذف
        $this->actingAs($committee)->postJson("/api/tickets/{$id}/archive")->assertForbidden();
        $this->actingAs($student)->deleteJson("/api/tickets/{$id}")->assertForbidden();

        // القائمة تكشف صاحب آخر رسالة ومقتطفها
        $this->actingAs($committee)->getJson('/api/tickets')->assertJsonPath('0.last_reply_user_id', $student->id)->assertJsonPath('0.last_reply_body', 'تفاصيل');

        $this->actingAs($committee)->postJson("/api/tickets/{$id}/replies", ['body' => 'تم'])->assertCreated();
        $this->actingAs($student)->postJson("/api/tickets/{$id}/archive")->assertForbidden();
        $this->actingAs($committee)->postJson("/api/tickets/{$id}/archive")
            ->assertOk()->assertJsonPath('status', 'closed')->assertJsonPath('archived_by', $committee->id);
        $this->actingAs($committee)->postJson("/api/tickets/{$id}/archive")->assertForbidden(); // مؤرشفة مسبقًا

        // الاسترجاع يبقيها مغلقة، وإعادة الفتح تُلغي الأرشفة
        $this->actingAs($admin)->postJson("/api/tickets/{$id}/unarchive")->assertOk()->assertJsonPath('archived_at', null)->assertJsonPath('status', 'closed');
        $this->actingAs($admin)->postJson("/api/tickets/{$id}/archive")->assertOk();
        $this->actingAs($admin)->postJson("/api/tickets/{$id}/reopen")->assertOk()->assertJsonPath('archived_at', null)->assertJsonPath('status', 'open');

        $this->actingAs($admin)->deleteJson("/api/tickets/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('tickets', ['id' => $id]);
        $this->assertDatabaseMissing('ticket_replies', ['ticket_id' => $id]);
    }
}
