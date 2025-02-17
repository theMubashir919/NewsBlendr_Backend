<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class CheckNewsApiLimit extends Command
{
    protected $signature = 'news:check-newsapi-limit';
    protected $description = 'Check remaining NewsAPI requests for today';

    public function handle()
    {
        $today = Carbon::now()->format('Y-m-d');
        $cacheKey = 'newsapi_daily_requests:' . $today;
        
        $requestCount = Cache::store('api_limits')->get($cacheKey, 0);
        $remaining = 100 - $requestCount;
        
        $this->info("NewsAPI Requests Status for {$today}:");
        $this->table(
            ['Used', 'Remaining', 'Limit', 'Reset At'],
            [[
                $requestCount,
                $remaining,
                100,
                Carbon::now()->endOfDay()->format('Y-m-d H:i:s')
            ]]
        );
        
        if ($remaining < 20) {
            $this->warn('Warning: Less than 20 requests remaining for today!');
        }
    }
} 