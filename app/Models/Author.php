<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Author extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'source_id',
    ];

    // An Author belongs to a Source.
    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    // An Author can have many Articles.
    public function articles()
    {
        return $this->hasMany(Article::class);
    }
}
