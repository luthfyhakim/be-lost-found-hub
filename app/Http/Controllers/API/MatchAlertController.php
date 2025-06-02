<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchAlertResource;
use App\Models\MatchAlert;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MatchAlertController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = MatchAlert::with(['lostItem.user', 'foundItem.user']);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter to show only matches related to user's items
        if ($request->has('my_matches') && $request->my_matches) {
            $userId = $request->user()->id;
            $query->where(function ($q) use ($userId) {
                $q->whereHas('lostItem', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })->orWhereHas('foundItem', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                });
            });
        }

        $matchAlerts = $query->orderBy('created_at', 'desc')
            ->paginate(10);

        return MatchAlertResource::collection($matchAlerts);
    }

    public function show(MatchAlert $matchAlert): MatchAlertResource
    {
        $matchAlert->load(['lostItem.user', 'foundItem.user']);
        return new MatchAlertResource($matchAlert);
    }

    public function updateStatus(Request $request, MatchAlert $matchAlert): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,rejected',
        ]);

        // Check if user is authorized to update this match alert
        $userId = $request->user()->id;
        $isAuthorized = $matchAlert->lostItem->user_id === $userId ||
            $matchAlert->foundItem->user_id === $userId;

        if (!$isAuthorized) {
            return response()->json([
                'message' => 'You are not authorized to update this match alert'
            ], 403);
        }

        $matchAlert->update([
            'status' => $request->status,
        ]);

        // Create notification for the other user
        $this->createNotificationForMatch($matchAlert, $userId);

        return response()->json([
            'message' => 'Match alert status updated successfully',
            'data' => new MatchAlertResource($matchAlert)
        ]);
    }

    public function createMatch(Request $request): JsonResponse
    {
        $request->validate([
            'lost_item_id' => 'required|exists:lost_items,id',
            'found_item_id' => 'required|exists:found_items,id',
            'match_score' => 'required|numeric|between:0,100',
        ]);

        // Check if match already exists
        $existingMatch = MatchAlert::where('lost_item_id', $request->lost_item_id)
            ->where('found_item_id', $request->found_item_id)
            ->first();

        if ($existingMatch) {
            return response()->json([
                'message' => 'Match alert already exists for these items'
            ], 409);
        }

        $matchAlert = MatchAlert::create([
            'lost_item_id' => $request->lost_item_id,
            'found_item_id' => $request->found_item_id,
            'match_score' => $request->match_score,
            'status' => 'pending',
        ]);

        // Create notifications for both users
        $this->createNotificationsForNewMatch($matchAlert);

        return response()->json([
            'message' => 'Match alert created successfully',
            'data' => new MatchAlertResource($matchAlert)
        ], 201);
    }

    private function createNotificationForMatch(MatchAlert $matchAlert, int $currentUserId): void
    {
        $lostItemOwner = $matchAlert->lostItem->user_id;
        $foundItemOwner = $matchAlert->foundItem->user_id;

        $targetUserId = $currentUserId === $lostItemOwner ? $foundItemOwner : $lostItemOwner;

        Notification::create([
            'user_id' => $targetUserId,
            'title' => 'Match Alert Updated',
            'content' => 'A match alert for your item has been ' . $matchAlert->status,
            'type' => 'match',
            'related_type' => MatchAlert::class,
            'related_id' => $matchAlert->id,
        ]);
    }

    private function createNotificationsForNewMatch(MatchAlert $matchAlert): void
    {
        // Notification for lost item owner
        Notification::create([
            'user_id' => $matchAlert->lostItem->user_id,
            'title' => 'Potential Match Found',
            'content' => 'A potential match has been found for your lost item: ' . $matchAlert->lostItem->title,
            'type' => 'match',
            'related_type' => MatchAlert::class,
            'related_id' => $matchAlert->id,
        ]);

        // Notification for found item owner
        Notification::create([
            'user_id' => $matchAlert->foundItem->user_id,
            'title' => 'Potential Match Found',
            'content' => 'Your found item: ' . $matchAlert->foundItem->title . ' might match a lost item',
            'type' => 'match',
            'related_type' => MatchAlert::class,
            'related_id' => $matchAlert->id,
        ]);
    }
}
