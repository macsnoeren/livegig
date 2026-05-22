<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
requireLogin();
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (!$q) { echo json_encode(['ok' => false, 'results' => [], 'source' => 'none']); exit; }

// Priority: 1. Tunebat  2. Spotify  3. MusicBrainz
$results = searchTunebat($q);
$source  = 'tunebat';

if (!$results && SPOTIFY_CLIENT_ID && SPOTIFY_CLIENT_SECRET) {
    $results = searchSpotify($q);
    $source  = 'spotify';
}

if (!$results) {
    $results = searchMusicBrainz($q);
    $source  = 'musicbrainz';
}

echo json_encode(['ok' => true, 'results' => $results, 'source' => $source]);

/* =========================================
   Helpers
   ========================================= */

/**
 * Camelot Wheel: maps (key 0-11, mode 1=maj/0=min) to notation like "8B", "5A".
 * Useful for harmonic mixing — adjacent numbers are compatible keys.
 */
function computeCamelot(int $keyIdx, int $mode): string {
    static $maj = ['8B','3B','10B','5B','12B','7B','2B','9B','4B','11B','6B','1B'];
    static $min = ['5A','12A','7A','2A','9A','4A','11A','6A','1A','8A','3A','10A'];
    if ($keyIdx < 0 || $keyIdx > 11) return '';
    return $mode === 1 ? $maj[$keyIdx] : $min[$keyIdx];
}

function buildKeyFields(int $keyIdx, int $mode): array {
    static $names = ['C','C#','D','D#','E','F','F#','G','G#','A','A#','B'];
    if ($keyIdx < 0 || $keyIdx > 11) return ['key' => null, 'camelot' => null];
    return [
        'key'     => $names[$keyIdx] . ($mode === 1 ? ' maj' : ' min'),
        'camelot' => computeCamelot($keyIdx, $mode),
    ];
}

function msDuration(int $ms): string {
    return sprintf('%d:%02d', floor($ms / 60000), floor(($ms % 60000) / 1000));
}

/* =========================================
   HTTP helpers
   ========================================= */

/** cURL GET — returns body string or false. */
function curlGet(string $url, array $headers, int $timeout = 8): string|false {
    if (!function_exists('curl_init')) return false;
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',   // auto decode gzip/deflate/br
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code !== 200) return false;
    // Guard against Cloudflare HTML challenge pages
    $first = ltrim($body)[0] ?? '';
    if ($first !== '{' && $first !== '[') return false;
    return $body;
}

