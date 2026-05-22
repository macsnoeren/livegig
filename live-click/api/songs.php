<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $bandId = $_GET['band_id'] ?? null;
    if ($bandId) {
        $stmt = $db->prepare('SELECT * FROM songs WHERE band_id = ? ORDER BY title COLLATE NOCASE');
        $stmt->execute([(int)$bandId]);
    } else {
        $stmt = $db->query('SELECT * FROM songs ORDER BY title COLLATE NOCASE');
    }
    echo json_encode(['ok' => true, 'songs' => $stmt->fetchAll()]);
    exit;
}

if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id     = (int)($data['id'] ?? 0);
    $title  = trim($data['title'] ?? '');
    $artist = trim($data['artist'] ?? '');
    if (!$title || !$artist) { echo json_encode(['ok'=>false,'error'=>'Titel en artiest verplicht']); exit; }

    $bpm        = isset($data['bpm']) && $data['bpm'] !== '' ? (int)$data['bpm'] : null;
    $key        = trim($data['song_key']    ?? '') ?: null;
    $dur        = trim($data['duration']    ?? '') ?: null;
    $starts     = trim($data['starts']      ?? '') ?: null;
    $desc       = trim($data['description'] ?? '') ?: null;
    $previewUrl = trim($data['preview_url'] ?? '') ?: null;
    $spotifyId  = trim($data['spotify_id']  ?? '') ?: null;
    // band_id: treat empty string / "null" / 0 all as NULL
    $rawBand = $data['band_id'] ?? null;
    $bandId  = ($rawBand !== null && $rawBand !== '' && $rawBand !== 'null' && (int)$rawBand > 0)
               ? (int)$rawBand : null;

    try {
        if ($id) {
            $db->prepare('UPDATE songs SET title=?,artist=?,bpm=?,song_key=?,duration=?,starts=?,description=?,preview_url=?,spotify_id=? WHERE id=?')
               ->execute([$title, $artist, $bpm, $key, $dur, $starts, $desc, $previewUrl, $spotifyId, $id]);
        } else {
            $db->prepare('INSERT INTO songs (title,artist,bpm,song_key,duration,starts,description,preview_url,spotify_id,band_id,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
               ->execute([$title, $artist, $bpm, $key, $dur, $starts, $desc, $previewUrl, $spotifyId, $bandId, currentUser()['id']]);
            $id = $db->lastInsertId();
        }
        echo json_encode(['ok' => true, 'id' => $id]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => 'Database fout: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'Geen id']); exit; }
    $db->prepare('DELETE FROM songs WHERE id=?')->execute([$id]);
    echo json_encode(['ok'=>true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
