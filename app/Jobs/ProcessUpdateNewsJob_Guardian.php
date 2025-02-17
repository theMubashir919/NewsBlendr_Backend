<?php

namespace App\Jobs;

use App\Services\GuardianNewsService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUpdateNewsJob_Guardian implements ShouldQueue
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
    }

    public function displayName()
    {
        return 'Process Guardian Latest News';
    }

    public function tags()
    {
        return [
            'guardian',
            'type:latest-news-processing',
        ];
    }

    public function handle(GuardianNewsService $guardianService)
    {
        $start = Carbon::now();

        // Debug logging
        Log::info('Guardian API Config:', [
            'endpoint' => config('services.guardian.endpoint'),
            'key' => config('services.guardian.key') ? 'present' : 'missing'
        ]);

        $source = \App\Models\Source::firstOrCreate(
            ['api_type' => 'guardian'],
            [
                'name' => 'The Guardian',
                'api_endpoint' => config('services.guardian.endpoint') ?? 'https://content.guardianapis.com'
            ]
        );

        // Merge default parameters for latest news
        $params = array_merge([
            'order-by' => 'newest',
            'show-fields' => 'byline,thumbnail,bodyText',
        ], $this->params);

        // Fetch and process latest articles
        [$articlesProcessed, $pagesProcessed] = $guardianService->fetchLatestArticles($params);

        $end = Carbon::now();
        $guardianService->recordScrapeLog(
            $source->id,
            'success',
            $articlesProcessed,
            null,
            $start,
            $end
        );

        Log::info("Guardian: Latest news update completed", [
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
        Log::error('Guardian Update News Job Failed', [
            'exception' => $exception->getMessage(),
            'attempt' => $this->attempts(),
            'params' => $this->params
        ]);

        if ($this->attempts() >= $this->tries) {
            $source = \App\Models\Source::where('api_type', 'guardian')->first();
            if ($source) {
                $guardianService = app(GuardianNewsService::class);
                $guardianService->recordScrapeLog(
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