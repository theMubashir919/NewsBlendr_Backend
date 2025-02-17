<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Article;
use App\Models\Source;
use App\Models\Category;
use App\Models\Author;
use App\Models\ScrapeLog;
use Illuminate\Http\Client\ConnectionException;

class GuardianNewsService
{
    /**
     * Fetch articles from The Guardian API for a given page.
     *
     * @param array $params Additional query parameters.
     * @param int $page Page number.
     * @return array [articles, total_pages, current_page]
     */
    public function fetchArticlesByPage(array $params = [], int $page = 1): array
    {
        $endpoint = config('services.guardian.endpoint') ?? 'https://content.guardianapis.com';
        $endpoint .= '/search';
        $defaultParams = [
            'api-key'     => config('services.guardian.key'),
            'page-size'   => 50,
            'order-by'    => 'newest',
            'show-fields' => 'byline,thumbnail,bodyText',
            'page'        => $page,
        ];
        $queryParams = array_merge($defaultParams, $params);

        // Add delay between requests to respect rate limits
        sleep(1);

        try {
            $response = Http::timeout(30)
                ->retry(3, 5000, function ($exception) {
                    return $exception instanceof ConnectionException;
                })
                ->get($endpoint, $queryParams);

            if (!$response->successful()) {
                Log::error('Guardian API request failed for page ' . $page, [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return [[], 0, $page];
            }

            $data = $response->json();
            $results = $data['response'] ?? [];
            
            return [
                $results['results'] ?? [],
                $results['pages'] ?? 0,
                $results['currentPage'] ?? $page
            ];
        } catch (\Exception $e) {
            Log::error('Guardian API request error for page ' . $page, [
                'error' => $e->getMessage(),
                'params' => $queryParams
            ]);
            return [[], 0, $page];
        }
    }

    /**
     * Fetch bulk articles by iterating over multiple pages.
     * This method is used for initial data loading and respects rate limits.
     *
     * @param array $params Additional query parameters.
     * @param int $maxPages Maximum number of pages to fetch (0 for all pages).
     * @param callable|null $progressCallback Callback function to report progress.
     * @return array [total_articles, pages_processed]
     */
    public function fetchBulkArticles(array $params = [], int $maxPages = 0, ?callable $progressCallback = null): array
    {
        $allArticles = [];
        $pagesProcessed = 0;
        $totalArticlesProcessed = 0;

        // Get first page to determine total pages
        [$articles, $totalPages, $currentPage] = $this->fetchArticlesByPage($params);
        
        if (empty($articles)) {
            return [0, 0];
        }

        // Process first page results
        $totalArticlesProcessed += $this->processArticleBatch($articles);
        $pagesProcessed++;

        if ($progressCallback) {
            $progressCallback($pagesProcessed, $totalPages, count($articles));
        }

        // Determine how many pages to process
        $pagesToProcess = ($maxPages > 0) ? min($maxPages, $totalPages) : $totalPages;

        // Process remaining pages
        for ($page = 2; $page <= $pagesToProcess; $page++) {
            [$articles, , $currentPage] = $this->fetchArticlesByPage($params, $page);
            
            if (empty($articles)) {
                break;
            }

            $totalArticlesProcessed += $this->processArticleBatch($articles);
            $pagesProcessed++;

            if ($progressCallback) {
                $progressCallback($pagesProcessed, $totalPages, count($articles));
            }
        }

        return [$totalArticlesProcessed, $pagesProcessed];
    }

    /**
     * Process a batch of articles.
     *
     * @param array $articles
     * @return int Number of articles successfully processed
     */
    private function processArticleBatch(array $articles): int
    {
        $processed = 0;
        foreach ($articles as $item) {
            try {
                $result = $this->processArticle($item, 'guardian');
                if ($result) {
                    $processed++;
                }
                Log::info("Guardian: Processed article", [
                    'title' => $item['webTitle'] ?? 'No title',
                    'success' => $result ? 'yes' : 'no'
                ]);
            } catch (\Exception $e) {
                Log::error("Guardian: Error processing article", [
                    'error' => $e->getMessage(),
                    'title' => $item['webTitle'] ?? 'No title'
                ]);
            }
        }
        return $processed;
    }

    /**
     * Fetch latest articles since the most recent article in our database.
     *
     * @param array $params Additional query parameters.
     * @return array [articles_processed, pages_processed]
     */
    public function fetchLatestArticles(array $params = []): array
    {
        $latestArticle = Article::where('source_id', function($query) {
            $query->select('id')
                  ->from('sources')
                  ->where('api_type', 'guardian')
                  ->first();
        })->latest('published_at')->first();

        if ($latestArticle) {
            $params['from-date'] = $latestArticle->published_at->format('Y-m-d');
        }

        [$articles, $totalPages, $currentPage] = $this->fetchArticlesByPage($params);
        
        if (empty($articles)) {
            return [0, 0];
        }

        $processed = $this->processArticleBatch($articles);
        return [$processed, 1];
    }

    /**
     * Process a single article item and update or create related records.
     *
     * @param array  $item    Article data from Guardian API
     * @param string $apiType The API source type (e.g., 'guardian')
     * @return \App\Models\Article
     */
    public function processArticle(array $item, string $apiType = 'guardian')
    {
        // Debug logging
        Log::info('Guardian API Config in Service:', [
            'endpoint' => config('services.guardian.endpoint'),
            'key' => config('services.guardian.key') ? 'present' : 'missing'
        ]);

        $source = Source::firstOrCreate(
            ['api_type' => $apiType],
            [
                'name' => 'The Guardian',
                'api_endpoint' => config('services.guardian.endpoint') ?? 'https://content.guardianapis.com'
            ]
        );

        $category = Category::firstOrCreate(['name' => $item['sectionName'] ?? 'General']);

        $authorName = $item['fields']['byline'] ?? 'Unknown Author';
        $author = Author::firstOrCreate(
            ['name' => $authorName, 'source_id' => $source->id],
            ['name' => $authorName, 'source_id' => $source->id]
        );

        $article = Article::updateOrCreate(
            ['url' => $item['webUrl']],
            [
                'title'        => $item['webTitle'] ?? 'No Title',
                'content'      => $item['fields']['bodyText'] ?? '',
                'image_url'    => $item['fields']['thumbnail'] ?? null,
                'published_at' => Carbon::parse($item['webPublicationDate']),
                'source_id'    => $source->id,
                'category_id'  => $category->id,
                'author_id'    => $author->id,
            ]
        );

        return $article;
    }

    /**
     * Record a scrape log entry.
     *
     * @param int         $sourceId       Source record ID.
     * @param string      $status         'success' or 'failed'
     * @param int         $articlesAdded  Number of articles processed
     * @param string|null $errorMessage   Any error message encountered
     * @param Carbon      $startedAt      Start time
     * @param Carbon      $completedAt    End time
     * @return \App\Models\ScrapeLog
     */
    public function recordScrapeLog(int $sourceId, string $status, int $articlesAdded, ?string $errorMessage, Carbon $startedAt, Carbon $completedAt)
    {
        return ScrapeLog::create([
            'source_id'      => $sourceId,
            'status'         => $status,
            'articles_added' => $articlesAdded,
            'error_message'  => $errorMessage,
            'started_at'     => $startedAt,
            'completed_at'   => $completedAt,
        ]);
    }
}
