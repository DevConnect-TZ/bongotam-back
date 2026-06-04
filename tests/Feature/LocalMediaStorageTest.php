<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateApiAccessToken;
use App\Http\Middleware\EnsureApiAdmin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocalMediaStorageTest extends TestCase
{
    protected function setUp(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required for upload image tests.');
        }

        parent::setUp();

        $this->withoutMiddleware([
            AuthenticateApiAccessToken::class,
            EnsureApiAdmin::class,
        ]);
    }

    public function test_uploads_are_stored_on_the_local_public_disk(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/uploads/image', [
            'file' => UploadedFile::fake()->image('poster.jpg'),
            'folder' => 'thumbnails',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $storedPath = $response->json('public_id');

        $this->assertSame('thumbnails', dirname($storedPath));
        Storage::disk('public')->assertExists($storedPath);
        $this->assertStringStartsWith('/storage/thumbnails/', parse_url($response->json('url'), PHP_URL_PATH));
    }

    public function test_videos_are_stored_on_the_local_public_disk(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/uploads/video', [
            'file' => UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4'),
            'folder' => 'videos',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $storedPath = $response->json('public_id');

        $this->assertSame('videos', dirname($storedPath));
        Storage::disk('public')->assertExists($storedPath);
        $this->assertStringStartsWith('/storage/videos/', parse_url($response->json('url'), PHP_URL_PATH));
    }
}
