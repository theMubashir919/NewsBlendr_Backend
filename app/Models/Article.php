<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'url',
        'image_url',
        'published_at',
        'source_id',
        'category_id',
        'author_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_headline' => 'boolean',
    ];

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
