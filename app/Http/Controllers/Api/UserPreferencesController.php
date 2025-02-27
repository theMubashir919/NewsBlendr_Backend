<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserPreference;
use App\Models\Article;
use Illuminate\Support\Facades\Auth;

class UserPreferencesController extends Controller
{
    public function getPreferences()
    {
        $user = Auth::user();
        $preferences = $user->preferences ?? new UserPreference();
        
        return response()->json([
            'status' => 'success',
            'data' => $preferences
        ]);
    }

    public function updatePreferences(Request $request)
    {
        $user = request()->user();
        
        $validated = $request->validate([
            'preferred_sources' => 'array',
            'preferred_sources.*' => 'exists:sources,id',
            'preferred_categories' => 'array',
            'preferred_categories.*' => 'exists:categories,id',
            'preferred_authors' => 'array',
            'preferred_authors.*' => 'exists:authors,id',
            'email_notifications' => 'boolean',
            'notification_frequency' => 'string|in:daily,weekly,never'
        ]);

        $preferences = UserPreference::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return response()->json([
            'status' => 'success',
            'data' => $preferences,
            'message' => 'Preferences updated successfully'
        ]);
    }

    public function getPersonalizedFeed(Request $request)
    {
        $user = request()->user();
        $preferences = $user->preferences;
        
        if (!$preferences) {
            return $this->getDefaultFeed($request);
        }

        $query = Article::with(['source:id,name', 'category:id,name', 'author:id,name']);

        if (!empty($preferences->preferred_sources)) {
            $query->whereIn('source_id', $preferences->preferred_sources);
        }
        
        if (!empty($preferences->preferred_categories)) {
            $query->whereIn('category_id', $preferences->preferred_categories);
        }
        
        if (!empty($preferences->preferred_authors)) {
            $query->whereIn('author_id', $preferences->preferred_authors);
        }

        $query->latest('published_at');

        $perPage = $request->get('per_page', 15);
        
        return response()->json([
            'status' => 'success',
            'data' => $query->paginate($perPage)
        ]);
    }

    private function getDefaultFeed(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => Article::latest('published_at')
                            ->take(20)
                            ->get()
        ]);
    }
} 