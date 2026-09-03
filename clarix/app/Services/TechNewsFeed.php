<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The technology headlines behind the public /blog page.
 *
 * These are other publications' articles reached through NewsData.io, not
 * Clarix's own writing, so this class deliberately carries only the parts a
 * link-out needs: headline, the API's own short description, the source's
 * name, the publication date and the original url. It never asks for
 * full_content and never stores article bodies — on the free tier the field
 * comes back as the string "ONLY AVAILABLE IN PAID PLANS" in any case, so the
 * question of reproducing an article does not arise.
 *
 * Nothing here throws. A marketing page must render whether or not a third
 * party is up, so every failure path returns an empty list and the view shows
 * its fallback.
 */
class TechNewsFeed
{
    public const CACHE_KEY = 'marketing.technews.latest';

    /**
     * How long an empty result is held before the API is tried again.
     *
     * Negative caching matters more than the happy path here: without it, an
     * outage turns every page view into an outbound request, and the daily
     * credit allowance is gone long before the API comes back. Short enough
     * that a blip clears quickly, long enough that a crawl does not bill us
     * for it.
     */
    public const FAILURE_TTL_MINUTES = 5;

    /**
     * The most articles the page will ever render, whatever the API returns.
     */
    public const LIMIT = 9;

    /**
     * Headlines for the page, from cache when possible.
     *
     * @return array<int, array{title: string, description: ?string, url: string, source: string, initials: string, domain: string, image: ?string, published_at: ?Carbon}>
     */
    public function articles(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $articles = $this->fetch();

        Cache::put(
            self::CACHE_KEY,
            $articles,
            now()->addMinutes($articles === []
                ? self::FAILURE_TTL_MINUTES
                : max(1, (int) config('services.newsdata.cache_minutes', 30))),
        );

        return $articles;
    }

    /**
     * Whether a key is configured at all. An unconfigured environment is a
     * supported state — local checkouts and CI have no key — so it is worth
     * being able to tell "not set up" from "tried and failed".
     */
    public function isConfigured(): bool
    {
        return filled(config('services.newsdata.key'));
    }

    /**
     * One call to the API, normalised. Returns [] rather than throwing.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetch(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = Http::timeout(8)
                ->connectTimeout(4)
                ->get(config('services.newsdata.endpoint'), [
                    'apikey'   => config('services.newsdata.key'),
                    'category' => 'technology',
                    'language' => 'en',
                    'size'     => max(1, (int) config('services.newsdata.size', 10)),
                ]);
        } catch (Throwable $e) {
            // Connection refused, DNS failure, TLS problem — anything the HTTP
            // client raises rather than returns.
            Log::warning('Tech news feed unreachable.', ['message' => $e->getMessage()]);

            return [];
        }

        if ($response->failed()) {
            // The key and the url are deliberately not logged.
            Log::warning('Tech news feed returned an error.', ['status' => $response->status()]);

            return [];
        }

        $payload = $response->json();

        if (! is_array($payload) || ($payload['status'] ?? null) !== 'success') {
            Log::warning('Tech news feed returned an unexpected body.', [
                'status' => is_array($payload) ? ($payload['status'] ?? null) : null,
            ]);

            return [];
        }

        return $this->normalise($payload['results'] ?? []);
    }

    /**
     * The API's shape reduced to the handful of fields the card renders.
     *
     * @param  mixed  $results
     * @return array<int, array<string, mixed>>
     */
    protected function normalise(mixed $results): array
    {
        if (! is_array($results)) {
            return [];
        }

        $articles = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $title = $this->clean($result['title'] ?? null);
            $url = is_string($result['link'] ?? null) ? trim($result['link']) : '';

            // A headline with nowhere to go is worse than one fewer card: the
            // whole card is a link out, so both halves are load-bearing.
            if ($title === null || ! $this->isSafeUrl($url)) {
                continue;
            }

            $source = $this->sourceName($result);

            $articles[] = [
                'title'        => Str::limit($title, 130),
                'description'  => $this->describe($result['description'] ?? null),
                'url'          => $url,
                'source'       => $source,
                'initials'     => $this->initials($source),
                'domain'       => $this->domain($result, $url),
                'image'        => $this->imageUrl($result['image_url'] ?? null),
                'published_at' => $this->publishedAt($result['pubDate'] ?? null),
            ];

            if (count($articles) >= self::LIMIT) {
                break;
            }
        }

        return $articles;
    }

    /**
     * A snippet, never a body. The API's own description is already short;
     * this trims it further so a card cannot become a wall of text, and drops
     * the upsell sentinels the free tier returns in place of paid fields.
     */
    protected function describe(mixed $description): ?string
    {
        $text = $this->clean($description);

        if ($text === null || str_contains($text, 'ONLY AVAILABLE IN')) {
            return null;
        }

        return Str::limit($text, 200);
    }

    /**
     * Tags stripped and whitespace collapsed. Descriptions arrive from a few
     * hundred different publishers, and some of them carry markup.
     */
    protected function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5))));

        return $text === '' ? null : $text;
    }

    /**
     * Only ever http(s), and only ever absolute. The url goes straight into an
     * href, so a javascript: or data: value arriving from a third party must
     * not survive this method.
     */
    protected function isSafeUrl(string $url): bool
    {
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /**
     * The publication's name, which the card has to show — attribution is the
     * point of the section. Falls back to the host so a card is never
     * unattributed; an article that cannot name its source is dropped by the
     * url check above long before it reaches here.
     */
    protected function sourceName(array $result): string
    {
        $name = $this->clean($result['source_name'] ?? null)
            ?? $this->clean($result['source_id'] ?? null);

        return $name ?? 'Unknown source';
    }

    protected function domain(array $result, string $url): string
    {
        $host = parse_url(
            is_string($result['source_url'] ?? null) && $result['source_url'] !== ''
                ? $result['source_url']
                : $url,
            PHP_URL_HOST,
        );

        return is_string($host) ? preg_replace('/^www\./', '', $host) : '';
    }

    /**
     * The article's own thumbnail, or null.
     *
     * Held to the same scheme check as the link — an img src is as good a
     * place to smuggle something as an href. A url that passes here can still
     * 404, be hotlink-blocked, or be an http image on an https page; the card
     * renders its placeholder underneath the image for exactly those cases, so
     * a thumbnail that fails at load time falls back rather than breaking.
     */
    protected function imageUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);

        return $this->isSafeUrl($url) ? $url : null;
    }

    /**
     * A monogram for the publication, shown where an article has no image.
     * Derived from the source name so the fallback still says who wrote it.
     */
    protected function initials(string $source): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $source, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $letters = array_map(
            fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)),
            array_slice($words, 0, 2),
        );

        return $letters === [] ? '·' : implode('', $letters);
    }
    protected function publishedAt(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            // pubDateTZ is UTC on every plan; the API documents pubDate in it.
            return Carbon::parse($value, 'UTC');
        } catch (Throwable) {
            return null;
        }
    }
}
