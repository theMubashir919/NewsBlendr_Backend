<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Author;
use App\Models\Source;

class AuthorFactory extends Factory
{
    protected $model = Author::class;

    public function definition(): array
    {
        return [
            'name'      => $this->faker->name,
            // If a source is not provided when calling the factory, one will be generated.
            'source_id' => Source::factory(),
            'created_at'=> now(),
            'updated_at'=> now(),
        ];
    }
}
