<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'preferred_sources',
        'preferred_categories',
        'preferred_authors',
        'email_notifications',
        'notification_frequency'
    ];

    protected $casts = [
        'preferred_sources' => 'array',
        'preferred_categories' => 'array',
        'preferred_authors' => 'array',
        'email_notifications' => 'boolean'
    ];

    // A UserPreference belongs to a User.
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Optionally, it can belong to a Source.
    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    // Optionally, it can belong to a Category.
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Optionally, it can belong to an Author.
    public function author()
    {
        return $this->belongsTo(Author::class);
    }
}
