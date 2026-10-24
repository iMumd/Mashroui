<?php

namespace App\Services;

use App\Enums\ProjectStatusEnum;
use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\InviteLink;
use App\Models\Project;
use App\Models\Specialization;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Rules\CollegeEmail;
use App\Rules\WhatsappNumber;
use App\Support\CurrentTerm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TeamImportService
{
    private const MAX_ROWS = 200;

    private const MAX_TEAM_SIZE = 4;

    /** يقبل ترويسة عمود ضمن أكثر من اسم شائع — حتى يقرأ ملفات مُصدَّرة من أكثر من مكان بالموقع بنفس الصيغة */
    private const COLUMN_ALIASES = [
        'name' => ['الاسم', 'اسم العضو', 'اسم الطالب'],
        'email' => ['البريد', 'البريد الإلكتروني'],
        'university_number' => ['الرقم الجامعي'],
        'whatsapp' => ['الواتساب', 'الواتس', 'رقم الواتساب'],
        'specialization' => ['التخصص', 'التخصص الدراسي'],
        'section' => ['الشعبة'],
        'supervisor' => ['المشرف'],
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

        // ملفات تصدير القوائم بتدمج خلايا أعمدة التخصص/الشعبة/المشرف عبر كل أعضاء نفس الفريق —
        // فتطلع فاضية لكل عضو إلا الأول. نعبّيها من آخر قيمة غير فاضية شفناها؛ وسواء انكتبت
        // بكل سطر أو انكتبت مرة وحدة بس، أعضاء الفريق الواحد بينتهي بهم المطاف بنفس قيمة
        // (المشرف + التخصص) المتتالية — وعلى أساسها منجمّعهم لفرق تلقائيًا بدون أي عمود إضافي.
        $lastSpecialization = '';
        $lastSection = '';
        $lastSupervisor = '';

        return array_map(function (array $row) use ($columns, &$lastSpecialization, &$lastSection, &$lastSupervisor) {
            $specialization = $columns['specialization'] !== null ? trim((string) ($row[$columns['specialization']] ?? '')) : '';
            $section = $columns['section'] !== null ? trim((string) ($row[$columns['section']] ?? '')) : '';
            $rawSupervisor = $columns['supervisor'] !== null ? trim((string) ($row[$columns['supervisor']] ?? '')) : '';

            if ($specialization !== '') {
                $lastSpecialization = $specialization;
            }
            if ($section !== '') {
                $lastSection = $section;
            }
            if ($rawSupervisor !== '') {
                $lastSupervisor = $rawSupervisor;
            }

            return [
                'name' => $columns['name'] !== null ? trim((string) ($row[$columns['name']] ?? '')) : '',
                'email' => $columns['email'] !== null ? trim((string) ($row[$columns['email']] ?? '')) : '',
                'university_number' => $columns['university_number'] !== null ? trim((string) ($row[$columns['university_number']] ?? '')) : '',
                'whatsapp' => $columns['whatsapp'] !== null ? trim((string) ($row[$columns['whatsapp']] ?? '')) : '',
                'specialization' => $specialization !== '' ? $specialization : $lastSpecialization,
                'section' => $section !== '' ? $section : $lastSection,
                'supervisor' => $rawSupervisor !== '' ? $rawSupervisor : $lastSupervisor,
            ];
        }, $rows);
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

    /** الملف فيه عمود "المشرف" فعليًا مُعبّى؟ لو لا، نبقى بالسلوك القديم: استيراد حسابات طلاب فقط بدون إنشاء فرق. */
    private function isTeamMode(array $rows): bool
    {
        foreach ($rows as $row) {
            if (($row['supervisor'] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    public function validate(array $rows): array
    {
        $teamMode = $this->isTeamMode($rows);

        $valid = [];
        $invalid = [];
        $seenEmails = [];
        $seenUniversityNumbers = [];
        $specializationsByName = Specialization::pluck('id', 'name');
        // المشرفون المطابقون بالاسم من الفصل الحالي فقط (أسماء مشرفي الفصول السابقة لا تُستخدم)
        $supervisorsByName = User::where('role', RoleEnum::Supervisor)
            ->where('term_id', app(CurrentTerm::class)->get())
            ->get(['id', 'name'])
            ->groupBy('name');

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $errors = [];

            if ($row['name'] === '') {
                $errors[] = 'الاسم مطلوب.';
            }

            if ($row['email'] === '' || ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'البريد الإلكتروني غير صالح.';
            } elseif (! CollegeEmail::matches($row['email'], CollegeEmail::STUDENT_DOMAIN)) {
                $errors[] = 'البريد الإلكتروني يجب أن ينتهي بـ @'.CollegeEmail::STUDENT_DOMAIN.'.';
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

            $specializationId = null;
            if ($row['specialization'] === '') {
                $errors[] = 'التخصص مطلوب.';
            } elseif (! $specializationsByName->has($row['specialization'])) {
                $errors[] = "التخصص \"{$row['specialization']}\" غير موجود بالنظام.";
            } else {
                $specializationId = $specializationsByName->get($row['specialization']);
            }

            $supervisorId = null;
            if ($teamMode) {
                if ($row['supervisor'] === '') {
                    $errors[] = 'المشرف مطلوب لكل عضو (خانة "المشرف" فاضية بهاي السطر).';
                } elseif (! $supervisorsByName->has($row['supervisor'])) {
                    $errors[] = "المشرف \"{$row['supervisor']}\" غير موجود بالنظام.";
                } elseif ($supervisorsByName->get($row['supervisor'])->count() > 1) {
                    $errors[] = "أكثر من مشرف بالاسم \"{$row['supervisor']}\" — عدّلي الاسم ليكون مميّزًا بالنظام أولاً.";
                } else {
                    $supervisorId = $supervisorsByName->get($row['supervisor'])->first()->id;
                }
            }

            $seenEmails[$row['email']] = true;
            $seenUniversityNumbers[$row['university_number']] = true;

            if ($errors === []) {
                $valid[] = $row + [
                    'specialization_id' => $specializationId,
                    'supervisor_id' => $supervisorId,
                ];
            } else {
                $invalid[] = ['row' => $rowNumber, 'data' => $row, 'errors' => $errors];
            }
        }

        return ['valid' => $valid, 'invalid' => $invalid, 'team_mode' => $teamMode];
    }

    /** يجمّع الصفوف الصالحة المتتالية لفرق: فريق جديد يبدأ كل ما تغيّر (المشرف+التخصص) عن السطر
     *  اللي قبله، أو وصل الفريق الحالي للحد الأقصى (4 أعضاء) — أول سطر بكل فريق يصير قائده. */
    private function groupIntoTeams(array $rows): array
    {
        $groups = [];
        $currentKey = null;

        foreach ($rows as $row) {
            $key = $row['supervisor_id'].'|'.$row['specialization_id'];

            if ($currentKey !== $key || count(end($groups) ?: []) >= self::MAX_TEAM_SIZE) {
                $groups[] = [];
                $currentKey = $key;
            }

            $groups[count($groups) - 1][] = $row;
        }

        return $groups;
    }

    public function confirm(array $rows, int $termId): array
    {
        $result = $this->validate($rows);

        if ($result['invalid'] !== []) {
            return $result;
        }

        if (! $result['team_mode']) {
            return $this->confirmLegacy($rows, $termId);
        }

        $created = DB::transaction(function () use ($rows, $termId) {
            $teams = [];

            foreach ($this->groupIntoTeams($rows) as $group) {
                $users = [];
                foreach ($group as $row) {
                    $users[] = $this->createStudent($row, $termId);
                }

                $leader = $users[0];

                $team = Team::create([
                    'name' => 'فريق '.$leader['user']->name,
                    'section' => $group[0]['section'] ?: null,
                    'supervisor_id' => $group[0]['supervisor_id'],
                    'specialization_id' => $group[0]['specialization_id'],
                    'term_id' => $termId,
                    'leader_id' => $leader['user']->id,
                ]);

                foreach ($users as $i => $u) {
                    TeamMember::create([
                        'team_id' => $team->id,
                        'student_id' => $u['user']->id,
                        'is_leader' => $i === 0,
                    ]);
                }

                $departmentId = Specialization::whereKey($group[0]['specialization_id'])->value('department_id');

                Project::create([
                    'team_id' => $team->id,
                    'supervisor_id' => $group[0]['supervisor_id'],
                    'department_id' => $departmentId,
                    'specialization_id' => $group[0]['specialization_id'],
                    'term_id' => $termId,
                    'status' => ProjectStatusEnum::Proposed,
                    'is_featured' => false,
                ]);

                $teams[] = ['team' => $team, 'members' => $users];
            }

            return $teams;
        });

        return ['valid' => [], 'invalid' => [], 'created' => $created, 'team_mode' => true];
    }

    private function confirmLegacy(array $rows, int $termId): array
    {
        $created = DB::transaction(function () use ($rows, $termId) {
            $users = [];

            foreach ($rows as $row) {
                $users[] = $this->createStudent($row, $termId);
            }

            return $users;
        });

        return ['valid' => [], 'invalid' => [], 'created' => $created, 'team_mode' => false];
    }

    private function createStudent(array $row, int $termId): array
    {
        $user = User::create([
            'name' => $row['name'],
            'email' => $row['email'],
            'password' => Str::random(32),
            'role' => RoleEnum::Student,
            'university_number' => $row['university_number'],
            'whatsapp' => $row['whatsapp'],
            'specialization_id' => $row['specialization_id'],
            'term_id' => $termId,
            'status' => UserStatusEnum::Active,
            'must_change_password' => true,
        ]);

        $invite = InviteLink::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(3),
        ]);

        return ['user' => $user, 'invite_token' => $invite->token];
    }
}
