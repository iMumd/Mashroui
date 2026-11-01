<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ImportBatchPayload;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\TeamImportService;
use App\Support\CurrentTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TeamImportController extends Controller
{
    use ImportBatchPayload;

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
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.name' => ['required', 'string'],
            'rows.*.email' => ['required', 'string'],
            'rows.*.university_number' => ['required', 'string'],
            'rows.*.whatsapp' => ['required', 'string'],
            'rows.*.specialization' => ['nullable', 'string'],
            'rows.*.specialization_id' => ['required', 'integer', 'exists:specializations,id'],
            'rows.*.section' => ['nullable', 'string'],
            'rows.*.supervisor' => ['nullable', 'string'],
            'rows.*.supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'filename' => ['nullable', 'string', 'max:255'],
        ]);

        $termId = $currentTerm->get();

        if (! $termId) {
            throw ValidationException::withMessages(['term_id' => 'لا يوجد فصل دراسي محدد.']);
        }

        $result = $service->confirm($data['rows'], $termId, $request->user()->id, $data['filename'] ?? null);

        return response()->json($result, isset($result['created']) ? 201 : 422);
    }

    /** حالة آخر استيراد فرق (لإتاحة/تعطيل زر التراجع) */
    public function latest(Request $request, TeamImportService $service, CurrentTerm $currentTerm)
    {
        Gate::authorize('create', Team::class);

        return response()->json(['batch' => $this->batchPayload($service->latest($currentTerm->get()))]);
    }

    /** يتراجع عن آخر استيراد فرق (رجوع خطوة واحدة) */
    public function revert(Request $request, TeamImportService $service, CurrentTerm $currentTerm)
    {
        Gate::authorize('create', Team::class);

        $batch = $service->latest($currentTerm->get());

        if (! $batch || $batch->status !== 'applied') {
            throw ValidationException::withMessages(['batch' => 'لا يوجد استيراد قابل للتراجع.']);
        }

        $service->revert($batch);

        return response()->json(['batch' => $this->batchPayload($batch->fresh())]);
    }
}
