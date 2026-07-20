<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\InviteLink;
use App\Models\User;
use App\Rules\WhatsappNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TeamImportService
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
            'name' => trim((string) ($row[0] ?? '')),
            'email' => trim((string) ($row[1] ?? '')),
            'university_number' => trim((string) ($row[2] ?? '')),
            'whatsapp' => trim((string) ($row[3] ?? '')),
        ], $rows);
    }

    public function validate(array $rows): array
    {
        $valid = [];
        $invalid = [];
        $seenEmails = [];
        $seenUniversityNumbers = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $errors = [];

            if ($row['name'] === '') {
                $errors[] = 'الاسم مطلوب.';
            }

            if ($row['email'] === '' || ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'البريد الإلكتروني غير صالح.';
            } elseif (isset($seenEmails[$row['email']])) {
                $errors[] = 'البريد الإلكتروني مكرر داخل الملف.';
            } elseif (User::where('email', $row['email'])->exists()) {
                $errors[] = 'البريد الإلكتروني مستخدم مسبقاً.';
            }

            if ($row['university_number'] === '') {
                $errors[] = 'الرقم الجامعي مطلوب.';
            } elseif (isset($seenUniversityNumbers[$row['university_number']])) {
                $errors[] = 'الرقم الجامعي مكرر داخل الملف.';
            } elseif (User::where('university_number', $row['university_number'])->exists()) {
                $errors[] = 'الرقم الجامعي مستخدم مسبقاً.';
            }

            if (! preg_match(WhatsappNumber::PATTERN, $row['whatsapp'])) {
                $errors[] = 'رقم الواتساب يجب أن يبدأ بـ 970 أو 972 ويتبعه رقم محمول صحيح.';
            }

            $seenEmails[$row['email']] = true;
            $seenUniversityNumbers[$row['university_number']] = true;

            if ($errors === []) {
                $valid[] = $row;
            } else {
                $invalid[] = ['row' => $rowNumber, 'data' => $row, 'errors' => $errors];
            }
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    public function confirm(array $rows, int $specializationId, int $termId): array
    {
        $result = $this->validate($rows);

        if ($result['invalid'] !== []) {
            return $result;
        }

        $created = DB::transaction(function () use ($rows, $specializationId, $termId) {
            $users = [];

            foreach ($rows as $row) {
                $user = User::create([
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'password' => Str::random(32),
                    'role' => RoleEnum::Student,
                    'university_number' => $row['university_number'],
                    'whatsapp' => $row['whatsapp'],
                    'specialization_id' => $specializationId,
                    'term_id' => $termId,
                    'status' => UserStatusEnum::Active,
                    'must_change_password' => true,
                ]);

                $invite = InviteLink::create([
                    'user_id' => $user->id,
                    'token' => Str::random(64),
                    'expires_at' => now()->addDays(3),
                ]);

                $users[] = ['user' => $user, 'invite_token' => $invite->token];
            }

            return $users;
        });

        return ['valid' => [], 'invalid' => [], 'created' => $created];
    }
}
