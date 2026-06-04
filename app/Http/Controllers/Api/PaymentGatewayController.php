<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
    private const ACTIVE_GATEWAY_KEY = 'active_payment_gateway';

    private const ALLOWED_GATEWAYS = ['sonicpesa', 'mobilipa'];

    /**
     * Get the currently active payment gateway.
     */
    public function show(): JsonResponse
    {
        $setting = AppSetting::where('key', self::ACTIVE_GATEWAY_KEY)->first();
        $value = is_array($setting?->value) ? $setting->value : [];
        $provider = $value['provider'] ?? 'sonicpesa';

        return response()->json([
            'active_provider' => in_array($provider, self::ALLOWED_GATEWAYS, true) ? $provider : 'sonicpesa',
            'available_gateways' => self::ALLOWED_GATEWAYS,
        ]);
    }

    /**
     * Set the active payment gateway (admin only).
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|string|in:'.implode(',', self::ALLOWED_GATEWAYS),
        ]);

        AppSetting::updateOrCreate(
            ['key' => self::ACTIVE_GATEWAY_KEY],
            ['value' => ['provider' => $validated['provider']]]
        );

        return response()->json([
            'status' => 'success',
            'active_provider' => $validated['provider'],
            'message' => 'Payment gateway switched to '.ucfirst($validated['provider']).'.',
        ]);
    }
}
