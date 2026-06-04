<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateApiAccessToken;
use App\Http\Middleware\EnsureApiAdmin;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocalMediaCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is required for database-backed media cleanup tests.');
        }

        parent::setUp();

        $this->withoutMiddleware([
            AuthenticateApiAccessToken::class,
            EnsureApiAdmin::class,
        ]);
    }

    public function test_deleting_a_video_also_deletes_its_local_files(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('thumbnails/video-thumb.jpg', 'thumb');
        Storage::disk('public')->put('videos/video-file.mp4', 'video');

        $video = Video::create([
            'title' => 'Server Stored Video',
            'thumbnail' => Storage::disk('public')->url('thumbnails/video-thumb.jpg'),
            'video_link' => Storage::disk('public')->url('videos/video-file.mp4'),
            'price' => 0,
            'rating' => '18+',
            'zone' => 'connection',
            'views' => 0,
        ]);

        $this->assertSame('thumbnails/video-thumb.jpg', $video->fresh()->getRawOriginal('thumbnail'));
        $this->assertSame('videos/video-file.mp4', $video->fresh()->getRawOriginal('video_link'));

        $response = $this->deleteJson('/api/videos/'.$video->id);

        $response->assertOk()->assertJson([
            'message' => 'Video deleted',
        ]);

        Storage::disk('public')->assertMissing('thumbnails/video-thumb.jpg');
        Storage::disk('public')->assertMissing('videos/video-file.mp4');
        $this->assertDatabaseMissing('videos', ['id' => $video->id]);
    }

    public function test_updating_media_replaces_old_local_files_on_the_server(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('thumbnails/original-thumb.jpg', 'thumb');
        Storage::disk('public')->put('videos/original-video.mp4', 'video');
        Storage::disk('public')->put('thumbnails/new-thumb.jpg', 'new thumb');
        Storage::disk('public')->put('videos/new-video.mp4', 'new video');

        $video = Video::create([
            'title' => 'Original Video',
            'thumbnail' => Storage::disk('public')->url('thumbnails/original-thumb.jpg'),
            'video_link' => Storage::disk('public')->url('videos/original-video.mp4'),
            'price' => 1000,
            'rating' => '5 stars',
            'zone' => 'connection',
            'views' => 0,
        ]);

        $response = $this->putJson('/api/videos/'.$video->id, [
            'title' => 'Updated Video',
            'thumbnail' => Storage::disk('public')->url('thumbnails/new-thumb.jpg'),
            'video_link' => Storage::disk('public')->url('videos/new-video.mp4'),
            'price' => 0,
            'rating' => '18+',
            'zone' => 'connection',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'title' => 'Updated Video',
            ]);

        Storage::disk('public')->assertMissing('thumbnails/original-thumb.jpg');
        Storage::disk('public')->assertMissing('videos/original-video.mp4');
        Storage::disk('public')->assertExists('thumbnails/new-thumb.jpg');
        Storage::disk('public')->assertExists('videos/new-video.mp4');

        $video->refresh();

        $this->assertSame('thumbnails/new-thumb.jpg', $video->getRawOriginal('thumbnail'));
        $this->assertSame('videos/new-video.mp4', $video->getRawOriginal('video_link'));
        $this->assertSame(Storage::disk('public')->url('thumbnails/new-thumb.jpg'), $video->thumbnail);
        $this->assertSame(Storage::disk('public')->url('videos/new-video.mp4'), $video->video_link);
    }
}
