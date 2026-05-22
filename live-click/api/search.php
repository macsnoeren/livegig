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
   Tunebat  (uses Spotify data, no credentials needed)
   ========================================= */
function searchTunebat(string $q): array {
    $url = 'https://api.tunebat.com/api/tracks/data?q=' . urlencode($q) . '&p=1';
    $ctx = stream_context_create(['http' => [
        'timeout' => 8,
        'ignore_errors' => true,
        'header' => implode("\r\n", [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept: application/json, text/plain, */*',
            'Accept-Language: en-US,en;q=0.9',
            'Origin: https://tunebat.com',
            'Referer: https://tunebat.com/',
        ]),
    ]]);

    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return [];

    $data  = json_decode($raw, true);
    $items = $data['data']['items'] ?? [];
    if (!$items) return [];

    $keys = ['C','C#','D','D#','E','F','F#','G','G#','A','A#','B'];

    $results = [];
    foreach ($items as $t) {
        $artist = '';
        if (isset($t['b'])) {
            $artist = is_array($t['b']) ? implode(', ', $t['b']) : (string)$t['b'];
        }

        $duration = '';
        if (!empty($t['f'])) {
            $ms  = (int)$t['f'];
            $min = floor($ms / 60000);
            $sec = floor(($ms % 60000) / 1000);
            $duration = sprintf('%d:%02d', $min, $sec);
        }

        $bpm = isset($t['j']) ? (int)round((float)$t['j']) : null;

        $key = null;
        if (isset($t['k']) && $t['k'] >= 0) {
            $keyName = $keys[$t['k']] ?? '?';
            $mode    = isset($t['l']) ? (int)$t['l'] : 1;
            $key     = $keyName . ($mode === 1 ? ' maj' : ' min');
        }

        $results[] = [
            'title'        => $t['a'] ?? '',
            'artist'       => $artist,
            'duration'     => $duration,
            'bpm'          => $bpm,
            'key'          => $key,
            'energy'       => isset($t['m']) ? (int)round((float)$t['m'] * 100) : null,
            'danceability' => isset($t['n']) ? (int)round((float)$t['n'] * 100) : null,
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

    $ids = implode(',', array_column($tracks, 'id'));
    $featRaw = spotifyGet('https://api.spotify.com/v1/audio-features?ids=' . $ids, $token);
    $featById = [];
    if ($featRaw) {
        foreach (json_decode($featRaw, true)['audio_features'] ?? [] as $f) {
            if ($f && isset($f['id'])) $featById[$f['id']] = $f;
        }
    }

    $keys    = ['C','C#','D','D#','E','F','F#','G','G#','A','A#','B'];
    $results = [];
    foreach ($tracks as $t) {
        $artists  = implode(', ', array_map(fn($a) => $a['name'], $t['artists'] ?? []));
        $duration = '';
        if (!empty($t['duration_ms'])) {
            $ms = (int)$t['duration_ms'];
            $duration = sprintf('%d:%02d', floor($ms/60000), floor(($ms%60000)/1000));
        }
        $feat = $featById[$t['id']] ?? null;
        $results[] = [
            'title'        => $t['name'] ?? '',
            'artist'       => $artists,
            'duration'     => $duration,
            'bpm'          => $feat ? (int)round($feat['tempo']) : null,
            'key'          => $feat ? (($keys[$feat['key']] ?? '?') . ($feat['mode'] ? ' maj' : ' min')) : null,
            'energy'       => $feat ? (int)round($feat['energy'] * 100) : null,
            'danceability' => $feat ? (int)round($feat['danceability'] * 100) : null,
        ];
    }
    return $results;
}

function spotifyGet(string $url, string $token): string|false {
    $ctx = stream_context_create(['http' => [
        'timeout' => 8,
        'ignore_errors' => true,
        'header'  => "Authorization: Bearer $token\r\nAccept: application/json\r\n",
    ]]);
    return @file_get_contents($url, false, $ctx);
}

function getSpotifyToken(): string|false {
    $cacheFile = SPOTIFY_TOKEN_CACHE_FILE;
    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if ($cache && $cache['expires_at'] > time() + 60) return $cache['token'];
    }
    $credentials = base64_encode(SPOTIFY_CLIENT_ID . ':' . SPOTIFY_CLIENT_SECRET);
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'timeout' => 8,
        'header'  => "Authorization: Basic $credentials\r\nContent-Type: application/x-www-form-urlencoded\r\n",
        'content' => 'grant_type=client_credentials',
    ]]);
    $raw = @file_get_contents('https://accounts.spotify.com/api/token', false, $ctx);
    if (!$raw) return false;
    $data = json_decode($raw, true);
    if (empty($data['access_token'])) return false;
    $cache = ['token' => $data['access_token'], 'expires_at' => time() + (int)($data['expires_in'] ?? 3600)];
    @file_put_contents($cacheFile, json_encode($cache));
    return $cache['token'];
}

/* =========================================
   MusicBrainz fallback (no BPM)
   ========================================= */
function searchMusicBrainz(string $q): array {
    $url = 'https://musicbrainz.org/ws/2/recording/?query=' . urlencode($q) . '&fmt=json&limit=10';
    $ctx = stream_context_create(['http' => [
        'timeout' => 8,
        'header'  => "User-Agent: LiveGig/1.0 (macsnoeren@gmail.com)\r\n",
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!$raw) return [];

    $results = [];
    foreach (json_decode($raw, true)['recordings'] ?? [] as $r) {
        $artist = $r['artist-credit'][0]['artist']['name'] ?? '';
        $duration = '';
        if (!empty($r['length'])) {
            $ms = (int)$r['length'];
            $duration = sprintf('%d:%02d', floor($ms/60000), floor(($ms%60000)/1000));
        }
        $results[] = ['title' => $r['title'] ?? '', 'artist' => $artist, 'duration' => $duration, 'bpm' => null, 'key' => null];
    }
    return $results;
}
