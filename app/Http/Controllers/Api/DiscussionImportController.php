<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discussion;
use App\Services\DiscussionImportService;
use App\Support\CurrentTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DiscussionImportController extends Controller
{
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
            'rows.*.team_name' => ['required', 'string'],
            'rows.*.place' => ['required', 'string'],
            'rows.*.discussion_date' => ['required', 'string'],
            'rows.*.discussion_time' => ['required', 'string'],
            'rows.*.committee' => ['required', 'string'],
            'rows.*.whatsapp' => ['nullable', 'string'],
        ]);

        $termId = $currentTerm->get();

        if (! $termId) {
            throw ValidationException::withMessages(['term_id' => 'لا يوجد فصل دراسي محدد.']);
        }

        $result = $service->confirm($data['rows'], $termId);

        return response()->json($result, isset($result['created']) ? 201 : 422);
    }
}