/** file_get_contents fallback for environments without cURL. */
function httpGet(string $url, array $headerLines, int $timeout = 8): string|false {
    $ctx = stream_context_create(['http' => [
        'timeout'       => $timeout,
        'ignore_errors' => true,
        'header'        => implode("\r\n", $headerLines),
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return false;
    $first = ltrim($raw)[0] ?? '';
    if ($first !== '{' && $first !== '[') return false;
    return $raw;
}

/* =========================================
   Tunebat  (Spotify data, no credentials needed)
   Field map (compact single-letter keys):
     a  = title
     b  = artist(s) — string or array
     f  = duration_ms
     j  = tempo / BPM
     k  = key index 0-11 (C,C#,D,D#,E,F,F#,G,G#,A,A#,B)
     l  = mode: 1=major, 0=minor
     m  = energy 0-1
     n  = danceability 0-1
     o  = valence 0-1 (happiness/positivity)
     p  = popularity 0-100 (integer)
   ========================================= */
function searchTunebat(string $q): array {
    $url = 'https://api.tunebat.com/api/tracks/data?q=' . urlencode($q) . '&p=1';

    $browserHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Accept: application/json, text/plain, */*',
        'Accept-Language: nl-NL,nl;q=0.9,en-US;q=0.8,en;q=0.7',
        'Cache-Control: no-cache',
        'Pragma: no-cache',
        'Origin: https://tunebat.com',
        'Referer: https://tunebat.com/Search/' . rawurlencode($q),
        'Sec-Fetch-Dest: empty',
        'Sec-Fetch-Mode: cors',
        'Sec-Fetch-Site: same-site',
        'sec-ch-ua: "Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'Connection: keep-alive',
    ];

    $raw = curlGet($url, $browserHeaders)
        ?? httpGet($url, $browserHeaders);

    if (!$raw) return [];

    $data  = json_decode($raw, true);
    $items = $data['data']['items'] ?? [];
    if (!$items) return [];

    $results = [];
    foreach ($items as $t) {
        $title  = isset($t['a']) && is_string($t['a']) ? $t['a'] : '';
        $artist = '';
        if (isset($t['b'])) {
            $artist = is_array($t['b']) ? implode(', ', array_filter($t['b'])) : (string)$t['b'];
        }

        $duration = !empty($t['f']) ? msDuration((int)$t['f']) : '';
        $bpm      = isset($t['j']) && is_numeric($t['j']) ? (int)round((float)$t['j']) : null;

        $keyFields = ['key' => null, 'camelot' => null];
        if (isset($t['k']) && is_numeric($t['k']) && (int)$t['k'] >= 0 && (int)$t['k'] <= 11) {
            $keyFields = buildKeyFields((int)$t['k'], isset($t['l']) ? (int)$t['l'] : 1);
        }

        $pct = function($v) { return isset($v) && is_numeric($v) ? (int)round((float)$v * 100) : null; };

        // 'p' is popularity (0-100 integer in Tunebat), not a 0-1 float
        $popularity = isset($t['p']) && is_numeric($t['p']) ? (int)$t['p'] : null;

        $results[] = [
            'title'        => $title,
            'artist'       => $artist,
            'duration'     => $duration,
            'bpm'          => $bpm,
            'key'          => $keyFields['key'],
            'camelot'      => $keyFields['camelot'],
            'energy'       => $pct($t['m'] ?? null),
            'danceability' => $pct($t['n'] ?? null),
            'valence'      => $pct($t['o'] ?? null),
            'popularity'   => $popularity,
        ];
    }
    return $results;
}

/* =========================================
   Spotify (requires credentials in config.php)
   ========================================= */
function searchSpotify(string $q): array {
    $token = getSpotifyToken();
    if (!$token) return [];

    $raw = spotifyGet('https://api.spotify.com/v1/search?q=' . urlencode($q) . '&type=track&limit=10', $token);
    if (!$raw) return [];

    $data   = json_decode($raw, true);
    $tracks = $data['tracks']['items'] ?? [];
    if (!$tracks) return [];

    // Fetch audio features for all tracks in one call
    $ids     = implode(',', array_column($tracks, 'id'));
    $featRaw = spotifyGet('https://api.spotify.com/v1/audio-features?ids=' . $ids, $token);
    $featById = [];
    if ($featRaw) {
        foreach (json_decode($featRaw, true)['audio_features'] ?? [] as $f) {
            if ($f && isset($f['id'])) $featById[$f['id']] = $f;
        }
    }

    $results = [];
    foreach ($tracks as $t) {
        $artists  = implode(', ', array_map(fn($a) => $a['name'], $t['artists'] ?? []));
        $duration = !empty($t['duration_ms']) ? msDuration((int)$t['duration_ms']) : '';
        $feat     = $featById[$t['id']] ?? null;

        $keyFields = ['key' => null, 'camelot' => null];
        if ($feat && isset($feat['key']) && $feat['key'] >= 0 && $feat['key'] <= 11) {
            $keyFields = buildKeyFields($feat['key'], $feat['mode'] ?? 1);
        }

        $results[] = [
            'title'        => $t['name'] ?? '',
            'artist'       => $artists,
            'duration'     => $duration,
            'bpm'          => $feat ? (int)round((float)$feat['tempo']) : null,
            'key'          => $keyFields['key'],
            'camelot'      => $keyFields['camelot'],
            'energy'       => $feat ? (int)round((float)$feat['energy'] * 100) : null,
            'danceability' => $feat ? (int)round((float)$feat['danceability'] * 100) : null,
            'valence'      => $feat ? (int)round((float)$feat['valence'] * 100) : null,
            'popularity'   => $t['popularity'] ?? null,
        ];
    }
    return $results;
}

function spotifyGet(string $url, string $token): string|false {
    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ];
    return curlGet($url, $headers)
        ?? httpGet($url, ['Authorization: Bearer ' . $token, 'Accept: application/json']);
}

function getSpotifyToken(): string|false {
    $cacheFile = SPOTIFY_TOKEN_CACHE_FILE;
    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if ($cache && $cache['expires_at'] > time() + 60) return $cache['token'];
    }

    $credentials = base64_encode(SPOTIFY_CLIENT_ID . ':' . SPOTIFY_CLIENT_SECRET);

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://accounts.spotify.com/api/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . $credentials,
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'timeout' => 8,
            'header'  => "Authorization: Basic $credentials\r\nContent-Type: application/x-www-form-urlencoded\r\n",
            'content' => 'grant_type=client_credentials',
        ]]);
        $raw = @file_get_contents('https://accounts.spotify.com/api/token', false, $ctx);
    }

    if (!$raw) return false;
    $data = json_decode($raw, true);
    if (empty($data['access_token'])) return false;
    $cache = ['token' => $data['access_token'], 'expires_at' => time() + (int)($data['expires_in'] ?? 3600)];
    @file_put_contents($cacheFile, json_encode($cache));
    return $cache['token'];
}

/* =========================================
   MusicBrainz fallback (no BPM / key)
   ========================================= */
function searchMusicBrainz(string $q): array {
    $url = 'https://musicbrainz.org/ws/2/recording/?query=' . urlencode($q) . '&fmt=json&limit=10';
    $raw = httpGet($url, ['User-Agent: LiveGig/1.0 (macsnoeren@gmail.com)']);
    if (!$raw) return [];

    $results = [];
    foreach (json_decode($raw, true)['recordings'] ?? [] as $r) {
        $artist   = $r['artist-credit'][0]['artist']['name'] ?? '';
        $duration = !empty($r['length']) ? msDuration((int)$r['length']) : '';
        $results[] = [
            'title'        => $r['title'] ?? '',
            'artist'       => $artist,
            'duration'     => $duration,
            'bpm'          => null,
            'key'          => null,
            'camelot'      => null,
            'energy'       => null,
            'danceability' => null,
            'valence'      => null,
            'popularity'   => null,
        ];
    }
    return $results;
}
