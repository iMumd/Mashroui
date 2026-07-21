<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AcademicTermController extends Controller
{
    public function index()
    {
        return response()->json(AcademicTerm::all());
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-org-structure');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        if ($data['is_current'] ?? false) {
            AcademicTerm::query()->update(['is_current' => false]);
        }

        return response()->json(AcademicTerm::create($data), 201);
    }

    public function show(AcademicTerm $academicTerm)
    {
        return response()->json($academicTerm);
    }

    public function update(Request $request, AcademicTerm $academicTerm)
    {
        Gate::authorize('manage-org-structure');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        if ($data['is_current'] ?? false) {
            AcademicTerm::query()->where('id', '!=', $academicTerm->id)->update(['is_current' => false]);
        }

        $academicTerm->update($data);

        return response()->json($academicTerm);
    }

    public function destroy(Request $request, AcademicTerm $academicTerm)
    {
        Gate::authorize('manage-org-structure');

        abort_if(
            $academicTerm->teams()->exists() || $academicTerm->projects()->exists() || $academicTerm->discussions()->exists(),
            422,
            'لا يمكن حذف الفصل الدراسي لوجود فرق أو مشاريع أو مناقشات مرتبطة به.'
        );

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'delete',
            'entity' => 'academic_term',
            'entity_id' => $academicTerm->id,
            'meta' => ['name' => $academicTerm->name],
        ]);

        $academicTerm->delete();

        return response()->json(null, 204);
    }
}
