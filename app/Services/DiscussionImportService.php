<?php

namespace App\Services;

use App\Events\DiscussionScheduled;
use App\Models\Discussion;
use App\Models\Team;
use App\Rules\WhatsappNumber;
use App\Support\DiscussionScheduleGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

class DiscussionImportService
{
    private const MAX_ROWS = 200;

    public function __construct(private DiscussionScheduleGuard $guard) {}

    /** أسماء الأشهر العربية كما تُنتجها Intl.DateTimeFormat('ar-EG', {month:'long'}) بواجهة الموقع — لفكّ تاريخ مُصدَّر كنص مقروء */
    private const ARABIC_MONTHS = [
        'يناير' => 1, 'فبراير' => 2, 'مارس' => 3, 'أبريل' => 4, 'مايو' => 5, 'يونيو' => 6,
        'يوليو' => 7, 'أغسطس' => 8, 'سبتمبر' => 9, 'أكتوبر' => 10, 'نوفمبر' => 11, 'ديسمبر' => 12,
    ];

    private const ARABIC_DIGITS = ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'];

    /** يقبل ترويسة عمود ضمن أكثر من اسم شائع — حتى يقرأ ملفات مُصدَّرة من أكثر من مكان بالموقع بنفس الصيغة */
    private const COLUMN_ALIASES = [
        'team_id' => ['رقم المجموعة', 'رقم الفريق'],
        'team_name' => ['الفريق', 'اسم الفريق'],
        'place' => ['المكان'],
        'discussion_date' => ['التاريخ'],
        'discussion_time' => ['الوقت'],
        'committee' => ['لجنة المناقشة', 'اللجنة'],
        'whatsapp' => ['الواتس', 'الواتساب', 'رقم الواتساب'],
    ];

