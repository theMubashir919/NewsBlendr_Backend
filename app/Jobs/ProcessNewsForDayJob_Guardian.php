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

class ProcessNewsForDayJob_Guardian implements ShouldQueue
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
    public $timeout = 3600; // 1 hour

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array
     */
    public function backoff()
    {
        return [60, 300, 600]; // Wait 1min, 5min, 10min between retries
    }

    protected $date;
    protected $params;
    protected $maxPages;

    /**
     * Create a new job instance.
     *
     * @param string $date Date in Y-m-d format
     * @param array $params Additional query parameters
     * @param int $maxPages Maximum pages to process (0 for all pages)
     */
    public function __construct(string $date, array $params = [], int $maxPages = 0)
    {
        $this->date = $date;
        $this->params = $params;
        $this->maxPages = $maxPages;
        $this->onQueue('guardian-bulk');
    }

    public function displayName()
    {
        return 'Process Guardian Articles for ' . $this->date;
    }

    public function tags()
    {
        return [
            'guardian',
            'date:' . $this->date,
            'type:bulk-processing',
        ];
    }

    public function handle(GuardianNewsService $guardianService)
    {
        $start = Carbon::now();
        
        $source = \App\Models\Source::firstOrCreate(
            ['api_type' => 'guardian', 'name' => 'The Guardian'],
            ['api_endpoint' => config('services.guardian.endpoint') ?? 'https://content.guardianapis.com']
        );

        $dateParams = array_merge($this->params, [
            'from-date' => $this->date,
            'to-date' => $this->date,
        ]);

        $progressCallback = function($currentPage, $totalPages, $articlesInBatch) {
            Log::info("Guardian: Processing progress", [
                'date' => $this->date,
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'articles_in_batch' => $articlesInBatch,
                'progress_percentage' => round(($currentPage / $totalPages) * 100, 2)
            ]);
        };

        [$totalArticles, $pagesProcessed] = $guardianService->fetchBulkArticles(
            $dateParams,
            $this->maxPages,
            $progressCallback
        );
        
        $end = Carbon::now();
        $guardianService->recordScrapeLog(
            $source->id,
            'success',
            $totalArticles,
            null,
            $start,
            $end
        );
        
        Log::info("Guardian: Bulk processing completed", [
            'date' => $this->date,
            'total_articles' => $totalArticles,
            'pages_processed' => $pagesProcessed,
            'duration_minutes' => $end->diffInMinutes($start)
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
        Log::error('Guardian Daily Processing Job Failed', [
            'exception' => $exception->getMessage(),
            'attempt' => $this->attempts(),
            'date' => $this->date,
            'params' => $this->params,
            'max_pages' => $this->maxPages
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