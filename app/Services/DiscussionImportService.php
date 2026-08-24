<?php

namespace App\Services;

use App\Events\DiscussionScheduled;
use App\Models\Discussion;
use App\Models\Team;
use App\Rules\WhatsappNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DiscussionImportService
{
    private const MAX_ROWS = 200;

    public function parseRows(UploadedFile $file): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);

        $sheet = $reader->load($file->getRealPath())->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        array_shift($rows);

        $rows = array_slice($rows, 0, self::MAX_ROWS);

        return array_map(fn (array $row) => [
            'team_name' => trim((string) ($row[0] ?? '')),
            'place' => trim((string) ($row[1] ?? '')),
            'discussion_date' => trim((string) ($row[2] ?? '')),
            'discussion_time' => trim((string) ($row[3] ?? '')),
            'committee' => trim((string) ($row[4] ?? '')),
            'whatsapp' => trim((string) ($row[5] ?? '')),
        ], $rows);
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

            if ($row['team_name'] === '') {
                $errors[] = 'اسم الفريق مطلوب.';
            } else {
                $team = Team::with('project')->where('term_id', $termId)->where('name', $row['team_name'])->first();

                if (! $team) {
                    $errors[] = 'لا يوجد فريق بهذا الاسم هذا الفصل.';
                } elseif (! $team->project || $team->project->status?->value !== 'in_progress') {
                    $errors[] = 'الفريق ليس له مشروع قيد التنفيذ.';
                } elseif (Discussion::where('project_id', $team->project->id)->exists()) {
                    $errors[] = 'يوجد موعد مناقشة مسجّل مسبقًا لهذا المشروع.';
                } elseif (isset($seenTeams[$row['team_name']])) {
                    $errors[] = 'الفريق مكرر داخل الملف.';
                }
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
                $seenTeams[$row['team_name']] = true;
            }

            if ($errors === []) {
                $valid[] = $row + ['project_id' => $team->project->id, 'supervisor_id' => $team->supervisor_id];
            } else {
                $invalid[] = ['row' => $rowNumber, 'data' => $row, 'errors' => $errors];
            }
        }

        return ['valid' => $valid, 'invalid' => $invalid];
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
                $discussion = Discussion::create([
                    'project_id' => $row['project_id'],
                    'supervisor_id' => $row['supervisor_id'],
                    'place' => $row['place'],
                    'discussion_date' => $row['discussion_date'],
                    'discussion_time' => $row['discussion_time'],
                    'committee' => $row['committee'],
                    'whatsapp' => $row['whatsapp'] !== '' ? $row['whatsapp'] : null,
                    'status' => 'pending',
                    'term_id' => $termId,
                ]);

                DiscussionScheduled::dispatch($discussion);

                $discussions[] = $discussion;
            }

            return $discussions;
        });

        return ['valid' => [], 'invalid' => [], 'created' => $created];
    }
}
