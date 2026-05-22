<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

function getBandsWithMembers(PDO $db): array {
    $bands = $db->query('SELECT * FROM bands ORDER BY name')->fetchAll();
    foreach ($bands as &$b) {
        $stmt = $db->prepare('SELECT u.id, u.username FROM band_members bm JOIN users u ON u.id=bm.user_id WHERE bm.band_id=?');
        $stmt->execute([$b['id']]);
        $b['members'] = $stmt->fetchAll();
    }
    return $bands;
}

if ($method === 'GET') {
    echo json_encode(['ok'=>true,'bands'=>getBandsWithMembers($db)]);
    exit;
}

if ($method === 'POST') {
    requireAdmin();
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = (int)($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $desc = trim($data['description'] ?? '');
    $memberIds = $data['member_ids'] ?? [];

    if (!$name) { echo json_encode(['ok'=>false,'error'=>'Naam verplicht']); exit; }

    if ($id) {
        $db->prepare('UPDATE bands SET name=?,description=? WHERE id=?')->execute([$name,$desc,$id]);
    } else {
        $db->prepare('INSERT INTO bands (name,description) VALUES (?,?)')->execute([$name,$desc]);
        $id = $db->lastInsertId();
    }

    $db->prepare('DELETE FROM band_members WHERE band_id=?')->execute([$id]);
    $ins = $db->prepare('INSERT OR IGNORE INTO band_members (user_id,band_id) VALUES (?,?)');
    foreach ($memberIds as $uid) { $ins->execute([(int)$uid,$id]); }

    echo json_encode(['ok'=>true,'id'=>$id]);
    exit;
}

http_response_code(405);
echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
