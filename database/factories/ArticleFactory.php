<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Article;
use App\Models\Source;
use App\Models\Category;
use App\Models\Author;

class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        return [
            'title'        => $this->faker->sentence,
            'content'      => $this->faker->paragraphs(3, true),
            'url'          => $this->faker->unique()->url,
            'image_url'    => $this->faker->optional()->imageUrl(),
            'published_at' => $this->faker->dateTime,
            'source_id'    => Source::factory(),
            'category_id'  => Category::factory(),
            'author_id'    => Author::factory(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ];
    }
}
