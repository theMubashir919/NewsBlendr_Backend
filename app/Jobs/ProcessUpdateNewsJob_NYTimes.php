<?php

namespace App\Jobs;

use App\Services\NYTimesNewsService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUpdateNewsJob_NYTimes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array
     */
    public function backoff()
    {
        return [60, 180, 300]; // Wait 1min, 3min, 5min between retries
    }

    protected $params;

    public function __construct(array $params = [])
    {
        $this->params = $params;
        $this->onQueue('nytimes-updates');
    }

    public function displayName()
    {
        return 'Process NYTimes Latest News';
    }

    public function tags()
    {
        return [
            'nytimes',
            'type:latest-news-processing',
        ];
    }

    public function handle(NYTimesNewsService $nytService)
    {
        $start = Carbon::now();

        // Debug logging
        Log::info('NYTimes API Config:', [
            'endpoint' => config('services.nytimes.endpoint'),
            'key' => config('services.nytimes.key') ? 'present' : 'missing'
        ]);

        $source = \App\Models\Source::firstOrCreate(
            ['api_type' => 'nytimes'],
            [
                'name' => 'The New York Times',
                'api_endpoint' => config('services.nytimes.endpoint')
            ]
        );

        // Merge default parameters for latest news
        $params = array_merge([
            'sort' => 'newest',
        ], $this->params);

        // Fetch and process latest articles
        [$articlesProcessed, $pagesProcessed] = $nytService->fetchLatestArticles($params);

        $end = Carbon::now();
        $nytService->recordScrapeLog(
            $source->id,
            'success',
            $articlesProcessed,
            null,
            $start,
            $end
        );

        Log::info("NYTimes: Latest news update completed", [
            'articles_processed' => $articlesProcessed,
            'pages_processed' => $pagesProcessed,
            'duration_seconds' => $end->diffInSeconds($start)
        ]);
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error('NYTimes Update News Job Failed', [
            'exception' => $exception->getMessage(),
            'attempt' => $this->attempts(),
            'params' => $this->params
        ]);

        if ($this->attempts() >= $this->tries) {
            $source = \App\Models\Source::where('api_type', 'nytimes')->first();
            if ($source) {
                $nytService = app(NYTimesNewsService::class);
                $nytService->recordScrapeLog(
                    $source->id,
                    'failed',
                    0,
                    $exception->getMessage(),
                    Carbon::now()->subMinutes(5),
                    Carbon::now()
                );
            }
        }
    }
} 