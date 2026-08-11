<?php

namespace App\Services;

use App\Models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ProgressExportService
{
    public function __construct(private ProgressService $progressService) {}

    public function build(User $user): Spreadsheet
    {
        $rows = $this->progressService->overview($user);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            'الفريق', 'المشرف', 'التخصص', 'حالة المشروع', 'المهام المنجزة', 'إجمالي المهام', 'نسبة الإنجاز %',
        ], null, 'A1');

        $row = 2;

        foreach ($rows as $entry) {
            $team = $entry['team'];
            $progress = $entry['progress'];

            $sheet->fromArray([
                $team->name,
                $team->supervisor->name ?? '',
                $team->specialization->name ?? '',
                $team->project->status->value ?? '',
                $progress['done'],
                $progress['total'],
                $progress['percentage'],
            ], null, "A{$row}");

            $row++;
        }

        return $spreadsheet;
    }
}
