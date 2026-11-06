<?php
declare(strict_types=1);

/** Everything the app knows about YouTube: parsing links, looking up titles, optional search. */
final class Youtube
{
    public const ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';

    /** Accepts watch, youtu.be, shorts, embed and live links, music.youtube.com, or a bare 11-character id. */
    public static function extractId(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        if (preg_match(self::ID_PATTERN, $input)) {
            return $input;
        }
        if (!preg_match('~^(?:https?://)?([^/?#]+)(/[^?#]*)?(?:\?([^#]*))?~i', $input, $m)) {
            return null;
        }
        $host = preg_replace('/^(?:www|m|music)\./', '', strtolower($m[1]));
        $path = $m[2] ?? '';
        parse_str($m[3] ?? '', $query);

        $id = null;
        if ($host === 'youtu.be') {
            $id = explode('/', ltrim($path, '/'))[0];
        } elseif ($host === 'youtube.com' || $host === 'youtube-nocookie.com') {
            if ($path === '/watch') {
                $id = $query['v'] ?? null;
            } elseif (preg_match('~^/(?:embed|shorts|live|v)/([^/]+)~', $path, $mm)) {
                $id = $mm[1];
            }
        }
        return is_string($id) && preg_match(self::ID_PATTERN, $id) ? $id : null;
    }

    /**
     * Title and channel from YouTube's public oEmbed endpoint. The URL is built from the validated id,
     * never from user input. When the lookup fails, `found` is false and the person types the title.
     */
    public static function lookup(string $id): array
    {
        $result = ['videoId' => $id, 'title' => '', 'artist' => '', 'thumbnail' => thumb($id), 'found' => false];
        $url = 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode('https://www.youtube.com/watch?v=' . $id);
        $body = self::httpGet($url, 4);
        $data = $body === null ? null : json_decode($body, true);
        if (is_array($data) && !empty($data['title'])) {
            [$title, $artist] = self::tidy((string) $data['title'], (string) ($data['author_name'] ?? ''));
            $result['title'] = $title;
            $result['artist'] = $artist;
            $result['found'] = $title !== '';
        }
        return $result;
    }

    /** Turns "Artist - Song (Official Video)" on "ArtistVEVO" into ['Song', 'Artist']. */
    public static function tidy(string $title, string $channel): array
    {
        $title = html_entity_decode(trim($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $noise = '/\s*[\(\[]\s*(?:official\s+)?(?:music\s+)?(?:video|audio|lyrics?(?:\s+video)?|visuali[sz]er|hd|hq|4k)\s*[\)\]]/iu';
        $title = trim(preg_replace($noise, '', $title) ?? $title);

        $artist = html_entity_decode(trim($channel), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $artist = preg_replace(['/\s+-\s+Topic$/i', '/(?<=[a-z0-9])VEVO$/'], '', $artist) ?? $artist;

        if (preg_match('/^(.{1,60}?)\s+[-–—]\s+(.+)$/u', $title, $m)) {
            $artist = trim($m[1]);
            $title = trim($m[2]);
        }

        $limits = limits();
        return [
            mb_substr($title, 0, $limits['title_max']),
            mb_substr(trim($artist), 0, $limits['artist_max']),
        ];
    }

    /** Search needs a YouTube Data API key. Returns null when the API can't be reached or refuses. */
    public static function search(string $query, string $apiKey, int $max = 8): ?array
    {
        $params = http_build_query([
            'part' => 'snippet',
            'type' => 'video',
            'videoEmbeddable' => 'true',
            'videoCategoryId' => '10',
            'maxResults' => $max,
            'q' => $query,
            'key' => $apiKey,
        ], '', '&', PHP_QUERY_RFC3986);
        $body = self::httpGet('https://www.googleapis.com/youtube/v3/search?' . $params, 5);
        $json = $body === null ? null : json_decode($body, true);
        return is_array($json) ? self::parseSearch($json) : null;
    }

    public static function parseSearch(array $json): array
    {
        $results = [];
        foreach ($json['items'] ?? [] as $item) {
            $id = $item['id']['videoId'] ?? null;
            if (!is_string($id) || !preg_match(self::ID_PATTERN, $id)) {
                continue;
            }
            [$title, $artist] = self::tidy((string) ($item['snippet']['title'] ?? ''), (string) ($item['snippet']['channelTitle'] ?? ''));
            if ($title !== '') {
                $results[] = ['videoId' => $id, 'title' => $title, 'artist' => $artist, 'thumbnail' => thumb($id)];
            }
        }
        return $results;
    }

    private static function httpGet(string $url, int $timeout): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'MixtapeApp/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        return $body !== false && $status === 200 ? (string) $body : null;
    }
}
