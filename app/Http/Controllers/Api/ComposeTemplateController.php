<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\ComposeTemplate;
use App\Models\ComposeTemplateFile;
use App\Models\User;
use App\Rules\AllowedDocumentTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * قوالب الإعلانات والملفات والتكليفات (عنوان + نص + مرفقات حتى 5).
 * الإدارة العامة ولجنة الإشراف يتشاركون قوالبهم، والمشرف يرى قوالبه فقط.
 * التعديل والحذف لصاحب القالب أو للإدارة العامة.
 */
class ComposeTemplateController extends Controller
{
    private const KINDS = ['announcement', 'file', 'task'];

    private const MAX_FILES = 5;

    // GET /compose-templates?kind=announcement|file|task
    public function index(Request $request)
    {
        $user = $this->authorizeAuthor($request);

        $kind = $request->validate(['kind' => ['nullable', Rule::in(self::KINDS)]])['kind'] ?? null;

        return response()->json($this->visibleTo($user)
            ->with('createdBy:id,name,role', 'files')
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->orderByDesc('updated_at')
            ->get());
    }

    // POST /compose-templates (multipart: kind, title, body, files[])
    public function store(Request $request)
    {
        $user = $this->authorizeAuthor($request);
        $data = $this->validated($request, true);

        $template = DB::transaction(function () use ($data, $request, $user) {
            $template = ComposeTemplate::create([...collect($data)->only(['kind', 'title', 'body'])->all(), 'created_by' => $user->id]);
            $this->storeFiles($template, $request->file('files', []));

            return $template;
        });

        return response()->json($template->load('createdBy:id,name,role', 'files'), 201);
    }

    // PUT /compose-templates/{composeTemplate} (أو POST مع _method=PUT للمرفقات): title, body, files[] جديدة, remove_file_ids[]
    public function update(Request $request, ComposeTemplate $composeTemplate)
    {
        $this->authorizeOwner($request->user(), $composeTemplate);
        $data = $this->validated($request, false);

        $removed = $composeTemplate->files()->whereIn('id', $data['remove_file_ids'] ?? [])->get();
        $newFiles = $request->file('files', []);

        if ($composeTemplate->files()->count() - $removed->count() + count($newFiles) > self::MAX_FILES) {
            throw ValidationException::withMessages(['files' => 'الحد الأقصى '.self::MAX_FILES.' مرفقات للقالب.']);
        }

        DB::transaction(function () use ($composeTemplate, $data, $removed, $newFiles) {
            $composeTemplate->update(collect($data)->only(['title', 'body'])->all());
            ComposeTemplateFile::whereIn('id', $removed->pluck('id'))->delete();
            $this->storeFiles($composeTemplate, $newFiles);
            $composeTemplate->touch();
        });

        Storage::delete($removed->pluck('path')->all());

        return response()->json($composeTemplate->load('createdBy:id,name,role', 'files'));
    }

    // DELETE /compose-templates/{composeTemplate}
    public function destroy(Request $request, ComposeTemplate $composeTemplate)
    {
        $this->authorizeOwner($request->user(), $composeTemplate);

        $paths = $composeTemplate->files()->pluck('path')->all();
        $composeTemplate->delete();
        Storage::delete($paths);

        return response()->json(null, 204);
    }

    // GET /compose-templates/files/{file}/download — لمن يرى القالب
    public function downloadFile(Request $request, ComposeTemplateFile $file)
    {
        $user = $this->authorizeAuthor($request);
        abort_unless($this->visibleTo($user)->whereKey($file->compose_template_id)->exists(), 403);

        return Storage::download($file->path, $file->name);
    }

    /** المشرف: قوالبه فقط · الإدارة واللجنة: قوالب الإدارة واللجنة */
    private function visibleTo(User $user): Builder
    {
        return ComposeTemplate::query()->when($user->role === RoleEnum::Supervisor,
            fn ($q) => $q->where('created_by', $user->id),
            fn ($q) => $q->whereHas('createdBy', fn ($u) => $u->whereIn('role', [RoleEnum::SuperAdmin, RoleEnum::Committee])));
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'kind' => [$creating ? 'required' : 'prohibited', Rule::in(self::KINDS)],
            'title' => ['nullable', 'string', 'max:200', 'required_without:body'],
            'body' => ['nullable', 'string', 'max:2000'],
            'files' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'files.*' => ['file', 'mimes:'.AllowedDocumentTypes::MIMES, 'max:10240'],
            'remove_file_ids' => [$creating ? 'prohibited' : 'nullable', 'array'],
            'remove_file_ids.*' => ['integer'],
        ]);
    }

    private function storeFiles(ComposeTemplate $template, array $files): void
    {
        foreach ($files as $file) {
            $template->files()->create([
                'name' => $file->getClientOriginalName(),
                'path' => Storage::putFile("compose-templates/{$template->id}", $file),
                'size' => $file->getSize(),
            ]);
        }
    }

    private function authorizeAuthor(Request $request): User
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [RoleEnum::SuperAdmin, RoleEnum::Committee, RoleEnum::Supervisor], true), 403);

        return $user;
    }

    private function authorizeOwner(User $user, ComposeTemplate $template): void
    {
        abort_unless($user->role === RoleEnum::SuperAdmin || $template->created_by === $user->id, 403);
    }
}
