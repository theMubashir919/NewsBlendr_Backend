<?php

namespace App\Console\Commands;

use App\Jobs\ProcessUpdateNewsJob_Guardian;
use Illuminate\Console\Command;

class DispatchUpdateNewsJob_Guardian extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:update-guardian {--section=* : Sections to fetch news from}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch a job to fetch and process latest news from The Guardian';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sections = $this->option('section');
        $params = [];

        if (!empty($sections)) {
            $params['section'] = implode('|', $sections);
        }

        ProcessUpdateNewsJob_Guardian::dispatch($params)
            ->onQueue('guardian-updates');

        $this->info('Guardian update news job has been dispatched.');
    }
} 