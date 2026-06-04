<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::all(['id', 'name', 'email', 'role', 'status', 'created_at', 'last_login']);

        return response()->json($users);
    }

    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $authUser = $request->user();
        $email = strtolower((string) $request->input('email'));

        if (! $authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($authUser->role !== 'admin' && strtolower((string) $authUser->email) !== $email) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
            'status' => $user->status,
            'unlocked_connection_videos' => $user->unlocked_connection_videos,
            'unlocked_wakubwa_videos' => $user->unlocked_wakubwa_videos,
            'wakubwa_subscribed' => $user->isWakubwaSubscribed(),
            'wakubwa_subscription_expires_at' => $user->wakubwa_subscription_expires_at,
            'last_login' => $user->last_login,
        ]);
    }

    public function storeOrUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'name' => 'nullable|string|max:255',
            'last_login' => 'nullable|date',
            'password' => 'nullable|string',
        ]);

        $authUser = $request->user();
        $email = strtolower((string) $validated['email']);

        if (! $authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($authUser->role !== 'admin' && strtolower((string) $authUser->email) !== $email) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $values = $validated;
        $values['email'] = $email;

        // SECURITY: Never allow role/status escalation via this endpoint.
        // Role and status can only be changed via updateRole/updateStatus.
        unset($values['role'], $values['status']);

        // Generate a random password for Firebase-synced users if none provided
        if (! isset($values['password'])) {
            $values['password'] = bcrypt(uniqid('fb_', true));
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            $values
        );

        return response()->json($user);
    }

    public function updateRole(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|string|in:admin,user',
        ]);

        $user = User::findOrFail($id);
        $user->update(['role' => $validated['role']]);

        return response()->json($user);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:active,banned',
        ]);

        $user = User::findOrFail($id);
        $user->update(['status' => $validated['status']]);

        return response()->json($user);
    }

    public function unlockVideo(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'video_id' => 'required|string',
            'zone' => 'nullable|string|in:connection,wakubwa',
        ]);

        $user = User::findOrFail($id);
        $zone = $validated['zone'] ?? 'connection';
        $field = $zone === 'wakubwa' ? 'unlocked_wakubwa_videos' : 'unlocked_connection_videos';
        $unlocked = $user->{$field} ?? [];

        if (! in_array($validated['video_id'], $unlocked, true)) {
            $unlocked[] = $validated['video_id'];
            $user->update([$field => $unlocked]);
        }

        return response()->json($user);
    }
}
