<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Category;
use App\Models\Source;
use App\Models\Author;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        // If search query is provided, use Meilisearch
        if ($request->has('search')) {
            $query = Article::search($request->get('search'));
            
            // Date filters - support both fromDate and from_date formats
            $fromDate = $request->get('fromDate') ?? $request->get('from_date');
            $toDate = $request->get('toDate') ?? $request->get('to_date');
            
            if ($fromDate) {
                $fromDate = date('Y-m-d H:i:s', strtotime($fromDate));
                $query = $query->where('published_at', '>=', $fromDate);
            }
            if ($toDate) {
                $toDate = date('Y-m-d H:i:s', strtotime($toDate));
                $query = $query->where('published_at', '<=', $toDate);
            }
            
            // Other filters
            if ($request->has('category')) {
                $category = Category::find($request->get('category'));
                if ($category) {
                    $query = $query->where('category', $category->name);
                }
            }
            if ($request->has('source')) {
                $source = Source::find($request->get('source'));
                if ($source) {
                    $query = $query->where('source', $source->name);
                }
            }
            if ($request->has('author')) {
                $author = Author::find($request->get('author'));
                if ($author) {
                    $query = $query->where('author', $author->name);
                }
            }

            // Sort options
            if ($request->has('sort_by') && $request->get('sort_by') === 'published_at') {
                $query = $query->orderBy('published_at', $request->get('sort_order', 'desc'));
            }

            // Get paginated results
            $perPage = $request->get('per_page', 15);
            $results = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $results
            ]);
        }

        // If no search query, use regular Eloquent
        $query = Article::query();

        // Apply filters
        if ($request->has('fromDate')) {
            $fromDate = date('Y-m-d H:i:s', strtotime($request->get('fromDate')));
            $query->where('published_at', '>=', $fromDate);
        }
        if ($request->has('toDate')) {
            $toDate = date('Y-m-d H:i:s', strtotime($request->get('toDate')));
            $query->where('published_at', '<=', $toDate);
        }
        if ($request->has('category')) {
            $query->where('category_id', $request->get('category'));
        }
        if ($request->has('source')) {
            $query->where('source_id', $request->get('source'));
        }
        if ($request->has('author')) {
            $query->where('author_id', $request->get('author'));
        }

        // Sort options
        $sortField = $request->get('sort_by', 'published_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        // Paginate results
        $perPage = $request->get('per_page', 15);
        
        return response()->json([
            'status' => 'success',
            'data' => $query->paginate($perPage)
        ]);
    }

    public function show($id)
    {
        $article = Article::with(['source:id,name', 'category:id,name', 'author:id,name'])
            ->findOrFail($id);
        
        // Increment views
        $article->increment('views');
        
        return response()->json([
            'status' => 'success',
            'data' => $article
        ]);
    }

    public function trending()
    {
        // Cache trending articles for 1 hour
        return Cache::remember('trending_articles', 3600, function() {
            return Article::orderBy('views', 'desc')
                         ->take(10)
                         ->get();
        });
    }

    public function latest()
    {
        return response()->json([
            'status' => 'success',
            'data' => Article::latest('published_at')
                            ->take(20)
                            ->get()
        ]);
    }

    public function preview()
    {
        return Cache::remember('articles_preview', 3600, function() {
            return response()->json([
                'status' => 'success',
                'data' => Article::with(['category:id,name', 'author:id,name', 'source:id,name'])
                    ->inRandomOrder()
                    ->take(10)
                    ->select(['id', 'title', 'image_url', 'published_at', 'category_id', 'author_id', 'source_id'])
                    ->get()
            ]);
        });
    }

    public function previewArticle($id)
    {
        $article = Article::select([
            'id', 'title', 'image_url', 'published_at', 
            'source_id', 'category_id', 'author_id'
        ])->findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'data' => $article
        ]);
    }

    public function bookmark($id)
    {
        $article = Article::findOrFail($id);
        $user = request()->user();
        
        // Check if already bookmarked
        if ($user->bookmarkedArticles()->where('article_id', $id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Article is already bookmarked'
            ], 400);
        }
        
        // Add bookmark
        $user->bookmarkedArticles()->attach($id);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Article bookmarked successfully'
        ]);
    }

    public function removeBookmark($id)
    {
        $article = Article::findOrFail($id);
        $user = request()->user();
        
        // Check if bookmark exists
        if (!$user->bookmarkedArticles()->where('article_id', $id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Article is not bookmarked'
            ], 400);
        }
        
        // Remove bookmark
        $user->bookmarkedArticles()->detach($id);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Bookmark removed successfully'
        ]);
    }

    public function getBookmarkedArticles()
    {
        $user = request()->user();
        
        return response()->json([
            'status' => 'success',
            'data' => $user->bookmarkedArticles()
                          ->latest('article_bookmarks.created_at')
                          ->paginate(15)
        ]);
    }
} 