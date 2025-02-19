<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SourceController;
use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\UserPreferencesController;
use App\Http\Controllers\Api\AuthController;

// Public routes - Only preview/sample content
Route::get('/articles/preview', [ArticleController::class, 'preview']); // Returns random selection of recent articles
Route::get('/articles/{id}/preview', [ArticleController::class, 'previewArticle']); // Returns limited article data

// Auth routes only for jwt token
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Protected routes (require authentication)
Route::middleware(['auth:sanctum'])->group(function () {
    // User profile
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Articles
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/trending', [ArticleController::class, 'trending']);
    Route::get('/articles/latest', [ArticleController::class, 'latest']);
    Route::get('/articles/{id}', [ArticleController::class, 'show']);
    Route::post('/articles/{id}/bookmark', [ArticleController::class, 'bookmark']);
    Route::delete('/articles/{id}/bookmark', [ArticleController::class, 'removeBookmark']);
    Route::get('/bookmarks', [ArticleController::class, 'getBookmarkedArticles']);

    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
    Route::get('/categories/{id}/trending', [CategoryController::class, 'trending']);

    // Sources
    Route::get('/sources', [SourceController::class, 'index']);
    Route::get('/sources/{id}', [SourceController::class, 'show']);
    Route::get('/sources/{id}/stats', [SourceController::class, 'stats']);

    // Authors
    Route::get('/authors', [AuthorController::class, 'index']);
    Route::get('/authors/popular', [AuthorController::class, 'popular']);
    Route::get('/authors/{id}', [AuthorController::class, 'show']);
    
    // User preferences
    Route::get('/preferences', [UserPreferencesController::class, 'getPreferences']);
    Route::post('/preferences', [UserPreferencesController::class, 'updatePreferences']);
    Route::get('/feed', [UserPreferencesController::class, 'getPersonalizedFeed']);
});
