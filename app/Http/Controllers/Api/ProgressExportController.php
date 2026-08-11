<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Services\ProgressExportService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProgressExportController extends Controller
{
    public function export(Request $request, ProgressExportService $service)
    {
        abort_unless(in_array($request->user()->role, [RoleEnum::Committee, RoleEnum::Supervisor], true), 403);

        $spreadsheet = $service->build($request->user());

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'progress.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
