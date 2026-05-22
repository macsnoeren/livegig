<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (!$q) { echo json_encode(['ok'=>false,'results'=>[]]); exit; }

// MusicBrainz recording search — free, no API key needed
$url = 'https://musicbrainz.org/ws/2/recording/?query=' . urlencode($q) . '&fmt=json&limit=10';

$ctx = stream_context_create(['http' => [
    'timeout' => 8,
    'header'  => "User-Agent: LiveGig/1.0 (macsnoeren@gmail.com)\r\n",
]]);

$raw = @file_get_contents($url, false, $ctx);
if ($raw === false) {
    echo json_encode(['ok'=>false,'error'=>'Zoekopdracht mislukt','results'=>[]]);
    exit;
}

$json = json_decode($raw, true);
$recordings = $json['recordings'] ?? [];

$results = [];
foreach ($recordings as $r) {
    $artist = '';
    if (!empty($r['artist-credit'][0]['artist']['name'])) {
        $artist = $r['artist-credit'][0]['artist']['name'];
    }

    $duration = '';
    if (!empty($r['length'])) {
        $ms = (int)$r['length'];
        $min = floor($ms / 60000);
        $sec = floor(($ms % 60000) / 1000);
        $duration = sprintf('%d:%02d', $min, $sec);
    }

    $results[] = [
        'title'    => $r['title'] ?? '',
        'artist'   => $artist,
        'duration' => $duration,
        'bpm'      => null,
    ];
}

echo json_encode(['ok'=>true,'results'=>$results]);
