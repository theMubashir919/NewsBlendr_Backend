<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InitializeMeilisearch extends Command
{
    protected $signature = 'meilisearch:initialize';
    protected $description = 'Initialize Meilisearch with proper settings and import data';

    public function handle()
    {
        $this->info('Initializing Meilisearch...');

        // Delete existing index
        $this->info('Deleting existing index...');
        $this->call('scout:delete-index', ['name' => 'articles']);

        // Sync settings
        $this->info('Syncing index settings...');
        $this->call('scout:sync-index-settings');

        // Import data
        $this->info('Importing articles...');
        $this->call('scout:import', ['model' => "App\\Models\\Article"]);

        $this->info('Meilisearch initialization completed successfully!');
    }
} 