<?php
namespace App\Http\Controllers;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RssController extends Controller
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = env('GUARDIAN_API_KEY');
    }

    public function show($section)
    {
        Log::info("RSS feed requested for section: $section");
    
        if (!preg_match('/^[a-z-]+$/', $section)) {
            return response()->json(['error' => 'Invalid section name format'], 400);
        }
    
        $cacheKey = "guardian_feed_$section";
    
        if (Cache::has($cacheKey)) {
            return response(Cache::get($cacheKey), 200)->header('Content-Type', 'application/rss+xml');
        }
    
        try {
            $client = new Client();
            $url = "https://content.guardianapis.com/$section";
            $response = $client->get($url, [
                'query' => [
                    'api-key' => $this->apiKey,
                    'format' => 'json',
                    'show-fields' => 'headline,trailText,thumbnail,shortUrl',
                ],
            ]);
    
            $data = json_decode($response->getBody(), true);
    
            if ($data['response']['status'] === 'error') {
                return response()->json(['error' => $data['response']['message']], 404);
            }
    
            if (empty($data['response']['results'])) {
                return response()->json(['error' => 'No articles found for this section'], 404);
            }
            // Generate RSS feed
            $rss = $this->generateRssFeed($data['response']['results'], $section);
    
            Cache::put($cacheKey, $rss, now()->addMinutes(10));
    
            return response($rss, 200)->header('Content-Type', 'application/rss+xml');
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return response()->json(['error' => 'The requested section does not exist'], 404);
            }
    
            return response()->json(['error' => 'An error occurred while fetching data from The Guardian API'], 500);
        } catch (\Throwable $t) {
            Log::error("Error fetching RSS feed for section: $section", ['error' => $t->getMessage()]);
            return response()->json(['error' => 'An unexpected error occurred'], 500);
        }
    }
        protected function generateRssFeed($articles, $section)
        {
            $rss = '<?xml version="1.0" encoding="UTF-8"?>';
            $rss .= '<rss version="2.0">';
            $rss .= '<channel>';
            $rss .= '<title>The Guardian - ' . ucfirst($section) . '</title>';
            $rss .= '<description>Latest articles from The Guardian</description>';
            $rss .= '<link>https://www.theguardian.com</link>';
    
            foreach ($articles as $article) {
                $rss .= '<item>';
                $rss .= '<title>' . htmlspecialchars($article['fields']['headline']) . '</title>';
                $rss .= '<description>' . htmlspecialchars($article['fields']['trailText']) . '</description>';
                $rss .= '<link>' . htmlspecialchars($article['fields']['shortUrl']) . '</link>';
                $rss .= '<guid>' . htmlspecialchars($article['fields']['shortUrl']) . '</guid>';
                $rss .= '<pubDate>' . date(DATE_RSS, strtotime($article['webPublicationDate'])) . '</pubDate>';
                $rss .= '</item>';
            }
    
            $rss .= '</channel>';
            $rss .= '</rss>';
    
            return $rss;
        }
    }