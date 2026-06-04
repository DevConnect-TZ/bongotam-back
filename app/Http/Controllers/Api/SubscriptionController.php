<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Check if the authenticated user has an active Wakubwa subscription.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'subscribed' => $user->isWakubwaSubscribed(),
            'expires_at' => $user->wakubwa_subscription_expires_at,
        ]);
    }

    /**
     * Subscribe the authenticated user to Wakubwa Zone.
     *
     * Records the transaction and subscribes the user for 1 month.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'transaction_id' => 'required|string',
            'amount' => 'required|integer|min:1',
        ]);

        $price = $this->getSubscriptionPrice();

        // Record the transaction
        Transaction::create([
            'user_id' => $user->id,
            'user_email' => $user->email,
            'amount' => $validated['amount'],
            'currency' => 'TSHS',
            'type' => 'WAKUBWA_SUBSCRIPTION',
            'item_id' => null,
            'item_title' => 'Wakubwa Zone Monthly Subscription',
            'transaction_id' => $validated['transaction_id'],
            'status' => 'COMPLETED',
        ]);

        // Subscribe user for 1 month
        $user->subscribeToWakubwa(1);

        return response()->json([
            'subscribed' => true,
            'expires_at' => $user->fresh()->wakubwa_subscription_expires_at,
            'message' => 'Successfully subscribed to Wakubwa Zone!',
        ]);
    }

    /**
     * Get or set the Wakubwa Zone subscription price.
     * Admin only for PUT; anyone can GET.
     */
    public function price(Request $request): JsonResponse
    {
        if ($request->isMethod('put')) {
            $validated = $request->validate([
                'price' => 'required|integer|min:1',
            ]);

            AppSetting::updateOrCreate(
                ['key' => 'wakubwa_subscription_price'],
                ['value' => ['price' => $validated['price']]]
            );

            return response()->json(['price' => $validated['price']]);
        }

        return response()->json(['price' => $this->getSubscriptionPrice()]);
    }

    private function getSubscriptionPrice(): int
    {
        $setting = AppSetting::where('key', 'wakubwa_subscription_price')->first();

        if ($setting && isset($setting->value['price'])) {
            return (int) $setting->value['price'];
        }

        // Default: 3000 TSHS per month
        return 3000;
    }
}
