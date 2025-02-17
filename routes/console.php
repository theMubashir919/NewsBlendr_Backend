<?php

use Illuminate\Support\Facades\Schedule;

// NewsApi Bulk Processing
Schedule::command('news:bulk-newsapi --from="2 weeks ago" --to="today" --max-articles=100')
    ->weekly()
    ->sundays()
    ->at('01:00')
    ->name('Weekly NewsAPI Bulk Processing')
    ->withoutOverlapping()
    ->onOneServer();

// NewsApi Regular Updates
Schedule::command('news:update-newsapi --max-articles=100')
    ->hourly()
    ->name('NewsAPI Headlines Update')
    ->withoutOverlapping()
    ->onOneServer();

// Guardian Bulk Processing
Schedule::command('news:bulk-guardian --from="1 week ago" --to="today" --max-pages=10')
    ->weekly()
    ->name('Weekly Guardian Bulk Processing')
    ->withoutOverlapping()
    ->onOneServer();
// Guardian Regular Updates
Schedule::command('news:update-guardian')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// NYTimes Bulk Processing
Schedule::command('news:bulk-nytimes --from="1 week ago" --to="today" --max-pages=10')
    ->weekly()
    ->name('Weekly NYTimes Bulk Processing')
    ->withoutOverlapping()
    ->onOneServer();
// NYTimes Regular Updates
Schedule::command('news:update-nytimes')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->onOneServer();
