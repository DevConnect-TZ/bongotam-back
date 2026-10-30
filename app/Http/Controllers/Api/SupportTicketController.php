<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupportTicketRequest;
use App\Http\Requests\UpdateSupportTicketRequest;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(SupportTicket::query()->where('user_id', $request->user()->id)->latest('id')->paginate(20));
    }

    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        $ticket = SupportTicket::create([...$request->validated(), 'user_id' => $request->user()->id]);

        return response()->json($ticket, 201);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $validated = $request->validate(['status' => ['nullable', 'in:open,in_progress,resolved']]);
        $tickets = SupportTicket::query()->with(['user:id,name,email', 'transaction:id,transaction_id,status', 'video:id,title'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')->paginate(20);

        return response()->json($tickets);
    }

    public function update(UpdateSupportTicketRequest $request, string $ticket): JsonResponse
    {
        $ticket = SupportTicket::query()->findOrFail($ticket);
        $ticket->update($request->validated());

        return response()->json($ticket->load(['user:id,name,email', 'transaction:id,transaction_id,status', 'video:id,title']));
    }
}
