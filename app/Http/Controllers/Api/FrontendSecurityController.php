<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FrontendSecurityController extends Controller
{
    private const SETTING_KEY = 'frontend_security';

    public function show(): JsonResponse
    {
        return response()->json(
            $this->payload(AppSetting::query()->find(self::SETTING_KEY))
        );
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'block_developer_tools' => 'required|boolean',
        ]);

        $setting = AppSetting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            [
                'value' => [
                    'block_developer_tools' => (bool) $validated['block_developer_tools'],
                ],
            ],
        );

        return response()->json($this->payload($setting));
    }

    private function payload(?AppSetting $setting): array
    {
        $value = is_array($setting?->value) ? $setting->value : [];

        return [
            'block_developer_tools' => (bool) ($value['block_developer_tools'] ?? false),
            'updated_at' => optional($setting?->updated_at)->toIso8601String(),
        ];
    }
}
