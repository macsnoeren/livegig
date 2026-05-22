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
    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id   = (int)($data['id'] ?? 0);
    $title = trim($data['title'] ?? '');
    $artist = trim($data['artist'] ?? '');
    if (!$title || !$artist) { echo json_encode(['ok'=>false,'error'=>'Titel en artiest verplicht']); exit; }

    if ($id) {
        $stmt = $db->prepare('UPDATE songs SET title=?,artist=?,bpm=?,duration=?,starts=?,description=? WHERE id=?');
        $stmt->execute([$title,$artist,$data['bpm']??null,$data['duration']??null,$data['starts']??null,$data['description']??null,$id]);
    } else {
        $stmt = $db->prepare('INSERT INTO songs (title,artist,bpm,duration,starts,description,band_id,created_by) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$title,$artist,$data['bpm']??null,$data['duration']??null,$data['starts']??null,$data['description']??null,$data['band_id']??null,currentUser()['id']]);
        $id = $db->lastInsertId();
    }
    echo json_encode(['ok'=>true,'id'=>$id]);
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
