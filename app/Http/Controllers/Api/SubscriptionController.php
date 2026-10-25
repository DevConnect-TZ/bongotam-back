<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
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

        $isSubscribed = $user->isWakubwaSubscribed();

        return response()->json([
            'subscribed' => $isSubscribed,
            'has_access' => $user->role === 'admin' || $isSubscribed,
            'expires_at' => $user->wakubwa_subscription_expires_at,
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Subscriptions are activated only after a verified payment. Please create a payment order.',
        ], 409);
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

    /**
     * Get all users who have subscribed or have subscription records for Wakubwa Zone.
     * Admin only.
     */
    public function subscribers(Request $request): JsonResponse
    {
        $subscribers = User::whereNotNull('wakubwa_subscription_expires_at')
            ->orderBy('wakubwa_subscription_expires_at', 'desc')
            ->get(['id', 'name', 'email', 'role', 'status', 'wakubwa_subscription_expires_at', 'created_at', 'last_login'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'is_active' => $user->isWakubwaSubscribed(),
                    'expires_at' => optional($user->wakubwa_subscription_expires_at)->toIso8601String(),
                    'created_at' => optional($user->created_at)->toIso8601String(),
                    'last_login' => optional($user->last_login)->toIso8601String(),
                ];
            });

        return response()->json($subscribers);
    }

    /**
     * Grant or extend Wakubwa Zone subscription for a user (Admin only).
     */
    public function grantSubscription(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'months' => 'nullable|integer|min:1|max:'.User::MAX_SUBSCRIPTION_MONTHS,
        ]);

        $user = User::findOrFail($id);
        $months = (int) ($validated['months'] ?? User::MAX_SUBSCRIPTION_MONTHS);

        $user->subscribeToWakubwa($months);
        $user->refresh();

        return response()->json([
            'status' => 'success',
            'message' => "Subscription granted to {$user->email} for {$months} month(s).",
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'is_active' => $user->isWakubwaSubscribed(),
                'expires_at' => optional($user->wakubwa_subscription_expires_at)->toIso8601String(),
            ],
        ]);
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
