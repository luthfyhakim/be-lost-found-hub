<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LostItemRequest;
use App\Http\Resources\LostItemResource;
use App\Models\LostItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class LostItemController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = LostItem::query();

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by category if provided
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by location if provided
        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        // Filter by user's own items
        if ($request->has('my_items') && $request->my_items) {
            $query->where('user_id', $request->user()->id);
        }

        // Order by date/time desc
        $lostItems = $query->orderBy('lost_date', 'desc')
            ->orderBy('lost_time', 'desc')
            ->paginate(10);

        return LostItemResource::collection($lostItems);
    }

    public function store(LostItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('lost_items', 'public');
            $data['image_path'] = $path;
        }

        $lostItem = LostItem::create($data);

        return response()->json([
            'message' => 'Lost item created successfully',
            'data' => new LostItemResource($lostItem)
        ], 201);
    }

    public function show(LostItem $lostItem): LostItemResource
    {
        return new LostItemResource($lostItem);
    }

    public function update(LostItemRequest $request, LostItem $lostItem): JsonResponse
    {
        // Check if the user is the owner of the item
        if ($request->user()->id !== $lostItem->user_id) {
            return response()->json([
                'message' => 'You are not authorized to update this item'
            ], 403);
        }

        $data = $request->validated();

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($lostItem->image_path) {
                Storage::disk('public')->delete($lostItem->image_path);
            }

            $path = $request->file('image')->store('lost_items', 'public');
            $data['image_path'] = $path;
        }

        $lostItem->update($data);

        return response()->json([
            'message' => 'Lost item updated successfully',
            'data' => new LostItemResource($lostItem)
        ]);
    }

    public function destroy(Request $request, LostItem $lostItem): JsonResponse
    {
        // Check if the user is the owner of the item
        if ($request->user()->id !== $lostItem->user_id) {
            return response()->json([
                'message' => 'You are not authorized to delete this item'
            ], 403);
        }

        // Delete image if exists
        if ($lostItem->image_path) {
            Storage::disk('public')->delete($lostItem->image_path);
        }

        $lostItem->delete();

        return response()->json([
            'message' => 'Lost item deleted successfully'
        ]);
    }

    public function updateStatus(Request $request, LostItem $lostItem): JsonResponse
    {
        // Check if the user is the owner of the item
        if ($request->user()->id !== $lostItem->user_id) {
            return response()->json([
                'message' => 'You are not authorized to update this item'
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:lost,found,claimed',
        ]);

        $lostItem->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Lost item status updated successfully',
            'data' => new LostItemResource($lostItem)
        ]);
    }
}
