<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\InviteLink;
use App\Models\TeamMember;
use App\Models\User;
use App\Rules\WhatsappNumber;
use App\Support\CurrentTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request, CurrentTerm $currentTerm)
    {
        $actor = $request->user();

        if ($actor->role === RoleEnum::SuperAdmin) {
            $allowedRoles = [RoleEnum::Student->value, RoleEnum::Supervisor->value, RoleEnum::Committee->value];
        } elseif ($actor->role === RoleEnum::Committee) {
            $allowedRoles = [RoleEnum::Student->value, RoleEnum::Supervisor->value];
        } else {
            abort(403);
        }

        $data = $request->validate([
            'role' => ['required', Rule::in($allowedRoles)],
            'unassigned' => ['sometimes', 'boolean'],
        ]);

        $query = User::where('role', $data['role']);

        if ($data['role'] === RoleEnum::Student->value && $request->boolean('unassigned')) {
            $termId = $currentTerm->get();
            $assignedIds = TeamMember::whereHas('team', fn ($q) => $q->where('term_id', $termId))->pluck('student_id');
            $query->whereNotIn('id', $assignedIds);
        }

        $users = $query->orderBy('name')->get();

        return UserResource::collection($users);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeManageTarget($request, $user);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'whatsapp' => ['nullable', new WhatsappNumber],
            'employee_number' => ['nullable', 'string', 'max:30'],
            'university_number' => ['nullable', 'string', 'max:30'],
            'specialization_id' => ['nullable', 'exists:specializations,id'],
        ]);

        $user->update($data);

        return new UserResource($user);
    }

    public function updateStatus(Request $request, User $user)
    {
        $this->authorizeManageTarget($request, $user);

        $data = $request->validate([
            'status' => ['required', Rule::in([UserStatusEnum::Active->value, UserStatusEnum::Restricted->value])],
        ]);

        $user->update(['status' => $data['status']]);

        return new UserResource($user);
    }

    /**
     * سوبر أدمن يدير حسابات المشرفين ولجنة الإشراف، ولجنة الإشراف تدير حسابات الطلاب والمشرفين
     * (تواصل/إيقاف فقط — كل دور محصور بالأدوار اللي فعليًا يشرف عليها).
     */
    private function authorizeManageTarget(Request $request, User $user): void
    {
        $actor = $request->user();

        if ($actor->role === RoleEnum::SuperAdmin) {
            abort_unless(in_array($user->role, [RoleEnum::Supervisor, RoleEnum::Committee, RoleEnum::TeamLeader, RoleEnum::Student], true), 404);

            return;
        }

        if ($actor->role === RoleEnum::Committee) {
            abort_unless(in_array($user->role, [RoleEnum::Student, RoleEnum::TeamLeader, RoleEnum::Supervisor], true), 404);

            return;
        }

        abort(403);
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
