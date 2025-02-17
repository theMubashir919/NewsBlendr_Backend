<?php

namespace App\Jobs;

use App\Services\NewsApiService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUpdateNewsJob_NewsApi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array
     */
    public function backoff()
    {
        return [1, 5, 10, 30, 60]; // Wait 1s, 5s, 10s, 30s, 60s between retries
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error('NewsAPI Update Job Failed', [
            'exception' => $exception->getMessage(),
            'attempt' => $this->attempts(),
            'params' => $this->params
        ]);

        if ($this->attempts() >= $this->tries) {
            $source = \App\Models\Source::where('api_type', 'newsapi')->first();
            if ($source) {
                $newsApiService = app(NewsApiService::class);
                $newsApiService->recordScrapeLog(
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

    protected $params;

    public function __construct(array $params = [])
    {
        $this->params = $params;
    }

    public function displayName()
    {
        return 'Process NewsAPI Headline Articles';
    }

    public function tags()
    {
        return [
            'newsapi',
            'type:headline-news-processing',
        ];
    }

    public function handle(NewsApiService $newsApiService)
    {
        $start = Carbon::now();

        $defaultParams = [
            'country'  => 'us',
            'language' => 'en',
            'pageSize' => 100,
        ];
        $params = array_merge($defaultParams, $this->params);

        $articles = $newsApiService->fetchTopHeadlines($params);

        Log::info("NewsAPI: Fetched " . count($articles) . " headline articles");

        $articlesAdded = 0;
        foreach ($articles as $item) {
            try {
                $result = $newsApiService->processArticle($item, 'newsapi', true);
                if ($result) {
                    $articlesAdded++;
                }
                Log::info("NewsAPI: Processed article", [
                    'title' => $item['title'] ?? 'No title',
                    'success' => $result ? 'yes' : 'no'
                ]);
            } catch (\Exception $e) {
                Log::error("NewsAPI: Error processing article", [
                    'error' => $e->getMessage(),
                    'title' => $item['title'] ?? 'No title'
                ]);
            }
        }

        $end = Carbon::now();
        $source = \App\Models\Source::where('api_type', 'newsapi')->first();
        $newsApiService->recordScrapeLog($source->id, 'success', $articlesAdded, null, $start, $end);
    }
}
