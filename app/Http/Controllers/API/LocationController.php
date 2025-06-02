<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LocationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $locations = Location::all();
        return LocationResource::collection($locations);
    }

    public function store(LocationRequest $request): JsonResponse
    {
        $location = Location::create($request->validated());

        return response()->json([
            'message' => 'Location created successfully',
            'data' => new LocationResource($location)
        ], 201);
    }

    public function show(Location $location): LocationResource
    {
        return new LocationResource($location);
    }

    public function update(LocationRequest $request, Location $location): JsonResponse
    {
        $location->update($request->validated());

        return response()->json([
            'message' => 'Location updated successfully',
            'data' => new LocationResource($location)
        ]);
    }

    public function destroy(Location $location): JsonResponse
    {
        $location->delete();

        return response()->json([
            'message' => 'Location deleted successfully'
        ]);
    }
}
