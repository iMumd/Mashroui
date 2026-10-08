<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    private const DEGREE_LABELS = ['diploma' => 'دبلوم', 'bachelor' => 'بكالوريوس'];

    private const GENDER_LABELS = ['male' => 'طلاب', 'female' => 'طالبات'];

    private const REGION_LABELS = ['gaza' => 'غزة', 'south' => 'الجنوب'];

    // GET /reports/supervisors — تقرير بكل مشرف ومجموعاته (نوع المشروع، الأماكن، الجنس، الدرجة)
    public function supervisors(Request $request)
    {
        $viewer = $request->user();
        abort_unless(in_array($viewer->role, [RoleEnum::Committee, RoleEnum::SuperAdmin, RoleEnum::Supervisor], true), 403);

        $query = Team::with(['supervisor:id,name', 'specialization:id,name,degree', 'project:id,team_id,name,status', 'members.student:id,gender,region'])
            ->orderBy('supervisor_id');

        // المشرف يطبع تقريره فقط
        if ($viewer->role === RoleEnum::Supervisor) {
            $query->where('supervisor_id', $viewer->id);
        } elseif ($request->filled('supervisor_id')) {
            $query->where('supervisor_id', $request->integer('supervisor_id'));
        }

        $teams = $query->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);
        $sheet->fromArray([
            'المشرف', 'المجموعة', 'الشعبة', 'المشروع', 'الدرجة', 'التخصص', 'الجنس', 'أماكن التواجد', 'عدد الأعضاء',
        ], null, 'A1');

        $row = 2;
        foreach ($teams as $team) {
            $genders = $team->members->map(fn ($m) => $m->student?->gender?->value)->filter()->unique();
            $regions = $team->members->map(fn ($m) => $m->student?->region?->value)->filter()->unique();

            $sheet->fromArray([
                $team->supervisor?->name ?? '',
                $team->name,
                $team->section ?? '',
                $team->project?->name ?? '',
                self::DEGREE_LABELS[$team->specialization?->degree?->value] ?? '',
                $team->specialization?->name ?? '',
                $this->labelSet($genders, self::GENDER_LABELS),
                $this->labelSet($regions, self::REGION_LABELS),
                $team->members->count(),
            ], null, "A{$row}");
            $row++;
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'تقرير-المشرفين.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** يحوّل مجموعة قيم مُرمّزة لنص عربي مقروء (طلاب/طالبات، غزة/الجنوب)، أو "مختلط" لو أكثر من قيمة */
    private function labelSet($values, array $labels): string
    {
        $mapped = $values->map(fn ($v) => $labels[$v] ?? $v)->values();

        if ($mapped->isEmpty()) {
            return '';
        }

        return $mapped->count() === 1 ? $mapped->first() : $mapped->implode(' / ');
    }
}
