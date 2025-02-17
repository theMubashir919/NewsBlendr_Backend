<?php

namespace App\Console\Commands;

use App\Jobs\ProcessNewsForDayJob_NYTimes;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DispatchBulkNewsJobs_NYTimes extends Command
{
    protected $signature = 'news:bulk-nytimes 
                          {--from= : Start date (YYYY-MM-DD)}
                          {--to= : End date (YYYY-MM-DD)}
                          {--section=* : Sections to fetch news from}
                          {--max-pages= : Maximum pages to process per day (default: 10 pages)}';

    protected $description = 'Dispatch jobs to fetch and process news from The New York Times for a date range';

    public function handle()
    {
        $fromDate = $this->option('from') ? Carbon::parse($this->option('from')) : Carbon::yesterday();
        $toDate = $this->option('to') ? Carbon::parse($this->option('to')) : Carbon::today();
        $sections = $this->option('section');
        $maxPages = (int) $this->option('max-pages') ?: 10;

        if ($fromDate->isAfter($toDate)) {
            $this->error('From date must be before or equal to to date');
            return 1;
        }

        $params = [];
        if (!empty($sections)) {
            $params['fq'] = 'section_name:(' . implode(' OR ', $sections) . ')';
        }

        $currentDate = clone $fromDate;
        $jobsDispatched = 0;

        while ($currentDate->lte($toDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            ProcessNewsForDayJob_NYTimes::dispatch($dateStr, $params, $maxPages)
                ->onQueue('nytimes-bulk');
            
            $this->info("Dispatched job for date: {$dateStr}");
            $jobsDispatched++;
            
            $currentDate->addDay();
        }

        $this->info("Total jobs dispatched: {$jobsDispatched}");
    }
} 