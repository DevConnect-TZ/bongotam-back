<?php

namespace Tests\Unit;

use App\Services\FirebaseIdentityService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebaseIdentityServiceTest extends TestCase
{
    public function test_it_uses_the_shipped_public_web_api_key_when_env_config_is_blank(): void
    {
        config([
            'services.firebase.web_api_key' => '',
        ]);

        Http::fake([
            'https://identitytoolkit.googleapis.com/*' => Http::response([
                'users' => [[
                    'localId' => 'firebase-user-1',
                    'email' => 'person@example.com',
                    'displayName' => 'Person Example',
                    'emailVerified' => true,
                ]],
            ], 200),
        ]);

        $service = new FirebaseIdentityService();
        $identity = $service->lookupByIdToken('sample-token');

        $this->assertSame('person@example.com', $identity['email'] ?? null);

        Http::assertSent(function ($request) {
            return str_contains(
                $request->url(),
                'key=AIzaSyANb1ccCko2x-7KxUEV2DzuTM09EMBjLyQ'
            );
        });
    }
}
