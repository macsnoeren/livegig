<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$db     = getDB();
$user   = currentUser();
$method = $_SERVER['REQUEST_METHOD'];

function isMember(PDO $db, int $bandId, int $userId): bool {
    $s = $db->prepare('SELECT 1 FROM band_members WHERE band_id=? AND user_id=?');
    $s->execute([$bandId, $userId]);
    return (bool)$s->fetch();
}

if ($method === 'GET') {
    $bandId = (int)($_GET['band_id'] ?? 0);
    if (!$bandId || !isMember($db, $bandId, $user['id'])) {
        echo json_encode(['ok' => false, 'error' => 'Geen toegang']); exit;
    }
    $stmt = $db->prepare('SELECT token FROM band_invites WHERE band_id=?');
    $stmt->execute([$bandId]);
    $row = $stmt->fetch();
    echo json_encode(['ok' => true, 'token' => $row['token'] ?? null]);
    exit;
}

if ($method === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $bandId = (int)($data['band_id'] ?? 0);
    if (!$bandId || !isMember($db, $bandId, $user['id'])) {
        echo json_encode(['ok' => false, 'error' => 'Geen toegang']); exit;
    }
    $token = bin2hex(random_bytes(16));
    $db->prepare('INSERT INTO band_invites (band_id, token, created_by) VALUES (?,?,?)
                  ON CONFLICT(band_id) DO UPDATE SET token=excluded.token, created_by=excluded.created_by, created_at=CURRENT_TIMESTAMP')
       ->execute([$bandId, $token, $user['id']]);
    echo json_encode(['ok' => true, 'token' => $token]);
    exit;
}

if ($method === 'DELETE') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $bandId = (int)($data['band_id'] ?? 0);
    if (!$bandId || !isMember($db, $bandId, $user['id'])) {
        echo json_encode(['ok' => false, 'error' => 'Geen toegang']); exit;
    }
    $db->prepare('DELETE FROM band_invites WHERE band_id=?')->execute([$bandId]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
