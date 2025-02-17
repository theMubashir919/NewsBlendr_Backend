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

class NYTimesNewsService
{
    /**
     * Fetch articles from the NYTimes API for a given page.
     *
     * @param array $params Additional query parameters.
     * @param int $page Page number.
     * @return array [articles, total_pages, current_page]
     */
    public function fetchArticlesByPage(array $params = [], int $page = 1): array
    {
        $endpoint = config('services.nytimes.endpoint') ?? 'https://api.nytimes.com/svc';
        $endpoint .= '/search/v2/articlesearch.json';
        
        $defaultParams = [
            'api-key' => config('services.nytimes.key'),
            'page' => $page - 1, // NYTimes uses 0-based pagination
            'sort' => 'newest',
            'fl' => 'web_url,headline,pub_date,byline,section_name,abstract,multimedia', // Fields to retrieve
        ];
        $queryParams = array_merge($defaultParams, $params);

        // Add delay between requests to respect rate limits (5 requests per minute)
        sleep(12);

        try {
            $response = Http::timeout(30)
                ->retry(3, 5000, function ($exception) {
                    return $exception instanceof ConnectionException;
                })
                ->get($endpoint, $queryParams);

            if (!$response->successful()) {
                Log::error('NYTimes API request failed for page ' . $page, [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return [[], 0, $page];
            }

            $data = $response->json();
            $response = $data['response'] ?? [];
            $meta = $data['response']['meta'] ?? [];
            
            // Calculate total pages (10 items per page)
            $totalHits = $meta['hits'] ?? 0;
            $totalPages = ceil($totalHits / 10);
            
            return [
                $response['docs'] ?? [],
                $totalPages,
                $page
            ];
        } catch (\Exception $e) {
            Log::error('NYTimes API request error for page ' . $page, [
                'error' => $e->getMessage(),
                'params' => $queryParams
            ]);
            return [[], 0, $page];
        }
    }

    /**
     * Fetch bulk articles by iterating over multiple pages.
     *
     * @param array $params Additional query parameters.
     * @param int $maxPages Maximum number of pages to fetch (0 for all pages).
     * @param callable|null $progressCallback Callback function to report progress.
     * @return array [total_articles, pages_processed]
     */
    public function fetchBulkArticles(array $params = [], int $maxPages = 0, ?callable $progressCallback = null): array
    {
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
                $result = $this->processArticle($item, 'nytimes');
                if ($result) {
                    $processed++;
                }
                Log::info("NYTimes: Processed article", [
                    'title' => $item['headline']['main'] ?? 'No title',
                    'success' => $result ? 'yes' : 'no'
                ]);
            } catch (\Exception $e) {
                Log::error("NYTimes: Error processing article", [
                    'error' => $e->getMessage(),
                    'title' => $item['headline']['main'] ?? 'No title'
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
                  ->where('api_type', 'nytimes')
                  ->first();
        })->latest('published_at')->first();

        if ($latestArticle) {
            $params['begin_date'] = $latestArticle->published_at->format('Ymd');
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
     * @param array  $item    Article data from NYTimes API
     * @param string $apiType The API source type (e.g., 'nytimes')
     * @return \App\Models\Article
     */
    public function processArticle(array $item, string $apiType = 'nytimes')
    {
        // Debug logging
        Log::info('NYTimes API Config in Service:', [
            'endpoint' => config('services.nytimes.endpoint'),
            'key' => config('services.nytimes.key') ? 'present' : 'missing'
        ]);

        $source = Source::firstOrCreate(
            ['api_type' => $apiType],
            [
                'name' => 'The New York Times',
                'api_endpoint' => config('services.nytimes.endpoint')
            ]
        );

        $category = Category::firstOrCreate(['name' => $item['section_name'] ?? 'General']);

        $authorName = $item['byline']['original'] ?? 'Unknown Author';
        $author = Author::firstOrCreate(
            ['name' => $authorName, 'source_id' => $source->id],
            ['name' => $authorName, 'source_id' => $source->id]
        );

        // Get the largest image URL if available
        $imageUrl = null;
        if (!empty($item['multimedia'])) {
            $multimedia = collect($item['multimedia'])
                ->sortByDesc('width')
                ->first();
            if ($multimedia) {
                $imageUrl = 'https://www.nytimes.com/' . $multimedia['url'];
            }
        }

        $article = Article::updateOrCreate(
            ['url' => $item['web_url']],
            [
                'title'        => $item['headline']['main'] ?? 'No Title',
                'content'      => $item['abstract'] ?? '',
                'image_url'    => $imageUrl,
                'published_at' => Carbon::parse($item['pub_date']),
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