<?php

namespace Database\Seeders;

use App\Models\Video;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $demoVideos = [
            [
                'title' => 'Mobilipa Test Video #1 - Bongo Flava Exclusive',
                'thumbnail' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=800',
                'video_link' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
                'price' => 400,
                'rating' => '4.9',
                'zone' => 'connection',
                'views' => 150,
            ],
            [
                'title' => 'Mobilipa Test Video #2 - Dar Live Concert Highlights',
                'thumbnail' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=800',
                'video_link' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
                'price' => 400,
                'rating' => '4.8',
                'zone' => 'connection',
                'views' => 230,
            ],
            [
                'title' => 'Mobilipa Test Video #3 - Bongo Episode Preview',
                'thumbnail' => 'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?w=800',
                'video_link' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
                'price' => 400,
                'rating' => '5.0',
                'zone' => 'connection',
                'views' => 410,
            ],
            [
                'title' => 'Mobilipa Test Video #4 - Comedy Special Clip',
                'thumbnail' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=800',
                'video_link' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4',
                'price' => 400,
                'rating' => '4.7',
                'zone' => 'connection',
                'views' => 95,
            ],
            [
                'title' => 'Mobilipa Test Video #5 - Special VIP Episode',
                'thumbnail' => 'https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?w=800',
                'video_link' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerFun.mp4',
                'price' => 400,
                'rating' => '4.9',
                'zone' => 'connection',
                'views' => 320,
            ],
        ];

        foreach ($demoVideos as $data) {
            Video::updateOrCreate(
                ['title' => $data['title']],
                $data
            );
        }
    }
}