    public function parseRows(UploadedFile $file): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);

        $sheet = $reader->load($file->getRealPath())->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        $header = array_map(fn ($cell) => trim((string) $cell), array_shift($rows) ?? []);
        $columns = $this->mapColumns($header);

        $rows = array_slice($rows, 0, self::MAX_ROWS);

        $parsed = [];
        $lastKey = null;

        foreach ($rows as $row) {
            $raw = fn (string $field) => $columns[$field] !== null ? ($row[$columns[$field]] ?? '') : '';
            $value = fn (string $field) => trim((string) $raw($field));

            $teamId = $value('team_id');
            $teamName = $value('team_name');
            $place = $value('place');
            $date = $this->normalizeDate($raw('discussion_date'));
            $time = $this->normalizeTime($raw('discussion_time'));
            $committee = $value('committee');

            // صفوف امتداد لنفس المجموعة (مثل عدة أسطر لأعضاء نفس الفريق بملف تصدير القوائم) — تُتجاهل بصمت
            $isBlankRow = $teamId === '' && $teamName === '' && $place === '' && $date === '' && $time === '' && $committee === '';
            if ($isBlankRow && $lastKey !== null) {
                continue;
            }

            $key = $teamId !== '' ? "id:{$teamId}" : "name:{$teamName}";
            if ($key === $lastKey) {
                continue;
            }
            $lastKey = $key;

            $parsed[] = [
                'team_id' => $teamId,
                'team_name' => $teamName,
                'place' => $place,
                'discussion_date' => $date,
                'discussion_time' => $time,
                'committee' => $committee,
                'whatsapp' => $value('whatsapp'),
            ];
        }

        return $parsed;
    }

    /** إكسل بيحوّل تلقائيًا تاريخ/وقت مكتوب كنص إلى قيمة تاريخ داخلية (رقم تسلسلي) — نرجّعها لصيغة YYYY-MM-DD المتوقعة.
     *  وبيدعم كمان صيغة التاريخ العربية المقروءة يلي بيصدّرها الموقع (مثل "٢٦ يوليو ٢٠٢٦"). */
    private function normalizeDate(mixed $raw): string
    {
        if (is_numeric($raw)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d');
            } catch (Throwable) {
                // تجاهل وارجع القيمة كنص أدناه
            }
        }

        $text = trim((string) $raw);

        if ($text === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return $text;
        }

        $ascii = strtr($text, self::ARABIC_DIGITS);

        if (preg_match('/(\d{1,2})\s+(\S+)\s+(\d{4})/u', $ascii, $m) && isset(self::ARABIC_MONTHS[$m[2]])) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], self::ARABIC_MONTHS[$m[2]], (int) $m[1]);
        }

        return $text;
    }

    /** نفس المنطق لعمود الوقت — يرجّعه لصيغة HH:MM */
    private function normalizeTime(mixed $raw): string
    {
        if (is_numeric($raw)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $raw)->format('H:i');
            } catch (Throwable) {
                // تجاهل وارجع القيمة كنص أدناه
            }
        }

        return trim((string) $raw);
    }

    private function mapColumns(array $header): array
    {
        $columns = [];

        foreach (self::COLUMN_ALIASES as $field => $labels) {
            $columns[$field] = null;
            foreach ($header as $index => $label) {
                if (in_array($label, $labels, true)) {
                    $columns[$field] = $index;
                    break;
                }
            }
        }

        return $columns;
    }

    public function validate(array $rows, int $termId): array
    {
        $valid = [];
        $invalid = [];
        $seenTeams = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $errors = [];
            $team = null;

            if ($row['team_id'] === '' && $row['team_name'] === '') {
                $errors[] = 'اسم الفريق أو رقم المجموعة مطلوب.';
            } else {
                $team = $row['team_id'] !== ''
                    ? Team::with('project')->where('term_id', $termId)->where('id', $row['team_id'])->first()
                    : Team::with('project')->where('term_id', $termId)->where('name', $row['team_name'])->first();

                if (! $team) {
                    $errors[] = 'لا يوجد فريق مطابق بهذا الرقم أو الاسم هذا الفصل.';
                } elseif (! $team->project || $team->project->status?->value !== 'in_progress') {
                    $errors[] = 'الفريق ليس له مشروع قيد التنفيذ.';
                }
                // موعد موجود مسبقًا لهذا المشروع لا يُعدّ خطأ — إعادة الرفع تحدّث الموعد بعد التعديل.
            }

            $dedupeKey = $team?->id ?? $row['team_id'].'|'.$row['team_name'];
            if ($team && isset($seenTeams[$dedupeKey])) {
                $errors[] = 'الفريق مكرر داخل الملف.';
            }

            if ($row['place'] === '') {
                $errors[] = 'مكان المناقشة مطلوب.';
            }

            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['discussion_date'])) {
                $errors[] = 'تاريخ المناقشة يجب أن يكون بصيغة YYYY-MM-DD.';
            }

            if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $row['discussion_time'])) {
                $errors[] = 'وقت المناقشة يجب أن يكون بصيغة HH:MM.';
            }

            if ($row['committee'] === '') {
                $errors[] = 'لجنة المناقشة مطلوبة.';
            }

            if ($row['whatsapp'] !== '' && ! preg_match(WhatsappNumber::PATTERN, $row['whatsapp'])) {
                $errors[] = 'رقم الواتساب يجب أن يبدأ بـ 970 أو 972 ويتبعه رقم محمول صحيح.';
            }

            if ($team) {
                $seenTeams[$dedupeKey] = true;
            }

            if ($errors === []) {
                $valid[] = $row + ['project_id' => $team->project->id, 'supervisor_id' => $team->supervisor_id, '_row' => $rowNumber];
            } else {
                $invalid[] = ['row' => $rowNumber, 'data' => $row, 'errors' => $errors];
            }
        }

        [$valid, $conflicts] = $this->checkConflicts($valid);
        $invalid = array_merge($invalid, $conflicts);

        $valid = array_map(function ($row) {
            unset($row['_row']);

            return $row;
        }, $valid);

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    /**
     * يفحص تعارضات الجدول (قاعة/مناقش/مشرف) بين الصفوف الصالحة بعضها ببعض وبين المواعيد
     * الموجودة لمشاريع أخرى (مشاريع هذه الدفعة تُستثنى لأنها ستُستبدل عند التأكيد).
     *
     * @return array{0: array<int,array>, 1: array<int,array>}
     */
    private function checkConflicts(array $valid): array
    {
        $batchProjectIds = array_column($valid, 'project_id');

        $kept = [];
        $conflicts = [];
        $accepted = []; // فترات الصفوف المقبولة حتى الآن للمقارنة بينها

        foreach ($valid as $row) {
            $slot = [
                'place' => $row['place'],
                'discussion_date' => $row['discussion_date'],
                'discussion_time' => $row['discussion_time'],
                'committee' => $row['committee'],
                'supervisor_id' => $row['supervisor_id'],
            ];

            // استثناء مواعيد مشاريع الدفعة الموجودة (ستُستبدل عند التأكيد)
            $dbOthers = array_values(array_filter(
                Discussion::query()
                    ->where('discussion_date', $row['discussion_date'])
                    ->whereNotIn('project_id', $batchProjectIds)
                    ->get(['place', 'discussion_date', 'discussion_time', 'committee'])
                    ->map(fn (Discussion $d) => [
                        'place' => $d->place,
                        'discussion_date' => $d->discussion_date instanceof \DateTimeInterface ? $d->discussion_date->format('Y-m-d') : (string) $d->discussion_date,
                        'discussion_time' => $d->discussion_time,
                        'committee' => $d->committee,
                    ])->all()
            ));

            $errors = $this->guard->errors($slot, array_merge($dbOthers, $accepted));

            if ($errors === []) {
                $kept[] = $row;
                $accepted[] = $slot;
            } else {
                $conflicts[] = ['row' => $row['_row'], 'data' => $row, 'errors' => $errors];
            }
        }

        return [$kept, $conflicts];
    }

    public function confirm(array $rows, int $termId): array
    {
        $result = $this->validate($rows, $termId);

        if ($result['invalid'] !== []) {
            return $result;
        }

        $created = DB::transaction(function () use ($rows, $termId) {
            $discussions = [];

            foreach ($rows as $row) {
                // إعادة الرفع بعد التعديل تحدّث موعد نفس المشروع بدل إنشاء موعد مكرّر
                $discussion = Discussion::updateOrCreate(
                    ['project_id' => $row['project_id'], 'term_id' => $termId],
                    [
                        'supervisor_id' => $row['supervisor_id'],
                        'place' => $row['place'],
                        'discussion_date' => $row['discussion_date'],
                        'discussion_time' => $row['discussion_time'],
                        'committee' => $row['committee'],
                        'whatsapp' => ($row['whatsapp'] ?? '') !== '' ? $row['whatsapp'] : null,
                        'status' => 'pending',
                    ]
                );

                DiscussionScheduled::dispatch($discussion);

                $discussions[] = $discussion;
            }

            return $discussions;
        });

        return ['valid' => [], 'invalid' => [], 'created' => $created];
    }

    /** قالب استيراد فعلي: صف جاهز لكل فريق له مشروع قيد التنفيذ وما إله موعد مناقشة بعد — بنفس أعمدة الاستيراد بالضبط */
    public function buildTemplate(int $termId): Spreadsheet
    {
        $candidates = Team::with('project')
            ->where('term_id', $termId)
            ->get()
            ->filter(fn (Team $team) => $team->project
                && $team->project->status?->value === 'in_progress'
                && ! Discussion::where('project_id', $team->project->id)->exists())
            ->values();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            'رقم المجموعة', 'اسم الفريق', 'اسم المشروع', 'المكان', 'التاريخ', 'الوقت', 'لجنة المناقشة', 'واتساب',
        ], null, 'A1');

        $row = 2;

        foreach ($candidates as $team) {
            $sheet->fromArray([
                $team->id,
                $team->name,
                $team->project->name ?? '',
                '', '', '', '', '',
            ], null, "A{$row}");

            $row++;
        }

        return $spreadsheet;
    }
}
