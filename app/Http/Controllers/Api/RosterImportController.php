<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Api\Concerns\ImportBatchPayload;
use App\Http\Controllers\Controller;
use App\Services\RosterSyncService;
use App\Support\CurrentTerm;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RosterImportController extends Controller
{
    use ImportBatchPayload;

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->role === RoleEnum::SuperAdmin,
            403,
            'مزامنة كشف الطلبة متاحة للإدارة العامة فقط.'
        );
    }

    private function termId(CurrentTerm $currentTerm): int
    {
        $termId = $currentTerm->get();

        if (! $termId) {
            throw ValidationException::withMessages(['term_id' => 'لا يوجد فصل دراسي محدد.']);
        }

        return $termId;
    }

    /** يقرأ الملف، يحسب الفرق ويعرضه للمستخدم دون أي تغيير بالنظام */
    public function preview(Request $request, RosterSyncService $service, CurrentTerm $currentTerm)
    {
        $this->authorizeSuperAdmin($request);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
        ]);

        $termId = $this->termId($currentTerm);
        $rows = $service->parseRows($request->file('file'));

        return response()->json($service->diff($rows, $termId));
    }

    /** يطبّق المزامنة ويسجّل دفعة قابلة للتراجع */
    public function confirm(Request $request, RosterSyncService $service, CurrentTerm $currentTerm)
    {
        $this->authorizeSuperAdmin($request);

        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.university_number' => ['required', 'string'],
            'rows.*.name' => ['required', 'string'],
            'rows.*.gender' => ['nullable', 'string'],
            'rows.*.region' => ['nullable', 'string'],
            'rows.*.specialization' => ['nullable', 'string'],
            'rows.*.supervisor' => ['nullable', 'string'],
            'rows.*.section' => ['nullable', 'string'],
            'filename' => ['nullable', 'string', 'max:255'],
        ]);

        $termId = $this->termId($currentTerm);

        $result = $service->confirm(
            $data['rows'],
            $termId,
            $request->user()->id,
            $data['filename'] ?? null,
        );

        if (isset($result['invalid'])) {
            return response()->json($result, 422);
        }

        return response()->json([
            'batch' => $this->batchPayload($result['batch']),
            'summary' => $result['summary'],
        ], 201);
    }

    /** يتراجع عن آخر دفعة مطبَّقة (رجوع خطوة واحدة) */
    public function revert(Request $request, RosterSyncService $service, CurrentTerm $currentTerm)
    {
        $this->authorizeSuperAdmin($request);

        $termId = $this->termId($currentTerm);
        $batch = $service->latest($termId);

        if (! $batch || $batch->status !== 'applied') {
            throw ValidationException::withMessages([
                'batch' => 'لا توجد مزامنة قابلة للتراجع.',
            ]);
        }

        $service->revert($batch);

        return response()->json(['batch' => $this->batchPayload($batch->fresh())]);
    }

    /** حالة آخر مزامنة (لعرضها وإتاحة/تعطيل زر التراجع) */
    public function latest(Request $request, RosterSyncService $service, CurrentTerm $currentTerm)
    {
        $this->authorizeSuperAdmin($request);

        $termId = $this->termId($currentTerm);
        $batch = $service->latest($termId);

        return response()->json(['batch' => $this->batchPayload($batch)]);
    }
}
