<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateApiAccessToken;
use App\Http\Middleware\EnsureApiAdmin;
use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendSecurityControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required for database-backed frontend security tests.');
        }

        parent::setUp();

        $this->withoutMiddleware([
            AuthenticateApiAccessToken::class,
            EnsureApiAdmin::class,
        ]);
    }

    public function test_it_returns_open_developer_tools_by_default(): void
    {
        $this->getJson('/api/frontend/security')
            ->assertOk()
            ->assertJson([
                'block_developer_tools' => false,
                'updated_at' => null,
            ]);
    }

    public function test_it_updates_the_frontend_security_setting(): void
    {
        $response = $this->putJson('/api/frontend/security', [
            'block_developer_tools' => true,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'block_developer_tools' => true,
            ]);

        $setting = AppSetting::query()->find('frontend_security');

        $this->assertNotNull($setting);
        $this->assertTrue((bool) ($setting->value['block_developer_tools'] ?? false));
    }
}
