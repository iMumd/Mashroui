<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discussion;
use App\Services\DiscussionImportService;
use App\Support\CurrentTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DiscussionImportController extends Controller
{
    public function template(DiscussionImportService $service, CurrentTerm $currentTerm)
    {
        Gate::authorize('create', Discussion::class);

        $termId = $currentTerm->get();

        if (! $termId) {
            throw ValidationException::withMessages(['term_id' => 'لا يوجد فصل دراسي محدد.']);
        }

        $spreadsheet = $service->buildTemplate($termId);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'قالب-استيراد-مواعيد-المناقشات.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function preview(Request $request, DiscussionImportService $service, CurrentTerm $currentTerm)
    {
        Gate::authorize('create', Discussion::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
        ]);

        $termId = $currentTerm->get();

        if (! $termId) {
            throw ValidationException::withMessages(['term_id' => 'لا يوجد فصل دراسي محدد.']);
        }

        $rows = $service->parseRows($request->file('file'));

        return response()->json($service->validate($rows, $termId));
    }

    public function confirm(Request $request, DiscussionImportService $service, CurrentTerm $currentTerm)
    {
        Gate::authorize('create', Discussion::class);

        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.team_id' => ['nullable', 'string'],
            'rows.*.team_name' => ['nullable', 'string'],
            'rows.*.place' => ['required', 'string'],
            'rows.*.discussion_date' => ['required', 'string'],
            'rows.*.discussion_time' => ['required', 'string'],
            'rows.*.committee' => ['required', 'string'],
            'rows.*.whatsapp' => ['nullable', 'string'],
            'rows.*.project_id' => ['required', 'integer'],
            'rows.*.supervisor_id' => ['required', 'integer'],
        ]);

        $termId = $currentTerm->get();

        if (! $termId) {
            throw ValidationException::withMessages(['term_id' => 'لا يوجد فصل دراسي محدد.']);
        }

        $result = $service->confirm($data['rows'], $termId);

        return response()->json($result, isset($result['created']) ? 201 : 422);
    }
}
