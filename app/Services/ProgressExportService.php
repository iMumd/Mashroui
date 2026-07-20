<?php

namespace App\Services;

use App\Models\Team;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ProgressExportService
{
    public function __construct(private ProgressService $progressService) {}

    public function build(): Spreadsheet
    {
        $teams = Team::with('supervisor', 'specialization', 'project')->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            'الفريق', 'المشرف', 'التخصص', 'حالة المشروع', 'المهام المنجزة', 'إجمالي المهام', 'نسبة الإنجاز %',
        ], null, 'A1');

        $row = 2;

        foreach ($teams as $team) {
            $progress = $this->progressService->forTeam($team);

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
