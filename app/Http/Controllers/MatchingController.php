<?php

namespace App\Http\Controllers;

use App\Models\MatchAlert;
use App\Services\AutoMatchingService;
use Illuminate\Http\Request;

class MatchingController extends Controller
{
    protected $autoMatchingService;

    public function __construct(AutoMatchingService $autoMatchingService)
    {
        $this->autoMatchingService = $autoMatchingService;
    }

    public function runMatching()
    {
        $result = $this->autoMatchingService->runAutoMatching();

        return response()->json($result);
    }

    public function getUserMatches(Request $request)
    {
        $user = $request->user();

        $matches = MatchAlert::whereHas('lostItem', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->orWhereHas('foundItem', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with(['lostItem', 'foundItem'])
            ->orderBy('match_score', 'desc')
            ->paginate(10);

        return response()->json($matches);
    }

    public function updateMatchStatus(Request $request, $matchId)
    {
        $request->validate([
            'status' => 'required|in:confirmed,rejected'
        ]);

        $match = MatchAlert::findOrFail($matchId);
        $user = $request->user();

        if (
            $match->lostItem->user_id !== $user->id &&
            $match->foundItem->user_id !== $user->id
        ) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $match->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Status match berhasil diupdate'
        ]);
    }

    public function getStats()
    {
        $stats = $this->autoMatchingService->getMatchingStats();

        return response()->json($stats);
    }
}
