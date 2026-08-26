<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Jobs\SendRelayEmail;
use Illuminate\Http\Request;

class EmailRelayController extends Controller
{
    public function send(Request $request)
    {
        abort_unless(
            in_array($request->user()->role, [RoleEnum::Supervisor, RoleEnum::Committee, RoleEnum::SuperAdmin], true),
            403
        );

        $data = $request->validate([
            'to' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        SendRelayEmail::dispatch($data['to'], $data['subject'], $data['message']);

        return response()->json(['status' => 'queued']);
    }
}
