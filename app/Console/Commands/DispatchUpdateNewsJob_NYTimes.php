<?php

namespace App\Console\Commands;

use App\Jobs\ProcessUpdateNewsJob_NYTimes;
use Illuminate\Console\Command;

class DispatchUpdateNewsJob_NYTimes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:update-nytimes {--section=* : Sections to fetch news from}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch a job to fetch and process latest news from The New York Times';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sections = $this->option('section');
        $params = [];

        if (!empty($sections)) {
            $params['fq'] = 'section_name:(' . implode(' OR ', $sections) . ')';
        }

        ProcessUpdateNewsJob_NYTimes::dispatch($params)
            ->onQueue('nytimes-updates');

        $this->info('NYTimes update news job has been dispatched.');
    }
} 