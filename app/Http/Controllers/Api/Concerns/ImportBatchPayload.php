<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\ImportBatch;

/** تمثيل موحّد لدفعة استيراد في استجابات الـ API (كشف الطلبة / الفرق / المناقشات) */
trait ImportBatchPayload
{
    protected function batchPayload(?ImportBatch $batch): ?array
    {
        if (! $batch) {
            return null;
        }

        return [
            'id' => $batch->id,
            'kind' => $batch->kind,
            'original_filename' => $batch->original_filename,
            'summary' => $batch->summary,
            'status' => $batch->status,
            'can_revert' => $batch->status === 'applied',
            'applied_at' => $batch->applied_at?->toIso8601String(),
            'reverted_at' => $batch->reverted_at?->toIso8601String(),
        ];
    }
}
