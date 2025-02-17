<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\UserPreference;
use App\Models\User;
use App\Models\Source;
use App\Models\Category;
use App\Models\Author;

class UserPreferenceFactory extends Factory
{
    protected $model = UserPreference::class;

    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'source_id'  => $this->faker->boolean(70) ? Source::factory() : null,
            'category_id'=> $this->faker->boolean(70) ? Category::factory() : null,
            'author_id'  => $this->faker->boolean(70) ? Author::factory() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}