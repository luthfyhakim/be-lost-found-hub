<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Notification::where('user_id', $request->user()->id);

        // Filter by read status if provided
        if ($request->has('read')) {
            $query->where('read', $request->boolean('read'));
        }

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        return NotificationResource::collection($notifications);
    }

    public function show(Request $request, Notification $notification)
    {
        // Check if user owns this notification
        if ($request->user()->id !== $notification->user_id) {
            return response()->json([
                'message' => 'You are not authorized to view this notification'
            ], 403);
        }

        return new NotificationResource($notification);
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        // Check if user owns this notification
        if ($request->user()->id !== $notification->user_id) {
            return response()->json([
                'message' => 'You are not authorized to update this notification'
            ], 403);
        }

        $notification->update(['read' => true]);

        return response()->json([
            'message' => 'Notification marked as read',
            'data' => new NotificationResource($notification)
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('read', false)
            ->update(['read' => true]);

        return response()->json([
            'message' => 'All notifications marked as read'
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->where('read', false)
            ->count();

        return response()->json([
            'unread_count' => $count
        ]);
    }

    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        // Check if user owns this notification
        if ($request->user()->id !== $notification->user_id) {
            return response()->json([
                'message' => 'You are not authorized to delete this notification'
            ], 403);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully'
        ]);
    }
}
