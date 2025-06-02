<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\FoundItemRequest;
use App\Http\Resources\FoundItemResource;
use App\Models\FoundItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class FoundItemController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = FoundItem::query();

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
        $foundItems = $query->orderBy('found_date', 'desc')
            ->orderBy('found_time', 'desc')
            ->paginate(10);

        return FoundItemResource::collection($foundItems);
    }

    public function store(FoundItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('found_items', 'public');
            $data['image_path'] = $path;
        }

        $foundItem = FoundItem::create($data);

        return response()->json([
            'message' => 'Found item created successfully',
            'data' => new FoundItemResource($foundItem)
        ], 201);
    }

    public function show(FoundItem $foundItem): FoundItemResource
    {
        return new FoundItemResource($foundItem);
    }

    public function update(FoundItemRequest $request, FoundItem $foundItem): JsonResponse
    {
        // Check if the user is the owner of the item
        if ($request->user()->id !== $foundItem->user_id) {
            return response()->json([
                'message' => 'You are not authorized to update this item'
            ], 403);
        }

        $data = $request->validated();

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($foundItem->image_path) {
                Storage::disk('public')->delete($foundItem->image_path);
            }

            $path = $request->file('image')->store('found_items', 'public');
            $data['image_path'] = $path;
        }

        $foundItem->update($data);

        return response()->json([
            'message' => 'Found item updated successfully',
            'data' => new FoundItemResource($foundItem)
        ]);
    }

    public function destroy(Request $request, FoundItem $foundItem): JsonResponse
    {
        // Check if the user is the owner of the item
        if ($request->user()->id !== $foundItem->user_id) {
            return response()->json([
                'message' => 'You are not authorized to delete this item'
            ], 403);
        }

        // Delete image if exists
        if ($foundItem->image_path) {
            Storage::disk('public')->delete($foundItem->image_path);
        }

        $foundItem->delete();

        return response()->json([
            'message' => 'Found item deleted successfully'
        ]);
    }

    public function updateStatus(Request $request, FoundItem $foundItem): JsonResponse
    {
        // Check if the user is the owner of the item
        if ($request->user()->id !== $foundItem->user_id) {
            return response()->json([
                'message' => 'You are not authorized to update this item'
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,matched,claimed',
        ]);

        $foundItem->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Found item status updated successfully',
            'data' => new FoundItemResource($foundItem)
        ]);
    }
}
