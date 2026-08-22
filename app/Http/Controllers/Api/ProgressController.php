<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Services\ProgressService;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    // GET /progress — نسبة تقدّم الفرق (لجنة: الكل، مشرف: فرقه فقط)
    public function index(Request $request, ProgressService $service)
    {
        abort_unless(in_array($request->user()->role, [RoleEnum::Committee, RoleEnum::Supervisor, RoleEnum::SuperAdmin], true), 403);

        return response()->json($service->overview($request->user()));
    }
}
