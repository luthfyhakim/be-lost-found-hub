<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\LocationController;
use App\Http\Controllers\API\LostItemController;
use App\Http\Controllers\API\FoundItemController;
use App\Http\Controllers\API\MatchAlertController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\MatchingController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Categories routes
    Route::apiResource('categories', CategoryController::class);

    // Locations routes
    Route::apiResource('locations', LocationController::class);

    // Lost Items routes
    Route::apiResource('lost-items', LostItemController::class);
    Route::patch('/lost-items/{lostItem}/status', [LostItemController::class, 'updateStatus']);

    // Found Items routes
    Route::apiResource('found-items', FoundItemController::class);
    Route::patch('/found-items/{foundItem}/status', [FoundItemController::class, 'updateStatus']);

    // Auto Matching routes
    Route::post('/matching/run', [MatchingController::class, 'runMatching']);
    Route::get('/matching/my-matches', [MatchingController::class, 'getUserMatches']);
    Route::put('/matching/{match}/status', [MatchingController::class, 'updateMatchStatus']);
    Route::get('/matching/stats', [MatchingController::class, 'getStats']);

    // Match Alerts routes
    Route::get('/match-alerts', [MatchAlertController::class, 'index']);
    Route::get('/match-alerts/{matchAlert}', [MatchAlertController::class, 'show']);
    Route::post('/match-alerts', [MatchAlertController::class, 'createMatch']);
    Route::patch('/match-alerts/{matchAlert}/status', [MatchAlertController::class, 'updateStatus']);

    // Notifications routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/{notification}', [NotificationController::class, 'show']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
});
