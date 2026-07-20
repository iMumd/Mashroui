<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccessLevelEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserRestriction;
use App\Services\UserRestrictionService;
use App\Support\Rbac\AccessControl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserRestrictionController extends Controller
{
    public function index(Request $request, User $user, UserRestrictionService $service)
    {
        Gate::authorize('manage-restrictions');

        abort_unless($service->canView($user, $request->user()), 403);

        return response()->json($user->restrictions()->with('restrictedBy')->get());
    }

    public function store(Request $request, User $user, UserRestrictionService $service)
    {
        Gate::authorize('manage-restrictions');

        $data = $request->validate([
            'module' => ['required', Rule::in(AccessControl::MODULES)],
            'level' => ['required', Rule::in(['view_only', 'blocked'])],
        ]);

        $restriction = $service->restrict($user, $data['module'], AccessLevelEnum::from($data['level']), $request->user());

        return response()->json($restriction, 201);
    }

    public function destroy(Request $request, UserRestriction $restriction, UserRestrictionService $service)
    {
        Gate::authorize('manage-restrictions');

        $service->unrestrict($restriction, $request->user());

        return response()->json(null, 204);
    }
}
