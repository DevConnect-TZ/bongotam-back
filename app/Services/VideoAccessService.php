<?php

namespace App\Services;

use App\Models\User;
use App\Models\Video;

class VideoAccessService
{
    public function hasAccess(User $user, Video $video): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($video->zone === 'wakubwa') {
            return $user->isWakubwaSubscribed();
        }

        if ($video->price === null || $video->price === 0) {
            return true;
        }

        $unlockedVideoIds = array_map('strval', $user->unlocked_connection_videos ?? []);

        return in_array((string) $video->getKey(), $unlockedVideoIds, true);
    }

    public function denialMessage(Video $video): string
    {
        if ($video->zone === 'wakubwa') {
            return 'An active Wakubwa Zone subscription is required to access this video.';
        }

        return 'Payment is required to access this video.';
    }
}
