<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UserPreference;
use App\Models\User;
use App\Models\Source;
use App\Models\Category;
use App\Models\Author;

class UserPreferenceSeeder extends Seeder
{
    public function run(): void
    {

        $users = User::all();

        foreach ($users as $user) {
            UserPreference::factory()->create([
                'user_id' => $user->id,
                'source_id'   => Source::inRandomOrder()->first()->id ?? null,
                'category_id' => Category::inRandomOrder()->first()->id ?? null,
                'author_id'   => Author::inRandomOrder()->first()->id ?? null,
            ]);
        }
    }
}
