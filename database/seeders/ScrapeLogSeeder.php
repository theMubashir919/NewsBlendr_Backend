<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ScrapeLog;
use App\Models\Source;

class ScrapeLogSeeder extends Seeder
{
    public function run(): void
    {
        // Create 10 scrape log entries
        $sources = Source::all();

        ScrapeLog::factory()->count(10)->make()->each(function ($log) use ($sources) {
            $log->source_id = $sources->random()->id;
            $log->save();
        });
    }
}