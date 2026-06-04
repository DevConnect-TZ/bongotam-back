<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|string',
            'user_email' => 'nullable|email',
            'amount' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|max:10',
            'type' => 'nullable|string|max:50',
            'item_id' => 'nullable|string',
            'item_title' => 'nullable|string',
            'transaction_id' => 'nullable|string',
            'status' => 'nullable|string|max:50',
        ]);

        $transaction = Transaction::create($validated);

        return response()->json($transaction, 201);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string',
            'user_email' => 'nullable|email',
        ]);

        $query = Transaction::query();
        $authUser = $request->user();

        if (! $authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($authUser->role === 'admin') {
            if (! empty($validated['user_id'])) {
                $query->where('user_id', $validated['user_id']);
            }

            if (! empty($validated['user_email'])) {
                $query->where('user_email', strtolower((string) $validated['user_email']));
            }
        } else {
            $query->where(function ($builder) use ($authUser): void {
                $builder
                    ->where('user_id', (string) $authUser->id)
                    ->orWhere('user_email', strtolower((string) $authUser->email));
            });
        }

        $transactions = $query
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Transaction $transaction) => $this->transactionPayload($transaction));

        return response()->json($transactions);
    }

    private function transactionPayload(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'order_id' => $transaction->transaction_id,
            'transaction_id' => $transaction->transaction_id,
            'user_id' => $transaction->user_id,
            'user_email' => $transaction->user_email,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'type' => $transaction->type,
            'zone' => $transaction->zone,
            'item_id' => $transaction->item_id,
            'item_title' => $transaction->item_title,
            'status' => $transaction->status,
            'payment_status' => $transaction->payment_status ?? strtoupper((string) $transaction->status),
            'provider' => $transaction->provider,
            'reference' => $transaction->reference,
            'transid' => $transaction->provider_transaction_id,
            'channel' => $transaction->channel,
            'msisdn' => $transaction->msisdn ?? $transaction->buyer_phone,
            'access_granted' => $this->hasUnlockedVideo($transaction),
            'created_at' => optional($transaction->created_at)->toISOString(),
            'updated_at' => optional($transaction->updated_at)->toISOString(),
        ];
    }

    private function hasUnlockedVideo(Transaction $transaction): bool
    {
        if (! filled($transaction->item_id)) {
            if ($transaction->zone === 'wakubwa') {
                $user = User::find((int) $transaction->user_id);
                if (! $user && filled($transaction->user_email)) {
                    $user = User::where('email', $transaction->user_email)->first();
                }

                return $user ? $user->isWakubwaSubscribed() : false;
            }

            return false;
        }

        $user = User::find((int) $transaction->user_id);

        if (! $user && filled($transaction->user_email)) {
            $user = User::where('email', $transaction->user_email)->first();
        }

        if (! $user) {
            return false;
        }

        $zone = $transaction->zone === 'wakubwa' ? 'wakubwa' : 'connection';
        $field = $zone === 'wakubwa' ? 'unlocked_wakubwa_videos' : 'unlocked_connection_videos';
        $videoId = (string) $transaction->item_id;
        $unlockedVideos = array_map('strval', $user->{$field} ?? []);

        return in_array($videoId, $unlockedVideos, true);
    }
}
