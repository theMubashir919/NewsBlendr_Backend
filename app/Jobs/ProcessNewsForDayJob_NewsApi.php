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

class ProcessNewsForDayJob_NewsApi implements ShouldQueue
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
        Log::error('NewsAPI Daily Processing Job Failed', [
            'exception' => $exception->getMessage(),
            'attempt' => $this->attempts(),
            'date' => $this->date,
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
    protected $date; // in 'Y-m-d' format

    public function __construct(string $date, array $params = [])
    {
        $this->date = $date;
        $this->params = $params;
    }

    public function displayName()
    {
        return 'Process NewsAPI Articles for ' . $this->date;
    }

    public function tags()
    {
        return [
            'newsapi',
            'date:' . $this->date,
            'type:news-processing',
        ];
    }

    public function handle(NewsApiService $newsApiService)
    {
        $start = Carbon::now();
        
        $source = \App\Models\Source::firstOrCreate(
            ['api_type' => 'newsapi', 'name' => 'NewsAPI'],
            ['api_endpoint' => config('services.newsapi.endpoint')]
        );

        $articles = $newsApiService->fetchEverythingForDay($this->params, $this->date);
        
        Log::info("NewsAPI: Fetched " . count($articles) . " articles for date {$this->date}");
        
        $articlesAdded = 0;
        foreach ($articles as $item) {
            try {
                $result = $newsApiService->processArticle($item, 'newsapi');
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
        $newsApiService->recordScrapeLog($source->id, 'success', $articlesAdded, null, $start, $end);
        
        Log::info("NewsAPI: Job completed", [
            'date' => $this->date,
            'articles_fetched' => count($articles),
            'articles_added' => $articlesAdded
        ]);
    }
}
