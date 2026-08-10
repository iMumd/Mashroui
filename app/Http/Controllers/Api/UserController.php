<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\InviteLink;
use App\Models\User;
use App\Rules\WhatsappNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === RoleEnum::SuperAdmin, 403);

        $data = $request->validate([
            'role' => ['required', Rule::in([RoleEnum::Supervisor->value, RoleEnum::Committee->value])],
        ]);

        $users = User::where('role', $data['role'])->orderBy('name')->get();

        return UserResource::collection($users);
    }

    public function update(Request $request, User $user)
    {
        abort_unless($request->user()->role === RoleEnum::SuperAdmin, 403);
        abort_unless(in_array($user->role, [RoleEnum::Supervisor, RoleEnum::Committee], true), 404);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'whatsapp' => ['nullable', new WhatsappNumber],
            'employee_number' => ['nullable', 'string', 'max:30'],
            'specialization_id' => ['nullable', 'exists:specializations,id'],
        ]);

        $user->update($data);

        return new UserResource($user);
    }

    public function updateStatus(Request $request, User $user)
    {
        abort_unless($request->user()->role === RoleEnum::SuperAdmin, 403);
        abort_unless(in_array($user->role, [RoleEnum::Supervisor, RoleEnum::Committee], true), 404);

        $data = $request->validate([
            'status' => ['required', Rule::in([UserStatusEnum::Active->value, UserStatusEnum::Restricted->value])],
        ]);

        $user->update(['status' => $data['status']]);

        return new UserResource($user);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->role === RoleEnum::SuperAdmin, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', Rule::in([RoleEnum::Supervisor->value, RoleEnum::Committee->value])],
            'whatsapp' => ['nullable', new WhatsappNumber],
            'employee_number' => ['nullable', 'string', 'max:30'],
            'specialization_id' => ['nullable', 'exists:specializations,id'],
        ]);

        $result = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::random(32),
                'role' => $data['role'],
                'whatsapp' => $data['whatsapp'] ?? null,
                'employee_number' => $data['employee_number'] ?? null,
                'specialization_id' => $data['specialization_id'] ?? null,
                'status' => UserStatusEnum::Active,
                'must_change_password' => true,
            ]);

            $invite = InviteLink::create([
                'user_id' => $user->id,
                'token' => Str::random(64),
                'expires_at' => now()->addDays(3),
            ]);

            return ['user' => $user, 'invite_token' => $invite->token, 'expires_at' => $invite->expires_at];
        });

        return response()->json($result, 201);
    }
}
