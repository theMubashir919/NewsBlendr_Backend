<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        User::create([
            'name' => 'Developer',
            'email' => 'dev@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        
        // User::factory(10)->create();

        // $this->call([
        //     SourceSeeder::class,
        //     CategorySeeder::class,
        //     AuthorSeeder::class,
        //     ArticleSeeder::class,
        //     UserPreferenceSeeder::class,
        //     ScrapeLogSeeder::class,
        // ]);
    }
}
