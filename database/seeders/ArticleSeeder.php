<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Article;
use App\Models\Source;
use App\Models\Category;
use App\Models\Author;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $sources = Source::all();
        $categories = Category::all();
        $authors = Author::all();

        Article::factory()->count(50)->make()->each(function ($article) use ($sources, $categories, $authors) {
            $article->source_id   = $sources->random()->id;
            $article->category_id = $categories->random()->id;
            $article->author_id   = $authors->random()->id;
            $article->save();
        });
    }
}
