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
                'title' => 'Demo - Big Buck Bunny Trailer',
                'thumbnail' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=800',
                'video_link' => 'https://media.w3.org/2010/05/bunny/trailer.mp4',
                'price' => 400,
                'rating' => '4.9',
                'zone' => 'connection',
                'views' => 150,
            ],
            [
                'title' => 'Demo - Sintel Trailer',
                'thumbnail' => 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=800',
                'video_link' => 'https://media.w3.org/2010/05/sintel/trailer.mp4',
                'price' => 400,
                'rating' => '4.8',
                'zone' => 'connection',
                'views' => 230,
            ],
            [
                'title' => 'Demo - Ocean Life',
                'thumbnail' => 'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?w=800',
                'video_link' => 'https://vjs.zencdn.net/v/oceans.mp4',
                'price' => 400,
                'rating' => '5.0',
                'zone' => 'connection',
                'views' => 410,
            ],
            [
                'title' => 'Demo - Big Buck Bunny Animation Preview',
                'thumbnail' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=800',
                'video_link' => 'https://media.w3.org/2010/05/bunny/trailer.mp4',
                'price' => 400,
                'rating' => '4.7',
                'zone' => 'connection',
                'views' => 95,
            ],
            [
                'title' => 'Demo - Flowers in Motion',
                'thumbnail' => 'https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?w=800',
                'video_link' => 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
                'price' => 400,
                'rating' => '4.9',
                'zone' => 'connection',
                'views' => 320,
            ],
        ];

        $demoVideos[] = [
            'title' => 'Demo - Big Buck Bunny (Free Preview)',
            'thumbnail' => 'https://media.w3.org/2010/05/bunny/poster.png',
            'video_link' => 'https://media.w3.org/2010/05/bunny/trailer.mp4',
            'price' => 0,
            'rating' => '4.9',
            'zone' => 'connection',
            'views' => 180,
        ];

        $wakubwaDemos = [
            ['https://media.w3.org/2010/05/bunny/trailer.mp4', 'https://media.w3.org/2010/05/bunny/poster.png', 'Big Buck Bunny', '4.9'],
            ['https://media.w3.org/2010/05/sintel/trailer.mp4', 'https://media.w3.org/2010/05/sintel/poster.png', 'Sintel Trailer', '4.8'],
            ['https://media.w3.org/2010/05/sintel/trailer.mp4', 'https://media.w3.org/2010/05/sintel/poster.png', 'Sintel', '5.0'],
            ['https://vjs.zencdn.net/v/oceans.mp4', 'https://vjs.zencdn.net/v/oceans.png', 'Ocean Life', '4.7'],
            ['https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4', 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?w=800', 'Flowers in Motion', '4.6'],
            ['https://media.w3.org/2010/05/bunny/trailer.mp4', 'https://media.w3.org/2010/05/bunny/poster.png', 'Big Buck Bunny Preview', '4.8'],
        ];

        foreach ($wakubwaDemos as [$videoLink, $thumbnail, $title, $rating]) {
            $demoVideos[] = [
                'title' => 'Demo - '.$title.' (Wakubwa)',
                'thumbnail' => $thumbnail,
                'video_link' => $videoLink,
                'price' => 400,
                'rating' => $rating,
                'zone' => 'wakubwa',
                'views' => 0,
            ];
        }

        foreach ($demoVideos as $data) {
            Video::updateOrCreate(
                ['title' => $data['title'], 'zone' => $data['zone']],
                $data
            );
        }
    }
}
