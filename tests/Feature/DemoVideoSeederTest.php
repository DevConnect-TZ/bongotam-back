<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use App\Services\ApiSessionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DemoVideoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_videos_are_listed_in_both_zones_without_exposing_stream_urls(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::factory()->create();
        $issued = app(ApiSessionService::class)->issueSession($user, Request::create('/'));
        $this->withToken($issued['access_token']);

        foreach (['connection', 'wakubwa'] as $zone) {
            $this->getJson('/api/videos?zone='.$zone)
                ->assertOk()
                ->assertJsonCount(6)
                ->assertJsonPath('0.zone', $zone)
                ->assertJsonMissingPath('0.video_link');

            foreach (Video::where('zone', $zone)->get() as $video) {
                $this->assertNotEmpty($video->thumbnail);
                $this->assertStringEndsWith('.mp4', $video->video_link);
            }
        }

        $this->assertTrue(Video::where('zone', 'connection')->where('price', 0)->exists());
        $this->assertFalse(Video::where('zone', 'wakubwa')->where('price', 0)->exists());
    }

    public function test_reseeding_preserves_ids_and_unrelated_videos(): void
    {
        $this->seed(DatabaseSeeder::class);
        $originalIds = Video::orderBy('id')->pluck('id')->all();
        $existingVideo = Video::create([
            'title' => 'Existing uploaded video',
            'zone' => 'connection',
            'price' => 1200,
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('videos', 13);
        $this->assertSame($originalIds, Video::where('id', '!=', $existingVideo->id)->orderBy('id')->pluck('id')->all());
        $this->assertModelExists($existingVideo);
        $this->assertSame(1200, $existingVideo->fresh()->price);
    }
}
