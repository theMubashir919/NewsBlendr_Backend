<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Article extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'title',
        'content',
        'url',
        'image_url',
        'published_at',
        'source_id',
        'category_id',
        'author_id',
        'views',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_headline' => 'boolean',
    ];

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'source' => $this->source?->name,
            'category' => $this->category?->name,
            'author' => $this->author?->name,
            'published_at' => $this->published_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Configure Meilisearch index settings.
     */
    public function searchableAs()
    {
        return 'articles';
    }

    /**
     * Get the Meilisearch attributes to be filtered.
     */
    public function getSearchableSettings()
    {
        return [
            'filterableAttributes' => [
                'published_at',
                'category',
                'source',
                'author'
            ],
            'sortableAttributes' => [
                'published_at'
            ],
            'searchableAttributes' => [
                'title',
                'content',
                'author',
                'source',
                'category'
            ]
        ];
    }

    // An Article belongs to a Source.
    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    // An Article belongs to a Category.
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // An Article belongs to an Author.
    public function author()
    {
        return $this->belongsTo(Author::class);
    }

    // Add this new relationship
    public function bookmarkedBy()
    {
        return $this->belongsToMany(User::class, 'article_bookmarks')
            ->withTimestamps();
    }
}
