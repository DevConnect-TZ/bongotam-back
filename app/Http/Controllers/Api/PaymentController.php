<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MobilipaService;
use App\Services\SonicPesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function createOrder(
        Request $request,
        SonicPesaService $sonicPesa,
        MobilipaService $mobilipa
    ): JsonResponse {
        $validated = $request->validate([
            'user_email' => 'nullable|email',
            'buyer_name' => 'required|string|max:255',
            'buyer_phone' => 'required|string|max:30',
            'amount' => 'required|integer|min:1',
            'currency' => 'required|string|size:3',
            'type' => 'nullable|string|max:50',
            'item_id' => 'nullable|string|max:255',
            'item_title' => 'nullable|string|max:255',
            'zone' => 'nullable|string|in:connection,wakubwa',
            'provider' => 'nullable|string|in:sonicpesa,mobilipa',
        ]);

        $authUser = $request->user();

        if (! $authUser) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $providerName = $validated['provider'] ?? $this->resolveActiveGateway();
        $service = $providerName === 'mobilipa' ? $mobilipa : $sonicPesa;

        if (! $service->isConfigured()) {
            return response()->json([
                'status' => 'error',
                'message' => ucfirst($providerName).' is not configured on the server.',
            ], 500);
        }

        $requestedEmail = strtolower((string) ($validated['user_email'] ?? $authUser->email));

        if ($authUser->role !== 'admin' && strtolower((string) $authUser->email) !== $requestedEmail) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden.',
            ], 403);
        }

        $user = $authUser->role === 'admin'
            ? User::where('email', $requestedEmail)->first()
            : $authUser;

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found.',
            ], 404);
        }

        $normalizedPhone = $service->normalizePhoneNumber($validated['buyer_phone']);

        if (! $normalizedPhone) {
            return response()->json([
                'status' => 'error',
                'message' => 'Buyer phone number must be a valid Tanzanian number.',
            ], 422);
        }

        $zone = $validated['zone'] ?? 'connection';
        $type = $validated['type'] ?? $this->defaultTransactionType($zone);

        $providerResponse = $service->createOrder([
            'buyer_email' => $user->email,
            'buyer_name' => $validated['buyer_name'],
            'buyer_phone' => $normalizedPhone,
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency']),
        ]);

        $orderId = data_get($providerResponse, 'data.order_id')
            ?? $providerResponse['order_id']
            ?? null;

        if (($providerResponse['status'] ?? 'error') !== 'success' || ! filled($orderId)) {
            return response()->json([
                'status' => 'error',
                'message' => $providerResponse['message'] ?? 'Unable to create payment order.',
                'data' => $providerResponse['data'] ?? null,
            ], (int) ($providerResponse['http_status'] ?? 502));
        }

        // SonicPesa returns fields at the top level; Mobilipa nests them under 'data'.
        $providerData = is_array($providerResponse['data'] ?? null)
            ? $providerResponse['data']
            : $providerResponse;

        $rawPaymentStatus = strtoupper((string) ($providerData['payment_status'] ?? $providerData['status'] ?? 'PENDING'));
        $paymentStatus = $this->mapTransactionStatus($rawPaymentStatus);

        $transaction = Transaction::updateOrCreate(
            ['transaction_id' => $orderId],
            [
                'user_id' => (string) $user->id,
                'user_email' => $user->email,
                'amount' => $validated['amount'],
                'currency' => strtoupper($validated['currency']),
                'type' => $type,
                'zone' => $zone,
                'item_id' => $validated['item_id'] ?? null,
                'item_title' => $validated['item_title'] ?? null,
                'status' => $paymentStatus,
                'provider' => $providerName,
                'payment_status' => $paymentStatus,
                'reference' => $providerData['reference'] ?? null,
                'buyer_name' => $validated['buyer_name'],
                'buyer_phone' => $normalizedPhone,
                'provider_transaction_id' => $providerData['transid'] ?? null,
                'channel' => $providerData['channel'] ?? null,
                'msisdn' => $providerData['msisdn'] ?? $normalizedPhone,
                'provider_payload' => $providerResponse,
            ]
        );

        if ($transaction->status === 'COMPLETED') {
            $this->unlockPurchasedVideo($transaction);
            $transaction->refresh();
        }

        return response()->json([
            'status' => 'success',
            'message' => $providerResponse['message'] ?? 'Payment order created successfully.',
            'data' => $this->statusPayload($transaction),
        ], 201);
    }

    public function status(
        string $orderId,
        SonicPesaService $sonicPesa,
        MobilipaService $mobilipa
    ): JsonResponse {
        $transaction = Transaction::where('transaction_id', $orderId)->first();
        $authUser = request()->user();

        if (! $authUser) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $transaction) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment order not found.',
            ], 404);
        }

        $belongsToUser = (string) $transaction->user_id === (string) $authUser->id
            || strtolower((string) $transaction->user_email) === strtolower((string) $authUser->email);

        if ($authUser->role !== 'admin' && ! $belongsToUser) {
            return response()->json([
                'status' => 'error',
                'message' => 'Forbidden.',
            ], 403);
        }

        $providerCheck = null;
        $providerName = $transaction->provider ?? 'sonicpesa';

        if ($transaction->status !== 'COMPLETED') {
            if ($providerName === 'mobilipa' && $mobilipa->isConfigured()) {
                $providerCheck = $mobilipa->orderStatus($orderId);

                if (($providerCheck['status'] ?? 'error') === 'success') {
                    $this->applyOrderStatusResponse($transaction, $providerCheck);
                    $transaction->refresh();
                }
            } elseif ($providerName === 'sonicpesa' && $sonicPesa->isConfigured()) {
                $providerCheck = $sonicPesa->orderStatus($orderId);

                if (($providerCheck['status'] ?? 'error') === 'success') {
                    $this->applyOrderStatusResponse($transaction, $providerCheck);
                    $transaction->refresh();
                }
            }
        }

        if ($transaction->status === 'COMPLETED' && ! $this->hasUnlockedVideo($transaction)) {
            $this->unlockPurchasedVideo($transaction);
            $transaction->refresh();
        }

        return response()->json([
            'status' => 'success',
            'message' => $providerCheck['message'] ?? 'Payment order status retrieved.',
            'data' => $this->statusPayload($transaction),
            'provider_check' => $providerCheck && ($providerCheck['status'] ?? 'error') !== 'success'
                ? [
                    'status' => $providerCheck['status'] ?? 'error',
                    'message' => $providerCheck['message'] ?? 'Unable to refresh provider status.',
                ]
                : null,
        ]);
    }

    public function webhook(Request $request, SonicPesaService $sonicPesa): JsonResponse
    {
        $payloadRaw = $request->getContent();
        $signature = $request->header('X-SonicPesa-Signature');

        if (! $sonicPesa->verifyWebhookSignature($payloadRaw, $signature)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid SonicPesa signature.',
            ], 401);
        }

        $payload = json_decode($payloadRaw, true);

        if (! is_array($payload)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid webhook payload.',
            ], 422);
        }

        $validator = Validator::make($payload, [
            'event' => 'required|string|max:100',
            'order_id' => 'required|string|max:255',
            'amount' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|size:3',
            'status' => 'required|string|max:50',
            'transid' => 'nullable|string|max:255',
            'channel' => 'nullable|string|max:100',
            'reference' => 'nullable|string|max:255',
            'msisdn' => 'nullable|string|max:30',
            'timestamp' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $transaction = Transaction::where('transaction_id', $payload['order_id'])->first();

        if (! $transaction) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found.',
            ], 404);
        }

        $rawPaymentStatus = strtoupper((string) $payload['status']);
        $mappedStatus = $this->mapTransactionStatus($rawPaymentStatus, $payload['event']);
        $transaction->fill([
            'status' => $mappedStatus,
            'payment_status' => $mappedStatus,
            'reference' => $payload['reference'] ?? $transaction->reference,
            'provider_transaction_id' => $payload['transid'] ?? $transaction->provider_transaction_id,
            'channel' => $payload['channel'] ?? $transaction->channel,
            'msisdn' => $payload['msisdn'] ?? $transaction->msisdn,
            'provider_event' => $payload['event'],
            'provider_payload' => $payload,
        ]);

        if (isset($payload['amount'])) {
            $transaction->amount = (int) $payload['amount'];
        }

        if (isset($payload['currency'])) {
            $transaction->currency = strtoupper((string) $payload['currency']);
        }

        $transaction->save();

        if ($transaction->status === 'COMPLETED') {
            $this->unlockPurchasedVideo($transaction);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook received.',
        ]);
    }

    private function applyOrderStatusResponse(Transaction $transaction, array $providerResponse): void
    {
        $providerData = data_get($providerResponse, 'data', []);
        $providerTransaction = data_get($providerResponse, 'transaction', []);

        if (! is_array($providerData)) {
            $providerData = [];
        }

        if (! is_array($providerTransaction)) {
            $providerTransaction = [];
        }

        // SonicPesa returns payment_status/amount/phone at the top level,
        // while Mobilipa nests them under 'data' or 'transaction'.
        // Check nested paths first, then fall back to top-level fields.
        $paymentStatus = $providerData['payment_status']
            ?? $providerData['status']
            ?? $providerTransaction['payment_status']
            ?? $providerTransaction['status']
            ?? $providerResponse['payment_status']
            ?? null;

        if (! filled($paymentStatus)) {
            return;
        }

        $rawPaymentStatus = strtoupper((string) $paymentStatus);
        $mappedStatus = $this->mapTransactionStatus($rawPaymentStatus);

        $transaction->fill([
            'status' => $mappedStatus,
            'payment_status' => $mappedStatus,
            'reference' => $providerData['reference'] ?? $providerResponse['reference'] ?? $transaction->reference,
            'buyer_name' => $providerTransaction['buyer_name'] ?? $transaction->buyer_name,
            'buyer_phone' => $providerData['phone'] ?? $providerResponse['phone'] ?? $transaction->buyer_phone,
            'provider_transaction_id' => $providerData['transid'] ?? $providerResponse['transid'] ?? $transaction->provider_transaction_id,
            'channel' => $providerData['channel'] ?? $providerResponse['channel'] ?? $transaction->channel,
            'msisdn' => $providerData['msisdn'] ?? $providerData['phone'] ?? $providerResponse['msisdn'] ?? $providerResponse['phone'] ?? $transaction->msisdn,
            'provider_event' => 'order_status.polled',
            'provider_payload' => $providerResponse,
        ]);

        $amount = $providerData['amount'] ?? $providerResponse['amount'] ?? null;
        if (isset($amount)) {
            $transaction->amount = (int) $amount;
        }

        $currency = $providerData['currency'] ?? $providerResponse['currency'] ?? null;
        if (isset($currency)) {
            $transaction->currency = strtoupper((string) $currency);
        }

        $transaction->save();

        if ($transaction->status === 'COMPLETED') {
            $this->unlockPurchasedVideo($transaction);
        }
    }

    private function statusPayload(Transaction $transaction): array
    {
        return [
            'order_id' => $transaction->transaction_id,
            'reference' => $transaction->reference,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'payment_status' => $transaction->status,
            'status' => $transaction->status,
            'creation_date' => optional($transaction->created_at)->format('Y-m-d H:i:s'),
            'transid' => $transaction->provider_transaction_id,
            'channel' => $transaction->channel,
            'msisdn' => $transaction->msisdn ?? $transaction->buyer_phone,
            'item_id' => $transaction->item_id,
            'item_title' => $transaction->item_title,
            'zone' => $transaction->zone,
            'access_granted' => $this->hasUnlockedVideo($transaction),
        ];
    }

    private function mapTransactionStatus(string $paymentStatus, ?string $event = null): string
    {
        $normalizedStatus = strtoupper($paymentStatus);
        $normalizedEvent = strtoupper((string) $event);

        if (in_array($normalizedStatus, ['SUCCESS', 'COMPLETED'], true) || $normalizedEvent === 'PAYMENT.COMPLETED') {
            return 'COMPLETED';
        }

        if (in_array($normalizedStatus, ['FAILED', 'CANCELLED', 'EXPIRED'], true) || $normalizedEvent === 'PAYMENT.FAILED') {
            return 'FAILED';
        }

        return 'PENDING';
    }

    private function defaultTransactionType(string $zone): string
    {
        return $zone === 'wakubwa' ? 'PURCHASE_WAKUBWA' : 'PURCHASE_CONNECTION';
    }

    private function unlockPurchasedVideo(Transaction $transaction): void
    {
        $zone = $transaction->zone === 'wakubwa' ? 'wakubwa' : 'connection';

        $user = User::find((int) $transaction->user_id);

        if (! $user && filled($transaction->user_email)) {
            $user = User::where('email', $transaction->user_email)->first();
        }

        if (! $user) {
            return;
        }

        // Subscription payments (wakubwa zone, no item_id) → activate or extend subscription
        if ($zone === 'wakubwa' && ! filled($transaction->item_id)) {
            $user->subscribeToWakubwa(1);

            return;
        }

        // Per-video unlock
        if (! filled($transaction->item_id)) {
            return;
        }

        $field = $zone === 'wakubwa' ? 'unlocked_wakubwa_videos' : 'unlocked_connection_videos';
        $current = $user->{$field} ?? [];
        $normalizedCurrent = array_map('strval', $current);
        $videoId = (string) $transaction->item_id;

        if (! in_array($videoId, $normalizedCurrent, true)) {
            $normalizedCurrent[] = $videoId;
            $user->update([$field => array_values(array_unique($normalizedCurrent))]);
        }
    }

    private function hasUnlockedVideo(Transaction $transaction): bool
    {
        if (! filled($transaction->item_id)) {
            // No item_id = subscription payment → check if user is subscribed
            if ($transaction->zone === 'wakubwa') {
                $user = User::find((int) $transaction->user_id);
                if (! $user && filled($transaction->user_email)) {
                    $user = User::where('email', $transaction->user_email)->first();
                }

                return $user ? $user->isWakubwaSubscribed() : false;
            }

            // Connection-zone transactions without an item_id are not video unlocks
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

    private function resolveActiveGateway(): string
    {
        $setting = AppSetting::where('key', 'active_payment_gateway')->first();
        $value = is_array($setting?->value) ? $setting->value : [];

        return $value['provider'] ?? 'sonicpesa';
    }
}
