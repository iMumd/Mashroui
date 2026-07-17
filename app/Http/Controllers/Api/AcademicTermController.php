<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
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

    public function destroy(AcademicTerm $academicTerm)
    {
        Gate::authorize('manage-org-structure');

        $academicTerm->delete();

        return response()->json(null, 204);
    }
}
