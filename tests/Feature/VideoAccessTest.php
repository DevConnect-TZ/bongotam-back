<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use App\Services\ApiSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class VideoAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_listing_and_streaming_use_the_same_backend_access_rules(): void
    {
        $free = Video::create(['title' => 'Free', 'price' => 0, 'zone' => 'connection', 'video_link' => '/free.mp4']);
        $purchased = Video::create(['title' => 'Purchased', 'price' => 500, 'zone' => 'connection', 'video_link' => '/purchased.mp4']);
        $locked = Video::create(['title' => 'Locked', 'price' => 700, 'zone' => 'connection', 'video_link' => '/locked.mp4']);
        $subscriberVideo = Video::create(['title' => 'Subscriber', 'price' => 0, 'zone' => 'wakubwa', 'video_link' => '/subscriber.mp4']);
        $user = User::factory()->create([
            'unlocked_connection_videos' => [(string) $purchased->id],
            'wakubwa_subscription_expires_at' => now()->addDay(),
        ]);

        $response = $this->withToken($this->token($user))->getJson('/api/videos')->assertOk();
        $videos = collect($response->json())->keyBy('id');

        $this->assertTrue($videos[$free->id]['has_access']);
        $this->assertTrue($videos[$purchased->id]['has_access']);
        $this->assertFalse($videos[$locked->id]['has_access']);
        $this->assertTrue($videos[$subscriberVideo->id]['has_access']);
        $this->assertArrayNotHasKey('video_link', $videos[$subscriberVideo->id]);

        $this->withToken($this->token($user))->postJson("/api/videos/{$subscriberVideo->id}/stream")
            ->assertOk()
            ->assertJsonPath('url', 'http://localhost:8000/storage/subscriber.mp4');

        $user->update(['wakubwa_subscription_expires_at' => now()->subMinute()]);

        $this->withToken($this->token($user))->postJson("/api/videos/{$subscriberVideo->id}/stream")
            ->assertForbidden()
            ->assertJsonPath('access', false);
    }

    public function test_subscription_status_reports_backend_validated_access(): void
    {
        $subscriber = User::factory()->create(['wakubwa_subscription_expires_at' => now()->addDay()]);
        $expired = User::factory()->create(['wakubwa_subscription_expires_at' => now()->subDay()]);
        $admin = User::factory()->create(['role' => 'admin', 'wakubwa_subscription_expires_at' => null]);

        $this->withToken($this->token($subscriber))->getJson('/api/subscription/wakubwa/status')
            ->assertOk()
            ->assertJsonPath('subscribed', true)
            ->assertJsonPath('has_access', true);

        $this->withToken($this->token($expired))->getJson('/api/subscription/wakubwa/status')
            ->assertOk()
            ->assertJsonPath('subscribed', false)
            ->assertJsonPath('has_access', false);

        $this->withToken($this->token($admin))->getJson('/api/subscription/wakubwa/status')
            ->assertOk()
            ->assertJsonPath('subscribed', false)
            ->assertJsonPath('has_access', true);
    }

    private function token(User $user): string
    {
        return app(ApiSessionService::class)->issueSession($user, Request::create('/'))['access_token'];
    }
}
