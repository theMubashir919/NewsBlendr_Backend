<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Jobs\ProcessNewsForDayJob_NewsApi;

class DispatchBulkNewsJobs_NewsApi extends Command
{
    protected $signature = 'news:bulk-newsapi 
                          {--from= : Start date (YYYY-MM-DD)}
                          {--to= : End date (YYYY-MM-DD)}
                          {--max-articles=100 : Maximum articles to fetch per day (max: 100)}
                          {--language=en : Language of articles}
                          {--query= : Search query to filter articles}';

    protected $description = 'Dispatch jobs to fetch and process news from NewsAPI for a date range (limited to 100 articles per request)';

    public function handle()
    {
        $fromDate = $this->option('from') ? Carbon::parse($this->option('from')) : Carbon::yesterday();
        $toDate = $this->option('to') ? Carbon::parse($this->option('to')) : Carbon::today();
        $maxArticles = min((int) $this->option('max-articles'), 100); // Ensure we don't exceed API limits
        $language = $this->option('language');
        $query = $this->option('query');

        if ($fromDate->isAfter($toDate)) {
            $this->error('From date must be before or equal to to date');
            return 1;
        }

        $dayCount = $fromDate->diffInDays($toDate) + 1;
        if ($dayCount > 50) {
            $this->warn("Warning: Processing {$dayCount} days will use {$dayCount} API requests out of your daily limit of 100.");
            $this->warn("Note: This leaves " . (100 - $dayCount) . " requests for other operations today.");
            if (!$this->confirm('Do you want to continue?')) {
                return 1;
            }
        }

        $params = array_filter([
            'language' => $language,
            'q' => $query,
            'pageSize' => $maxArticles,
        ]);

        $currentDate = clone $fromDate;
        $jobsDispatched = 0;

        while ($currentDate->lte($toDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            ProcessNewsForDayJob_NewsApi::dispatch($dateStr, $params)
                ->onQueue('newsapi-bulk');
            
            $this->info("Dispatched job for date: {$dateStr}");
            $jobsDispatched++;
            
            $currentDate->addDay();
        }

        $this->info("Total jobs dispatched: {$jobsDispatched}");
        $this->info("Note: Each job will consume one API request. We have a limit of 100 requests per day.");
    }
}
