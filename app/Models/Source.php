<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'api_type',
        'api_endpoint',
        'api_key',
    ];

    // A Source has many Authors.
    public function authors()
    {
        return $this->hasMany(Author::class);
    }

    // A Source has many Articles.
    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    // A Source has many ScrapeLogs.
    public function scrapeLogs()
    {
        return $this->hasMany(ScrapeLog::class);
    }
}
