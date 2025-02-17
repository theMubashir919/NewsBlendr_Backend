<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function index()
    {
        return Cache::remember('categories', 3600, function() {
            return response()->json([
                'status' => 'success',
                'data' => Category::all()
            ]);
        });
    }

    public function show($id)
    {
        $category = Category::findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'category' => $category,
                'articles' => $category->articles()
                    ->latest('published_at')
                    ->paginate(15)
            ]
        ]);
    }

    public function trending($id)
    {
        $category = Category::findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'data' => $category->articles()
                ->orderBy('views', 'desc')
                ->take(10)
                ->get()
        ]);
    }
} 