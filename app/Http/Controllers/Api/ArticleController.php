<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $query = Article::query();

        // Apply search filters
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('published_at', '>=', $request->get('from_date'));
        }
        if ($request->has('to_date')) {
            $query->where('published_at', '<=', $request->get('to_date'));
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->get('category'));
        }

        // Filter by source
        if ($request->has('source')) {
            $query->where('source', $request->get('source'));
        }

        // Filter by author
        if ($request->has('author')) {
            $query->where('author', $request->get('author'));
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
        $article = Article::findOrFail($id);
        
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
                'data' => Article::latest('published_at')
                    ->take(6)
                    ->select(['id', 'title', 'image_url', 'published_at'])
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
} 