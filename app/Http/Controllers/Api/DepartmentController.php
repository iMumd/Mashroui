<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DepartmentController extends Controller
{
    public function index()
    {
        return response()->json(Department::all());
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-org-structure');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:departments,name'],
        ]);

        return response()->json(Department::create($data), 201);
    }

    public function show(Department $department)
    {
        return response()->json($department);
    }

    public function update(Request $request, Department $department)
    {
        Gate::authorize('manage-org-structure');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:departments,name,'.$department->id],
        ]);

        $department->update($data);

        return response()->json($department);
    }

    public function destroy(Department $department)
    {
        Gate::authorize('manage-org-structure');

        $department->delete();

        return response()->json(null, 204);
    }
}
