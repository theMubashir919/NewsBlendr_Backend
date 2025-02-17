<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessUpdateNewsJob_NewsApi;

class DispatchUpdateNewsJob_NewsApi extends Command
{
    protected $signature = 'news:update-newsapi 
                          {--country=us : Country code for headlines}
                          {--language=en : Language of articles}
                          {--max-articles=100 : Maximum articles to fetch (max: 100)}';

    protected $description = 'Dispatch a job to fetch and process latest news from NewsAPI';

    public function handle()
    {
        $params = array_filter([
            'country' => $this->option('country'),
            'language' => $this->option('language'),
            'pageSize' => min((int) $this->option('max-articles'), 100),
        ]);

        ProcessUpdateNewsJob_NewsApi::dispatch($params)
            ->onQueue('newsapi-updates');

        $this->info('NewsAPI update job has been dispatched.');
        $this->info('Note: This will consume one API request out of daily limit of 100.');
    }
} 