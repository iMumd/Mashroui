<?php

namespace App\Services;

use App\Models\Discussion;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class DiscussionExportService
{
    public function build(): Spreadsheet
    {
        $discussions = Discussion::with('project.team', 'project.department', 'project.specialization', 'supervisor')->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            'الفريق', 'القسم', 'التخصص', 'المشرف', 'المكان', 'التاريخ', 'الوقت', 'اللجنة', 'الحالة',
        ], null, 'A1');

        $row = 2;

        foreach ($discussions as $discussion) {
            $sheet->fromArray([
                $discussion->project->team->name ?? '',
                $discussion->project->department->name ?? '',
                $discussion->project->specialization->name ?? '',
                $discussion->supervisor->name ?? '',
                $discussion->place,
                $discussion->discussion_date->toDateString(),
                $discussion->discussion_time,
                $discussion->committee,
                $discussion->status->value,
            ], null, "A{$row}");

            $row++;
        }

        return $spreadsheet;
    }
}
