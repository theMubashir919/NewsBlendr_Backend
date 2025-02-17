<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Source;

class SourceFactory extends Factory
{
    protected $model = Source::class;

    public function definition(): array
    {
        $apiTypes = ['newsapi', 'guardian', 'nytimes'];
        return [
            'name'         => $this->faker->company,
            'api_type'     => $this->faker->randomElement($apiTypes),
            'api_endpoint' => $this->faker->url,
            'created_at'   => now(),
            'updated_at'   => now(),
        ];
    }
}
