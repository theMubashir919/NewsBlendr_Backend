<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScrapeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id',
        'status',
        'articles_added',
        'error_message',
        'started_at',
        'completed_at',
    ];

    // A ScrapeLog belongs to a Source.
    public function source()
    {
        return $this->belongsTo(Source::class);
    }
}
