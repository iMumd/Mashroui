<?php

namespace App\Services;

use App\Models\Team;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class TeamExportService
{
    public function build(): Spreadsheet
    {
        $teams = Team::with('members.student', 'supervisor', 'specialization', 'leader', 'project')->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            'اسم الفريق', 'المشرف', 'التخصص', 'القائد', 'الأعضاء', 'حالة المشروع',
        ], null, 'A1');

        $row = 2;

        foreach ($teams as $team) {
            $members = $team->members
                ->map(fn ($member) => sprintf('%s (%s)', $member->student->name, $member->student->university_number))
                ->implode(' / ');

            $sheet->fromArray([
                $team->name,
                $team->supervisor->name ?? '',
                $team->specialization->name ?? '',
                $team->leader->name ?? '',
                $members,
                $team->project->status->value ?? '',
            ], null, "A{$row}");

            $row++;
        }

        return $spreadsheet;
    }
}
