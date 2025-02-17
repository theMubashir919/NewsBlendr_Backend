<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Author;
use Illuminate\Support\Facades\Cache;

class AuthorController extends Controller
{
    public function index(Request $request)
    {
        $query = Author::query();
        
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%");
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate(20)
        ]);
    }

    public function show($id)
    {
        $author = Author::findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'author' => $author,
                'articles' => $author->articles()
                    ->latest('published_at')
                    ->paginate(15)
            ]
        ]);
    }

    public function popular()
    {
        return Cache::remember('popular_authors', 3600, function() {
            return response()->json([
                'status' => 'success',
                'data' => Author::withCount('articles')
                    ->orderBy('articles_count', 'desc')
                    ->take(10)
                    ->get()
            ]);
        });
    }
} 