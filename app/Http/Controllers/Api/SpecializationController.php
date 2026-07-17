<?php

namespace App\Http\Controllers\Api;

use App\Enums\DegreeEnum;
use App\Http\Controllers\Controller;
use App\Models\Specialization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SpecializationController extends Controller
{
    public function index()
    {
        return response()->json(Specialization::with('department')->get());
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-org-structure');

        $data = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:150'],
            'degree' => ['required', Rule::enum(DegreeEnum::class)],
        ]);

        return response()->json(Specialization::create($data), 201);
    }

    public function show(Specialization $specialization)
    {
        return response()->json($specialization->load('department'));
    }

    public function update(Request $request, Specialization $specialization)
    {
        Gate::authorize('manage-org-structure');

        $data = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:150'],
            'degree' => ['required', Rule::enum(DegreeEnum::class)],
        ]);

        $specialization->update($data);

        return response()->json($specialization);
    }

    public function destroy(Specialization $specialization)
    {
        Gate::authorize('manage-org-structure');

        $specialization->delete();

        return response()->json(null, 204);
    }
}
