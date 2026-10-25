<?php

namespace Tests\Unit;

use App\Services\FirebaseAdminService;
use App\Services\FirebaseIdentityService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
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

        $service = new FirebaseIdentityService;
        $identity = $service->lookupByIdToken('sample-token');

        $this->assertSame('person@example.com', $identity['email'] ?? null);

        Http::assertSent(function ($request) {
            return str_contains(
                $request->url(),
                'key=AIzaSyB3L65yGO42EjY_CwuSCUML6iF84l1QDgE'
            );
        });
    }

    public function test_admin_service_deletes_a_firebase_user_found_by_email(): void
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($privateKey);
        $this->assertTrue(openssl_pkey_export($privateKey, $privateKeyPem));

        config([
            'services.firebase.project_id' => 'demo-project',
            'services.firebase.client_email' => 'firebase-admin@example.test',
            'services.firebase.private_key' => $privateKeyPem,
        ]);
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'admin-token'], 200),
            'https://identitytoolkit.googleapis.com/v1/projects/demo-project/accounts:lookup' => Http::response([
                'users' => [['localId' => 'firebase-user-1']],
            ], 200),
            'https://identitytoolkit.googleapis.com/v1/projects/demo-project/accounts:delete' => Http::response([], 200),
        ]);

        (new FirebaseAdminService)->deleteUserByEmail('Person@Example.com');

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://identitytoolkit.googleapis.com/v1/projects/demo-project/accounts:lookup'
            && $request['email'] === ['person@example.com']
            && $request->hasHeader('Authorization', 'Bearer admin-token'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://identitytoolkit.googleapis.com/v1/projects/demo-project/accounts:delete'
            && $request['localId'] === 'firebase-user-1'
            && $request->hasHeader('Authorization', 'Bearer admin-token'));
    }
}
