<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Source;
use Illuminate\Support\Facades\Cache;

class SourceController extends Controller
{
    public function index()
    {
        return Cache::remember('sources', 3600, function() {
            return response()->json([
                'status' => 'success',
                'data' => Source::all()
            ]);
        });
    }

    public function show($id)
    {
        $source = Source::findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'source' => $source,
                'articles' => $source->articles()
                    ->latest('published_at')
                    ->paginate(15)
            ]
        ]);
    }

    public function stats($id)
    {
        $source = Source::findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'total_articles' => $source->articles()->count(),
                'articles_today' => $source->articles()
                    ->whereDate('published_at', today())
                    ->count(),
                'most_covered_categories' => $source->articles()
                    ->select('category')
                    ->groupBy('category')
                    ->orderByRaw('COUNT(*) DESC')
                    ->take(5)
                    ->get()
            ]
        ]);
    }
} 