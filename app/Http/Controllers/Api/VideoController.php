<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Video;
use App\Support\MediaPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Video::query();

        if ($request->has('zone')) {
            $query->where('zone', $request->input('zone'));
        }

        $videos = $query->orderByDesc('created_at')
            ->get(['id', 'title', 'thumbnail', 'price', 'rating', 'zone', 'views', 'created_at']);

        return response()->json($videos);
    }

    public function show(int $id): JsonResponse
    {
        $video = Video::findOrFail($id);

        // Never expose video_link in the public show response
        return response()->json([
            'id' => $video->id,
            'title' => $video->title,
            'thumbnail' => $video->thumbnail,
            'price' => $video->price,
            'rating' => $video->rating,
            'zone' => $video->zone,
            'views' => $video->views,
            'created_at' => $video->created_at,
        ]);
    }

    public function manage(int $id): JsonResponse
    {
        $video = Video::findOrFail($id);

        return response()->json([
            'id' => $video->id,
            'title' => $video->title,
            'thumbnail' => $video->thumbnail,
            'video_link' => $video->video_link,
            'price' => $video->price,
            'rating' => $video->rating,
            'zone' => $video->zone,
            'views' => $video->views,
            'created_at' => $video->created_at,
            'updated_at' => $video->updated_at,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'thumbnail' => 'nullable|string',
            'video_link' => 'nullable|string',
            'price' => 'nullable|integer|min:0',
            'rating' => 'nullable|string|max:50',
            'zone' => 'nullable|string|max:50',
        ]);

        $validated['views'] = 0;

        $video = Video::create($validated);

        return response()->json($video, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $video = Video::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'thumbnail' => 'nullable|string',
            'video_link' => 'nullable|string',
            'price' => 'nullable|integer|min:0',
            'rating' => 'nullable|string|max:50',
            'zone' => 'nullable|string|max:50',
        ]);

        $originalThumbnail = $video->getRawOriginal('thumbnail');
        $originalVideoLink = $video->getRawOriginal('video_link');

        $video->update($validated);
        $video->refresh();

        if (array_key_exists('thumbnail', $validated) && $originalThumbnail !== $video->getRawOriginal('thumbnail')) {
            MediaPath::deleteIfLocal($originalThumbnail);
        }

        if (array_key_exists('video_link', $validated) && $originalVideoLink !== $video->getRawOriginal('video_link')) {
            MediaPath::deleteIfLocal($originalVideoLink);
        }

        return response()->json($video);
    }

    public function destroy(int $id): JsonResponse
    {
        $video = Video::findOrFail($id);

        MediaPath::deleteIfLocal($video->getRawOriginal('thumbnail'));
        MediaPath::deleteIfLocal($video->getRawOriginal('video_link'));

        $video->delete();

        return response()->json(['message' => 'Video deleted']);
    }

    public function incrementViews(int $id): JsonResponse
    {
        $video = Video::findOrFail($id);
        $video->increment('views');

        return response()->json($video);
    }

    /**
     * Access-controlled endpoint to retrieve the video stream URL.
     * Requires user email and checks if the video is free, unlocked, or user is admin.
     */
    public function stream(Request $request, int $id): JsonResponse
    {
        $video = Video::findOrFail($id);
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'access' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Free videos are always accessible
        if ($video->price === 0 || $video->price === null) {
            $video->increment('views');

            return response()->json([
                'access' => true,
                'url' => $video->video_link,
            ]);
        }

        $isAdmin = $user->role === 'admin';

        // Wakubwa Zone: check subscription instead of per-video unlock
        if ($video->zone === 'wakubwa') {
            $hasAccess = $isAdmin || $user->isWakubwaSubscribed();

            if (! $hasAccess) {
                return response()->json([
                    'access' => false,
                    'message' => 'Wakubwa Zone subscription required. Subscribe for only Tsh 3000/month.',
                ], 403);
            }

            $video->increment('views');

            return response()->json([
                'access' => true,
                'url' => $video->video_link,
            ]);
        }

        // Connection Zone: per-video unlock check
        $unlockedConnection = $user->unlocked_connection_videos ?? [];
        $hasAccess = $isAdmin || in_array((string) $id, $unlockedConnection, true) || in_array($id, $unlockedConnection, true);

        if (! $hasAccess) {
            return response()->json([
                'access' => false,
                'message' => 'Payment required to access this video.',
            ], 403);
        }

        $video->increment('views');

        return response()->json([
            'access' => true,
            'url' => $video->video_link,
        ]);
    }
}
