<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\ScrapeLog;
use App\Models\Source;

class ScrapeLogFactory extends Factory
{
    protected $model = ScrapeLog::class;

    public function definition(): array
    {
        $statuses = ['success', 'failed'];
        return [
            'source_id'     => Source::factory(),
            'status'        => $this->faker->randomElement($statuses),
            'articles_added'=> $this->faker->numberBetween(0, 100),
            'error_message' => $this->faker->optional()->sentence,
            'started_at'    => $this->faker->dateTime,
            'completed_at'  => $this->faker->dateTime,
            'created_at'    => now(),
            'updated_at'    => now(),
        ];
    }
}
