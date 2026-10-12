<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\ComposeTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** قوالب الإعلانات والملفات والتكليفات — للإدارة العامة ولجنة الإشراف (يراها الطرفان، ويحذفها صاحبها أو الإدارة) */
class ComposeTemplateController extends Controller
{
    // GET /compose-templates?kind=announcement|file|task
    public function index(Request $request)
    {
        $this->authorizeStaff($request);

        $kind = $request->validate(['kind' => ['nullable', Rule::in(['announcement', 'file', 'task'])]])['kind'] ?? null;

        return response()->json(ComposeTemplate::with('createdBy:id,name')
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->orderByDesc('id')
            ->get());
    }

    // POST /compose-templates
    public function store(Request $request)
    {
        $this->authorizeStaff($request);

        $data = $request->validate([
            'kind' => ['required', Rule::in(['announcement', 'file', 'task'])],
            'title' => ['nullable', 'string', 'max:200', 'required_without:body'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $template = ComposeTemplate::create([...$data, 'created_by' => $request->user()->id]);

        return response()->json($template->load('createdBy:id,name'), 201);
    }

    // DELETE /compose-templates/{composeTemplate}
    public function destroy(Request $request, ComposeTemplate $composeTemplate)
    {
        $user = $request->user();
        abort_unless($user->role === RoleEnum::SuperAdmin || ($user->role === RoleEnum::Committee && $composeTemplate->created_by === $user->id), 403);

        $composeTemplate->delete();

        return response()->json(null, 204);
    }

    private function authorizeStaff(Request $request): void
    {
        abort_unless(in_array($request->user()->role, [RoleEnum::SuperAdmin, RoleEnum::Committee], true), 403);
    }
}
