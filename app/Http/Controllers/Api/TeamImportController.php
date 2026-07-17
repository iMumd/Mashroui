<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\TeamImportService;
use App\Support\CurrentTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TeamImportController extends Controller
{
    public function preview(Request $request, TeamImportService $service)
    {
        Gate::authorize('create', Team::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
        ]);

        $rows = $service->parseRows($request->file('file'));

        return response()->json($service->validate($rows));
    }

    public function confirm(Request $request, TeamImportService $service, CurrentTerm $currentTerm)
    {
        Gate::authorize('create', Team::class);

        $data = $request->validate([
            'specialization_id' => ['required', 'exists:specializations,id'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.name' => ['required', 'string'],
            'rows.*.email' => ['required', 'string'],
            'rows.*.university_number' => ['required', 'string'],
            'rows.*.whatsapp' => ['required', 'string'],
        ]);

        $termId = $currentTerm->get();

        if (! $termId) {
            throw ValidationException::withMessages(['term_id' => 'لا يوجد فصل دراسي محدد.']);
        }

        $result = $service->confirm($data['rows'], $data['specialization_id'], $termId);

        return response()->json($result, isset($result['created']) ? 201 : 422);
    }
}
