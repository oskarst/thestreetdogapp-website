<?php
/**
 * Server-side proxy for the woof.thestreetdogapp.com recent-sightings endpoint.
 *
 * Why this exists:
 * - The upstream endpoint on woof is locked to server-side callers via a shared
 *   API key. We keep that key out of the browser by calling upstream from PHP
 *   and serving the (cached) JSON back to the page.
 * - When upstream is not yet configured (or unreachable) we fall back to a
 *   small curated sample so the section never looks broken.
 *
 * Upstream contract (when wired up on woof's Next.js side):
 *   GET https://woof.thestreetdogapp.com/api/recent-sightings
 *   Header: X-API-Key: <shared secret>
 *   Returns JSON: { "items": [ { dog_name, dog_id, city, country,
 *                                condition, contributor, sighted_at,
 *                                image_url }, ... ] }
 *
 * Config (set in nginx fastcgi_param or php-fpm pool):
 *   SIGHTINGS_UPSTREAM_URL  full URL of upstream endpoint
 *   SIGHTINGS_API_KEY       shared secret sent as X-API-Key
 */

header('Content-Type: application/json; charset=utf-8');
// Browsers/CDNs can keep this for an hour; the server cache holds for a day.
header('Cache-Control: public, max-age=3600, s-maxage=86400');

// Apache mod_php exposes SetEnv values via $_SERVER but not always via
// getenv(); php-fpm uses env[] entries that show up in getenv(). Check both.
$upstreamUrl = getenv('SIGHTINGS_UPSTREAM_URL') ?: ($_SERVER['SIGHTINGS_UPSTREAM_URL'] ?? '');
$apiKey      = getenv('SIGHTINGS_API_KEY')      ?: ($_SERVER['SIGHTINGS_API_KEY']      ?? '');

$cacheFile = sys_get_temp_dir() . '/tsda_sightings_cache.json';
$ttl       = 86400; // 24 hours

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
    readfile($cacheFile);
    exit;
}

$items = null;
$source = 'fallback';

if ($upstreamUrl && $apiKey && function_exists('curl_init')) {
    $ch = curl_init($upstreamUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => [
            'X-API-Key: ' . $apiKey,
            'Accept: application/json',
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && is_string($body) && $body !== '') {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            if (isset($decoded['items']) && is_array($decoded['items'])) {
                $items = $decoded['items'];
            } elseif (array_is_list_compat($decoded)) {
                $items = $decoded;
            }
            if ($items !== null) {
                $source = 'upstream';
            }
        }
    }
}

if ($items === null) {
    $now = time();
    $items = [
        [
            'dog_name'    => 'Frank',
            'dog_id'      => 1042,
            'city'        => 'Tbilisi',
            'country'     => 'Georgia',
            'condition'   => 'Healthy',
            'contributor' => 'nino',
            'sighted_at'  => date('c', $now -  8 * 60),
            'image_url'   => '/img/pexels-mati-mango-4052809.jpg',
        ],
        [
            'dog_name'    => 'Bubu',
            'dog_id'      => 871,
            'city'        => 'Batumi',
            'country'     => 'Georgia',
            'condition'   => 'Re-sighted',
            'contributor' => 'levan',
            'sighted_at'  => date('c', $now - 47 * 60),
            'image_url'   => '/img/pexels-cristina-anskaja-10433404.jpg',
        ],
        [
            'dog_name'    => null,
            'dog_id'      => 2103,
            'city'        => 'Istanbul',
            'country'     => 'Turkey',
            'condition'   => 'New record',
            'contributor' => 'aylin',
            'sighted_at'  => date('c', $now -  2 * 3600),
            'image_url'   => null,
        ],
        [
            'dog_name'    => 'Kupata',
            'dog_id'      => 414,
            'city'        => 'Kutaisi',
            'country'     => 'Georgia',
            'condition'   => 'Tagged',
            'contributor' => 'maia',
            'sighted_at'  => date('c', $now -  4 * 3600),
            'image_url'   => '/img/pexels-raynand-yray-ii-6559738 (1).jpg',
        ],
    ];
}

$payload = json_encode(
    ['items' => $items, 'source' => $source, 'cached_at' => date('c')],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

@file_put_contents($cacheFile, $payload, LOCK_EX);

echo $payload;

/**
 * PHP 8.1+ has array_is_list(); guard for older runtimes.
 */
function array_is_list_compat(array $a): bool
{
    if (function_exists('array_is_list')) {
        return array_is_list($a);
    }
    $i = 0;
    foreach ($a as $k => $_) {
        if ($k !== $i++) return false;
    }
    return true;
}
