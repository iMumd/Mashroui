<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Policies\TicketPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    // GET /tickets — الدعم يرى الكل، وغيره يرى تذاكره فقط
    public function index(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'status' => ['sometimes', Rule::enum(TicketStatusEnum::class)],
        ]);

        // آخر رسالة (صاحبها + مقتطف) لتمييز "بانتظار الرد" وعرض معاينة في القائمة
        $last = fn (string $col) => TicketReply::select($col)->whereColumn('ticket_id', 'tickets.id')->latest('id')->limit(1);

        $query = Ticket::with('user:id,name,role')->withCount('replies')
            ->addSelect(['last_reply_user_id' => $last('user_id'), 'last_reply_body' => $last('body')])
            ->latest();

        if (! app(TicketPolicy::class)->isSupportStaff($user)) {
            $query->where('user_id', $user->id);
        }

        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        return response()->json($query->get());
    }

    // POST /tickets — فتح تذكرة بعنوان ورسالة أولى
    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $ticket = DB::transaction(function () use ($request, $data) {
            $ticket = Ticket::create([
                'user_id' => $request->user()->id,
                'subject' => $data['subject'],
                'status' => TicketStatusEnum::Open,
                'last_reply_at' => now(),
            ]);

            $ticket->replies()->create([
                'user_id' => $request->user()->id,
                'body' => $data['body'],
            ]);

            return $ticket;
        });

        return response()->json($ticket->load('replies.user:id,name,role', 'user:id,name,role'), 201);
    }

    public function show(Ticket $ticket)
    {
        Gate::authorize('view', $ticket);

        return response()->json($ticket->load('replies.user:id,name,role', 'user:id,name,role', 'closedBy:id,name', 'archivedBy:id,name'));
    }

    // POST /tickets/{ticket}/replies — رد على تذكرة مفتوحة
    public function reply(Request $request, Ticket $ticket)
    {
        Gate::authorize('reply', $ticket);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $reply = DB::transaction(function () use ($request, $ticket, $data) {
            $reply = $ticket->replies()->create([
                'user_id' => $request->user()->id,
                'body' => $data['body'],
            ]);

            $ticket->update(['last_reply_at' => now()]);

            return $reply;
        });

        return response()->json($reply->load('user:id,name,role'), 201);
    }

    // POST /tickets/{ticket}/close
    public function close(Request $request, Ticket $ticket)
    {
        Gate::authorize('close', $ticket);

        $ticket->update([
            'status' => TicketStatusEnum::Closed,
            'closed_at' => now(),
            'closed_by' => $request->user()->id,
        ]);

        return response()->json($ticket->fresh());
    }

    // POST /tickets/{ticket}/reopen — للدعم فقط
    public function reopen(Ticket $ticket)
    {
        Gate::authorize('reopen', $ticket);

        $ticket->update([
            'status' => TicketStatusEnum::Open,
            'closed_at' => null,
            'closed_by' => null,
            'archived_at' => null,
            'archived_by' => null,
        ]);

        return response()->json($ticket->fresh());
    }

    // POST /tickets/{ticket}/archive — بعد رد الدعم: تُغلق وتخرج من الوارد
    public function archive(Request $request, Ticket $ticket)
    {
        Gate::authorize('archive', $ticket);

        $ticket->update([
            'status' => TicketStatusEnum::Closed,
            'closed_at' => $ticket->closed_at ?? now(),
            'closed_by' => $ticket->closed_by ?? $request->user()->id,
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);

        return response()->json($ticket->fresh());
    }

    // POST /tickets/{ticket}/unarchive — تعود للمغلقة
    public function unarchive(Ticket $ticket)
    {
        Gate::authorize('unarchive', $ticket);

        $ticket->update(['archived_at' => null, 'archived_by' => null]);

        return response()->json($ticket->fresh());
    }

    // DELETE /tickets/{ticket} — حذف نهائي مع الردود (cascade)
    public function destroy(Ticket $ticket)
    {
        Gate::authorize('delete', $ticket);

        $ticket->delete();

        return response()->noContent();
    }
}
