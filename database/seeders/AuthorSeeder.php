<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Author;
use App\Models\Source;

class AuthorSeeder extends Seeder
{
    public function run(): void
    {
        $sources = Source::all();

        Author::factory()->count(10)->make()->each(function($author) use ($sources) {
            $author->source_id = $sources->random()->id;
            $author->save();
        });
    }
}