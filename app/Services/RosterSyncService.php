<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\ImportBatch;
use App\Models\Specialization;
use App\Models\User;
use App\Rules\CollegeEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * مزامنة كشف الطلبة: الملف الرسمي المُصدَّر من UCAS هو المصدر الأوثق كل أسبوع/أسبوعين.
 * المفتاح الثابت للمطابقة هو الرقم الجامعي (STUDENT_NO). المزامنة تحسب الفرق ثم تطبّقه:
 *   - مضاف:   رقم جامعي في الملف وغير موجود بالنظام      → إنشاء حساب طالب.
 *   - مُستعاد: رقم جامعي بالملف لكنه مؤرشف (محذوف ناعم)   → استعادة + تحديث ما تغيّر.
 *   - معدّل:   رقم موجود ونشط لكن تغيّر اسمه/تخصصه/…       → تحديث الحقول.
 *   - محذوف:  طالب نشط بالنظام غير موجود بالملف الجديد    → أرشفة (حذف ناعم، قابل للرجوع).
 *   - بلا تغيير.
 * كل تطبيق يُسجَّل كدفعة (ImportBatch) بلقطة عكسية تكفي للتراجع خطوة واحدة.
 */
class RosterSyncService
{
    private const MAX_ROWS = 2000;

    /** ترويسات الأعمدة المقبولة — تقرأ تصدير UCAS الخام أو قالبًا عربيًا مبسّطًا بنفس المعنى */
    private const COLUMN_ALIASES = [
        'university_number' => ['STUDENT_NO', 'الرقم الجامعي', 'رقم الطالب'],
        'name' => ['STUDENT_A_NAME', 'الاسم', 'اسم الطالب', 'اسم العضو'],
        'gender' => ['SEX', 'الجنس'],
        'specialization' => ['DEPARTMENT_NAME', 'التخصص', 'التخصص الدراسي'],
        'region' => ['ORIGIN_GOVE', 'المحافظة', 'مكان السكن'],
        'supervisor' => ['TEACHER_NAME', 'المشرف', 'اسم المشرف'],
        'section' => ['BRANCH_NO', 'الشعبة'],
    ];

    /** تحويل قيمة SEX إلى GenderEnum */
    private const GENDER_MAP = [
        'M' => 'male', 'F' => 'female',
        'ذكر' => 'male', 'أنثى' => 'female', 'انثى' => 'female',
    ];

    /** تحويل المحافظة إلى RegionEnum (غزة / وسط غزة / الجنوب) — بذل أفضل تطابق، وإلا تُترك فارغة */
    private const REGION_MAP = [
        'غزة' => 'gaza', 'مدينة غزة' => 'gaza', 'شمال غزة' => 'gaza', 'الشمال' => 'gaza',
        'المنطقة الوسطى' => 'middle', 'الوسطى' => 'middle', 'وسط غزة' => 'middle', 'الوسط' => 'middle',
        'خانيونس' => 'south', 'خان يونس' => 'south', 'رفح' => 'south', 'الجنوب' => 'south',
    ];

    /** الحقول التي تُقارَن لاكتشاف التعديل وتُخزَّن في اللقطة العكسية */
    private const TRACKED_FIELDS = ['name', 'gender', 'region', 'specialization_id'];

