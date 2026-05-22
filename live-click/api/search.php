<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
requireLogin();
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (!$q) { echo json_encode(['ok' => false, 'results' => []]); exit; }

// Use Spotify if credentials are configured, otherwise fall back to MusicBrainz
if (SPOTIFY_CLIENT_ID && SPOTIFY_CLIENT_SECRET) {
    $results = searchSpotify($q);
} else {
    $results = searchMusicBrainz($q);
}

echo json_encode(['ok' => true, 'results' => $results]);

/* =========================================
   Spotify search (with BPM via audio-features)
   ========================================= */
function searchSpotify(string $q): array {
    $token = getSpotifyToken();
    if (!$token) return searchMusicBrainz($q);

    // 1. Search for tracks
    $url  = 'https://api.spotify.com/v1/search?q=' . urlencode($q) . '&type=track&limit=10';
    $raw  = spotifyGet($url, $token);
    if (!$raw) return searchMusicBrainz($q);

    $data   = json_decode($raw, true);
    $tracks = $data['tracks']['items'] ?? [];
    if (!$tracks) return [];

    // 2. Fetch audio features for all track IDs in one call
    $ids        = implode(',', array_column($tracks, 'id'));
    $featRaw    = spotifyGet('https://api.spotify.com/v1/audio-features?ids=' . $ids, $token);
    $featByIdRaw = [];
    if ($featRaw) {
        $featData = json_decode($featRaw, true);
        foreach ($featData['audio_features'] ?? [] as $f) {
            if ($f && isset($f['id'])) $featByIdRaw[$f['id']] = $f;
        }
    }

    $results = [];
    foreach ($tracks as $t) {
        $artists  = implode(', ', array_map(fn($a) => $a['name'], $t['artists'] ?? []));
        $duration = '';
        if (!empty($t['duration_ms'])) {
            $ms  = (int)$t['duration_ms'];
            $min = floor($ms / 60000);
            $sec = floor(($ms % 60000) / 1000);
            $duration = sprintf('%d:%02d', $min, $sec);
        }

        $feat = $featByIdRaw[$t['id']] ?? null;
        $bpm  = $feat ? (int)round($feat['tempo']) : null;
        $key  = $feat ? spotifyKey($feat['key'], $feat['mode'] ?? 1) : null;

        $results[] = [
            'title'        => $t['name'] ?? '',
            'artist'       => $artists,
            'duration'     => $duration,
            'bpm'          => $bpm,
            'key'          => $key,
            'energy'       => $feat ? round($feat['energy'] * 100) : null,
            'danceability' => $feat ? round($feat['danceability'] * 100) : null,
            'spotify_id'   => $t['id'],
        ];
    }
    return $results;
}

function spotifyGet(string $url, string $token): string|false {
    $ctx = stream_context_create(['http' => [
        'timeout' => 8,
        'header'  => "Authorization: Bearer $token\r\nAccept: application/json\r\n",
    ]]);
    return @file_get_contents($url, false, $ctx);
}

function getSpotifyToken(): string|false {
    // Read cached token
    $cacheFile = SPOTIFY_TOKEN_CACHE_FILE;
    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if ($cache && $cache['expires_at'] > time() + 60) {
            return $cache['token'];
        }
    }

    // Request new token via Client Credentials flow
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

    $cache = [
        'token'      => $data['access_token'],
        'expires_at' => time() + (int)($data['expires_in'] ?? 3600),
    ];
    @file_put_contents($cacheFile, json_encode($cache));
    return $cache['token'];
}

function spotifyKey(int $key, int $mode): string {
    $keys = ['C','C#','D','D#','E','F','F#','G','G#','A','A#','B'];
    $name = $keys[$key] ?? '?';
    return $name . ($mode === 1 ? ' maj' : ' min');
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

    $data   = json_decode($raw, true);
    $results = [];
    foreach ($data['recordings'] ?? [] as $r) {
        $artist   = $r['artist-credit'][0]['artist']['name'] ?? '';
        $duration = '';
        if (!empty($r['length'])) {
            $ms  = (int)$r['length'];
            $min = floor($ms / 60000);
            $sec = floor(($ms % 60000) / 1000);
            $duration = sprintf('%d:%02d', $min, $sec);
        }
        $results[] = [
            'title'    => $r['title'] ?? '',
            'artist'   => $artist,
            'duration' => $duration,
            'bpm'      => null,
            'key'      => null,
        ];
    }
    return $results;
}
