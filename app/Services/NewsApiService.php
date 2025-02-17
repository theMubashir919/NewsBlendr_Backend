<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\Article;
use App\Models\Source;
use App\Models\Category;
use App\Models\Author;
use App\Models\ScrapeLog;

class NewsApiService
{
    private const DAILY_LIMIT = 100;
    private const CACHE_KEY = 'newsapi_daily_requests';

    /**
     * Track API request to ensure we don't exceed daily limits
     *
     * @return bool Whether the request can proceed
     */
    private function trackApiRequest(): bool
    {
        $today = Carbon::now()->format('Y-m-d');
        $cacheKey = self::CACHE_KEY . ":{$today}";
        
        $requestCount = Cache::store('api_limits')->get($cacheKey, 0);
        
        if ($requestCount >= self::DAILY_LIMIT) {
            Log::warning('NewsAPI daily limit reached', [
                'date' => $today,
                'limit' => self::DAILY_LIMIT,
                'current_count' => $requestCount
            ]);
            return false;
        }

        Cache::store('api_limits')->put(
            $cacheKey,
            $requestCount + 1,
            Carbon::now()->endOfDay()
        );

        Log::info('NewsAPI request tracked', [
            'date' => $today,
            'current_count' => $requestCount + 1,
            'limit' => self::DAILY_LIMIT
        ]);
        
        return true;
    }

    /**
     * Make an API request with rate limit checking
     *
     * @param string $endpoint
     * @param array $params
     * @return array|null
     */
    private function makeApiRequest(string $endpoint, array $params): ?array
    {
        // Debug logging
        Log::info('NewsAPI Config:', [
            'endpoint' => config('services.newsapi.endpoint'),
            'key' => config('services.newsapi.key') ? 'present' : 'missing'
        ]);

        if (!$this->trackApiRequest()) {
            throw new \RuntimeException('NewsAPI daily request limit exceeded');
        }

        try {
            $response = Http::timeout(30)
                ->get($endpoint, $params);

            if ($response->successful()) {
                return $response->json();
            }

            $errorMessage = $response->json()['message'] ?? 'Unknown error';
            Log::error('NewsAPI request failed', [
                'status' => $response->status(),
                'error' => $errorMessage,
                'params' => $params
            ]);

            if ($response->status() === 429) {
                throw new \RuntimeException('NewsAPI rate limit exceeded: ' . $errorMessage);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('NewsAPI request error', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            throw $e;
        }
    }

    /**
     * Fetch articles using the "everything" endpoint for a specific day.
     *
     * @param array $params Additional query parameters (e.g., q, language)
     * @param string $date A date in 'Y-m-d' format
     * @return array List of articles for that day
     */
    public function fetchEverythingForDay(array $params = [], string $date): array
    {
        $url = config('services.newsapi.endpoint') . 'everything';
        $defaultParams = [
            'apiKey'   => config('services.newsapi.key'),
            'pageSize' => 100, // maximum allowed
            'sortBy'   => 'publishedAt',
            'q'        => 'news OR "breaking news" OR "latest news" OR "top stories" OR +headlines OR current OR today',
        ];

        $queryParams = array_merge(
            $defaultParams,
            $params,
            [
                'from' => $date,
                'to'   => $date,
            ]
        );

        Log::info('Fetching NewsAPI articles', [
            'date' => $date,
            'params' => array_diff_key($queryParams, ['apiKey' => ''])
        ]);

        $response = $this->makeApiRequest($url, $queryParams);
        return $response['articles'] ?? [];
    }

    /**
     * Fetch articles using the "top-headlines" endpoint.
     *
     * @param array $params Additional query parameters (e.g., country, language)
     * @return array List of articles
     */
    public function fetchTopHeadlines(array $params = []): array
    {
        $url = config('services.newsapi.endpoint') . 'top-headlines';
        $defaultParams = [
            'apiKey'   => config('services.newsapi.key'),
            'pageSize' => 100,
            'page'     => 1,
        ];
        
        $queryParams = array_merge($defaultParams, $params);

        Log::info('Fetching NewsAPI headlines', [
            'params' => array_diff_key($queryParams, ['apiKey' => ''])
        ]);

        $response = $this->makeApiRequest($url, $queryParams);
        return $response['articles'] ?? [];
    }

    /**
     * Process a single article item and update or create related records.
     *
     * @param array  $item    Article data from NewsAPI
     * @param string $apiType The API source type (e.g., 'newsapi')
     * @return \App\Models\Article
     */
    public function processArticle(array $item, string $apiType = 'newsapi', ?bool $isHeadline = false)
    {
        $source = Source::firstOrCreate(
            ['api_type' => $apiType, 'name' => 'NewsAPI'],
            ['api_endpoint' => config('services.newsapi.endpoint')]
        );

        $category = Category::firstOrCreate(['name' => 'General']);

        $authorName = $item['author'] ?? 'Unknown Author';
        $author = Author::firstOrCreate(
            ['name' => $authorName, 'source_id' => $source->id],
            ['name' => $authorName, 'source_id' => $source->id]
        );

        $article = Article::updateOrCreate(
            ['url' => $item['url']],
            [
                'title'        => $item['title'] ?? 'No Title',
                'content'      => $item['content'] ?? ($item['description'] ?? ''),
                'image_url'    => $item['urlToImage'] ?? null,
                'published_at' => Carbon::parse($item['publishedAt']),
                'source_id'    => $source->id,
                'category_id'  => $category->id,
                'author_id'    => $author->id,
                'is_headline'  => $isHeadline,
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