    public function parseRows(UploadedFile $file): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);

        $sheet = $reader->load($file->getRealPath())->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        $header = array_map(fn ($cell) => trim((string) $cell), array_shift($rows) ?? []);
        $columns = $this->mapColumns($header);

        $rows = array_slice($rows, 0, self::MAX_ROWS);

        $out = [];
        foreach ($rows as $row) {
            $get = fn (string $field) => $columns[$field] !== null ? trim((string) ($row[$columns[$field]] ?? '')) : '';

            $universityNumber = $this->cleanNumber($get('university_number'));
            if ($universityNumber === '') {
                continue; // صف فارغ أو صف مجموع/تذييل بلا رقم جامعي
            }

            $out[] = [
                'university_number' => $universityNumber,
                'name' => $this->cleanName($get('name')),
                'gender' => $this->mapGender($get('gender')),
                'region' => $this->mapRegion($get('region')),
                'specialization' => $get('specialization'),
                'supervisor' => $get('supervisor'),
                'section' => $get('section'),
            ];
        }

        return $out;
    }

    private function mapColumns(array $header): array
    {
        $columns = [];
        foreach (self::COLUMN_ALIASES as $field => $labels) {
            $columns[$field] = null;
            foreach ($header as $index => $label) {
                foreach ($labels as $alias) {
                    if (strcasecmp($label, $alias) === 0) {
                        $columns[$field] = $index;
                        break 2;
                    }
                }
            }
        }

        return $columns;
    }

    /** الرقم الجامعي قد يأتي رقمًا (3175099.0) من الإكسل — ننظّفه لسلسلة أرقام */
    private function cleanNumber(string $value): string
    {
        $value = trim($value);
        if ($value !== '' && preg_match('/^\d+\.0+$/', $value)) {
            $value = (string) (int) $value;
        }

        return $value;
    }

    /** اسم الطالب في تصدير UCAS يسبقه رمز "###" وفراغات — ننظّفه */
    private function cleanName(string $value): string
    {
        $value = preg_replace('/#+/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', (string) $value);

        return trim((string) $value);
    }

    private function mapGender(string $value): string
    {
        $value = trim($value);

        return self::GENDER_MAP[$value] ?? self::GENDER_MAP[strtoupper($value)] ?? '';
    }

    private function mapRegion(string $value): ?string
    {
        $value = trim($value);

        return self::REGION_MAP[$value] ?? null;
    }

    private function generatedEmail(string $universityNumber): string
    {
        return strtolower($universityNumber).'@'.CollegeEmail::STUDENT_DOMAIN;
    }

    /**
     * يحسب الفرق بين صفوف الملف وبين طلاب الفصل الحالي، ويصنّفهم.
     * يُرجع: added/updated/restored/removed (قوائم للعرض) + invalid + valid + summary.
     */
    public function diff(array $rows, ?int $termId): array
    {
        $specializationsByName = Specialization::pluck('id', 'name');

        // طلاب الفصل الحالي — بما فيهم المؤرشفون (لمطابقة العائدين بدل تكرارهم)
        $existing = User::withTrashed()
            ->where('role', RoleEnum::Student)
            ->where('term_id', $termId)
            ->get(['id', 'name', 'email', 'university_number', 'gender', 'region', 'specialization_id', 'deleted_at'])
            ->keyBy('university_number');

        $added = [];
        $updated = [];
        $restored = [];
        $invalid = [];
        $valid = [];
        $unchanged = 0;
        $seen = [];
        $seenInFile = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $errors = [];
            $universityNumber = (string) ($row['university_number'] ?? '');

            if ($universityNumber === '') {
                continue;
            }

            if ($row['name'] === '') {
                $errors[] = 'الاسم مطلوب.';
            }

            if (isset($seenInFile[$universityNumber])) {
                $errors[] = 'الرقم الجامعي مكرر داخل الملف.';
            }
            $seenInFile[$universityNumber] = true;

            $specializationId = null;
            if ($row['specialization'] === '') {
                $errors[] = 'التخصص مطلوب.';
            } elseif (! $specializationsByName->has($row['specialization'])) {
                $errors[] = "التخصص \"{$row['specialization']}\" غير موجود بالنظام — أضِفه أولًا.";
            } else {
                $specializationId = $specializationsByName->get($row['specialization']);
            }

            $current = $existing->get($universityNumber);

            // تعارض: الإيميل المولّد مستخدم لحساب آخر (مثلًا نفس الرقم مسجّل بفصل مختلف)
            if (! $current) {
                $email = $this->generatedEmail($universityNumber);
                if (User::withTrashed()->where('email', $email)->exists()) {
                    $errors[] = 'الرقم الجامعي مسجّل مسبقًا بالنظام (قد يكون بفصل آخر).';
                }
            }

            $resolved = $row + [
                'specialization_id' => $specializationId,
                'email' => $current->email ?? $this->generatedEmail($universityNumber),
            ];

            if ($errors !== []) {
                $invalid[] = ['row' => $rowNumber, 'data' => $row, 'errors' => $errors];

                continue;
            }

            $valid[] = $resolved;
            $seen[$universityNumber] = true;

            if (! $current) {
                $added[] = $this->display($resolved);
            } elseif ($current->deleted_at !== null) {
                $restored[] = $this->display($resolved);
            } else {
                $changes = $this->changedFields($current, $resolved);
                if ($changes === []) {
                    $unchanged++;
                } else {
                    $updated[] = $this->display($resolved) + ['changes' => $changes];
                }
            }
        }

        // المحذوفون: طلاب نشطون بالفصل ليسوا في الملف الجديد
        $removed = [];
        foreach ($existing as $universityNumber => $current) {
            if ($current->deleted_at === null && ! isset($seen[$universityNumber])) {
                $removed[] = [
                    'id' => $current->id,
                    'university_number' => $universityNumber,
                    'name' => $current->name,
                ];
            }
        }

        return [
            'added' => $added,
            'updated' => $updated,
            'restored' => $restored,
            'removed' => $removed,
            'invalid' => $invalid,
            'valid' => $valid,
            'summary' => [
                'added' => count($added),
                'updated' => count($updated),
                'restored' => count($restored),
                'removed' => count($removed),
                'unchanged' => $unchanged,
                'invalid' => count($invalid),
            ],
        ];
    }

    private function display(array $row): array
    {
        return [
            'university_number' => $row['university_number'],
            'name' => $row['name'],
            'specialization' => $row['specialization'],
            'gender' => $row['gender'],
            'region' => $row['region'],
            'section' => $row['section'] ?? '',
            'supervisor' => $row['supervisor'] ?? '',
        ];
    }

    /** الحقول المتغيّرة بين السجل الحالي وصف الملف (للعرض في المعاينة وللّقطة العكسية) */
    private function changedFields(User $current, array $row): array
    {
        $after = [
            'name' => $row['name'],
            'gender' => $row['gender'] !== '' ? $row['gender'] : null,
            'region' => $row['region'],
            'specialization_id' => $row['specialization_id'],
        ];

        $changes = [];
        foreach (self::TRACKED_FIELDS as $field) {
            $old = $current->{$field};
            $old = $old instanceof \BackedEnum ? $old->value : $old;
            $new = $after[$field];

            // لا نعتبر فراغًا جديدًا (قيمة غير موجودة بالملف) تعديلًا يمسح قيمة قائمة
            if (($new === null || $new === '') && $field !== 'name') {
                continue;
            }

            if ((string) $old !== (string) $new) {
                $changes[$field] = ['from' => $old, 'to' => $new];
            }
        }

        return $changes;
    }

    /**
     * يطبّق المزامنة داخل معاملة واحدة ويسجّل دفعة قابلة للتراجع خطوة واحدة.
     * يعيد حساب الفرق من الصفوف على الخادم (لا نثق بتصنيف العميل).
     */
    public function confirm(array $rows, ?int $termId, ?int $uploadedBy, ?string $filename): array
    {
        $diff = $this->diff($rows, $termId);

        if ($diff['invalid'] !== []) {
            return ['invalid' => $diff['invalid'], 'summary' => $diff['summary']];
        }

        $existing = User::withTrashed()
            ->where('role', RoleEnum::Student)
            ->where('term_id', $termId)
            ->get()
            ->keyBy('university_number');

        $batch = DB::transaction(function () use ($diff, $existing, $termId, $uploadedBy, $filename) {
            $reverse = ['created' => [], 'restored' => [], 'updated' => [], 'archived' => []];
            $seen = [];

            foreach ($diff['valid'] as $row) {
                $num = $row['university_number'];
                $seen[$num] = true;
                $current = $existing->get($num);

                if (! $current) {
                    $user = $this->createStudent($row, $termId);
                    $reverse['created'][] = $user->id;
                } elseif ($current->trashed()) {
                    $before = $this->snapshot($current);
                    $current->restore();
                    $current->fill($this->writableFields($row))->save();
                    $reverse['restored'][] = ['id' => $current->id, 'before' => $before];
                } else {
                    $changes = $this->changedFields($current, $row);
                    if ($changes !== []) {
                        $before = $this->snapshot($current);
                        $current->fill($this->writableFields($row))->save();
                        $reverse['updated'][] = ['id' => $current->id, 'before' => $before];
                    }
                }
            }

            // الأرشفة: طلاب الفصل النشطون غير الموجودين بالملف
            foreach ($existing as $num => $current) {
                if (! $current->trashed() && ! isset($seen[$num])) {
                    $current->deleted_reason = 'أُرشِف تلقائيًا بمزامنة كشف الطلبة '.now()->format('Y-m-d');
                    $current->save();
                    $current->delete();
                    $reverse['archived'][] = $current->id;
                }
            }

            return ImportBatch::record('student_roster', $termId, $uploadedBy, $filename, $diff['summary'], $reverse);
        });

        return ['batch' => $batch, 'summary' => $diff['summary']];
    }

    /** يتراجع عن دفعة مطبَّقة خطوةً واحدة: يحذف المُنشأين، ويعيد المؤرشفين، ويؤرشف المُستعادين، ويرجع المعدّلين */
    public function revert(ImportBatch $batch): void
    {
        if ($batch->status !== 'applied') {
            return;
        }

        $reverse = $batch->reverse ?? [];

        DB::transaction(function () use ($batch, $reverse) {
            // المُنشأون حديثًا بهذه الدفعة — حذف نهائي (لا بيانات تابعة بعد)
            foreach ($reverse['created'] ?? [] as $id) {
                User::withTrashed()->whereKey($id)->first()?->forceDelete();
            }

            // المؤرشفون بهذه الدفعة — استعادة
            foreach ($reverse['archived'] ?? [] as $id) {
                $user = User::withTrashed()->whereKey($id)->first();
                if ($user) {
                    $user->restore();
                    $user->deleted_reason = null;
                    $user->save();
                }
            }

            // المُستعادون بهذه الدفعة — إعادة أرشفتهم مع إرجاع حقولهم
            foreach ($reverse['restored'] ?? [] as $entry) {
                $user = User::withTrashed()->whereKey($entry['id'])->first();
                if ($user) {
                    $user->fill($entry['before'])->save();
                    $user->delete();
                }
            }

            // المعدّلون — إرجاع الحقول لما كانت
            foreach ($reverse['updated'] ?? [] as $entry) {
                $user = User::withTrashed()->whereKey($entry['id'])->first();
                $user?->fill($entry['before'])->save();
            }

            $batch->status = 'reverted';
            $batch->reverted_at = now();
            $batch->save();
        });
    }

    /** أحدث دفعة لنوع وفصل (لعرض الحالة وزر التراجع) */
    public function latest(?int $termId): ?ImportBatch
    {
        return ImportBatch::latestFor('student_roster', $termId);
    }

    private function createStudent(array $row, ?int $termId): User
    {
        return User::create([
            'name' => $row['name'],
            'email' => $this->generatedEmail($row['university_number']),
            'password' => Str::random(32),
            'role' => RoleEnum::Student,
            'university_number' => $row['university_number'],
            'gender' => $row['gender'] !== '' ? $row['gender'] : null,
            'region' => $row['region'],
            'specialization_id' => $row['specialization_id'],
            'term_id' => $termId,
            'status' => UserStatusEnum::Active,
            'must_change_password' => true,
        ]);
    }

    /** الحقول القابلة للكتابة من صف الملف إلى حساب الطالب */
    private function writableFields(array $row): array
    {
        $fields = [
            'name' => $row['name'],
            'specialization_id' => $row['specialization_id'],
        ];

        if ($row['gender'] !== '') {
            $fields['gender'] = $row['gender'];
        }
        if ($row['region'] !== null) {
            $fields['region'] = $row['region'];
        }

        return $fields;
    }

    /** لقطة للحقول المتتبَّعة (للتراجع) */
    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'gender' => $user->gender instanceof \BackedEnum ? $user->gender->value : $user->gender,
            'region' => $user->region instanceof \BackedEnum ? $user->region->value : $user->region,
            'specialization_id' => $user->specialization_id,
        ];
    }
}
