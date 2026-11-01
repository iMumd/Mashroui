<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

/** مزامنة كشف الطلبة: معاينة الفرق، التطبيق (مضاف/معدّل/محذوف/مُستعاد)، والتراجع خطوة واحدة */
class RosterImportTest extends TestCase
{
    use RefreshDatabase;

    private int $termId;

    private int $specId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->termId = DB::table('academic_terms')->insertGetId(['name' => 'T', 'is_current' => true]);
        $dept = DB::table('departments')->insertGetId(['name' => 'قسم']);
        $this->specId = DB::table('specializations')->insertGetId([
            'department_id' => $dept, 'name' => 'تصميم وبرمجة تطبيقات الموبايل', 'degree' => 'diploma',
        ]);
    }

    /** يبني ملف xlsx بأعمدة تصدير UCAS من صفوف [رقم, اسم, جنس, محافظة] */
    private function rosterFile(array $rows): UploadedFile
    {
        $ss = new Spreadsheet;
        $sheet = $ss->getActiveSheet();
        $sheet->fromArray(['STUDENT_NO', 'STUDENT_A_NAME', 'SEX', 'DEPARTMENT_NAME', 'ORIGIN_GOVE'], null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'roster').'.xlsx';
        (new XlsxWriter($ss))->save($path);

        return new UploadedFile($path, 'roster.xlsx', null, null, true);
    }

    private function student(string $num, string $name, bool $trashed = false): User
    {
        $u = User::factory()->create([
            'name' => $name, 'email' => $num.'@smail.ucas.edu.ps', 'role' => RoleEnum::Student,
            'university_number' => $num, 'specialization_id' => $this->specId, 'term_id' => $this->termId,
        ]);
        if ($trashed) {
            $u->delete();
        }

        return $u;
    }

    public function test_preview_categorizes_added_updated_removed_restored(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);

        $this->student('900001', 'طالب أ');            // سيُعدَّل
        $this->student('900002', 'طالب ب');            // سيُؤرشَف (غائب عن الملف)
        $this->student('900004', 'طالب د', true);      // مؤرشف → سيُستعاد

        $file = $this->rosterFile([
            ['900001', '###  طالب أ المعدل', 'M', 'تصميم وبرمجة تطبيقات الموبايل', 'غزة'],
            ['900003', 'طالب ج', 'F', 'تصميم وبرمجة تطبيقات الموبايل', 'رفح'],
            ['900004', 'طالب د', 'M', 'تصميم وبرمجة تطبيقات الموبايل', 'غزة'],
        ]);

        $res = $this->actingAs($admin)->post('/api/roster/import/preview', [
            'term_id' => $this->termId, 'file' => $file,
        ])->assertOk();

        $res->assertJsonPath('summary.added', 1)
            ->assertJsonPath('summary.updated', 1)
            ->assertJsonPath('summary.removed', 1)
            ->assertJsonPath('summary.restored', 1)
            ->assertJsonPath('summary.invalid', 0);

        // الاسم يُنظَّف من "###"
        $this->assertSame('طالب أ المعدل', $res->json('updated.0.name'));
    }

    public function test_confirm_then_revert_round_trips_state(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);

        $a = $this->student('900001', 'طالب أ');
        $b = $this->student('900002', 'طالب ب');
        $d = $this->student('900004', 'طالب د', true);

        $rows = [
            ['university_number' => '900001', 'name' => 'طالب أ المعدل', 'gender' => 'male', 'region' => 'gaza', 'specialization' => 'تصميم وبرمجة تطبيقات الموبايل'],
            ['university_number' => '900003', 'name' => 'طالب ج', 'gender' => 'female', 'region' => 'south', 'specialization' => 'تصميم وبرمجة تطبيقات الموبايل'],
            ['university_number' => '900004', 'name' => 'طالب د', 'gender' => 'male', 'region' => 'gaza', 'specialization' => 'تصميم وبرمجة تطبيقات الموبايل'],
        ];

        $this->actingAs($admin)->postJson('/api/roster/import/confirm', [
            'term_id' => $this->termId, 'rows' => $rows, 'filename' => 'roster.xlsx',
        ])->assertCreated()->assertJsonPath('summary.added', 1);

        // بعد التطبيق
        $c = User::where('university_number', '900003')->where('term_id', $this->termId)->first();
        $this->assertNotNull($c);
        $this->assertSame('طالب أ المعدل', $a->fresh()->name);
        $this->assertTrue($b->fresh()->trashed());
        $this->assertFalse(User::withTrashed()->find($d->id)->trashed());
        $this->assertDatabaseHas('import_batches', ['status' => 'applied', 'kind' => 'student_roster']);

        // تراجع خطوة واحدة
        $this->actingAs($admin)->postJson('/api/roster/import/revert', ['term_id' => $this->termId])
            ->assertOk()->assertJsonPath('batch.status', 'reverted');

        $this->assertNull(User::withTrashed()->where('university_number', '900003')->first()); // المُنشأ حُذف
        $this->assertSame('طالب أ', $a->fresh()->name);                                        // المعدَّل رجع
        $this->assertFalse($b->fresh()->trashed());                                            // المؤرشف استُعيد
        $this->assertTrue(User::withTrashed()->find($d->id)->trashed());                       // المُستعاد أُعيد أرشفته
    }

    public function test_only_super_admin_can_sync(): void
    {
        $committee = User::factory()->create(['role' => RoleEnum::Committee]);

        $this->actingAs($committee)->postJson('/api/roster/import/confirm', [
            'term_id' => $this->termId,
            'rows' => [['university_number' => '900009', 'name' => 'x', 'specialization' => 'تصميم وبرمجة تطبيقات الموبايل']],
        ])->assertForbidden();
    }

    public function test_unknown_specialization_is_invalid(): void
    {
        $admin = User::factory()->create(['role' => RoleEnum::SuperAdmin]);

        $file = $this->rosterFile([
            ['900005', 'طالب هـ', 'M', 'تخصص غير موجود', 'غزة'],
        ]);

        $this->actingAs($admin)->post('/api/roster/import/preview', [
            'term_id' => $this->termId, 'file' => $file,
        ])->assertOk()->assertJsonPath('summary.invalid', 1);

        $this->assertSame(0, ImportBatch::count());
    }
}
