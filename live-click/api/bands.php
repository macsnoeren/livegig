<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = currentUser();
$isAdmin = $user['role'] === 'admin';

function getBandsForUser(PDO $db, int $userId, bool $isAdmin): array {
    if ($isAdmin) {
        $bands = $db->query('SELECT * FROM bands ORDER BY name')->fetchAll();
    } else {
        $stmt = $db->prepare(
            'SELECT b.* FROM bands b JOIN band_members bm ON bm.band_id = b.id
             WHERE bm.user_id = ? ORDER BY b.name'
        );
        $stmt->execute([$userId]);
        $bands = $stmt->fetchAll();
    }
    foreach ($bands as &$b) {
        $stmt = $db->prepare('SELECT u.id, u.username FROM band_members bm JOIN users u ON u.id=bm.user_id WHERE bm.band_id=?');
        $stmt->execute([$b['id']]);
        $b['members'] = $stmt->fetchAll();
    }
    return $bands;
}

if ($method === 'GET') {
    echo json_encode(['ok' => true, 'bands' => getBandsForUser($db, $user['id'], $isAdmin)]);
    exit;
}

if ($method === 'POST') {
    $data      = json_decode(file_get_contents('php://input'), true);
    $id        = (int)($data['id'] ?? 0);
    $name      = trim($data['name'] ?? '');
    $desc      = trim($data['description'] ?? '');
    $memberIds = $data['member_ids'] ?? null; // null = caller did not send member list

    if (!$name) { echo json_encode(['ok' => false, 'error' => 'Naam verplicht']); exit; }

    if ($id) {
        // Edit — allowed for admins or band members
        if (!$isAdmin) {
            $chk = $db->prepare('SELECT 1 FROM band_members WHERE band_id=? AND user_id=?');
            $chk->execute([$id, $user['id']]);
            if (!$chk->fetch()) {
                echo json_encode(['ok' => false, 'error' => 'Geen toegang']); exit;
            }
        }
        $db->prepare('UPDATE bands SET name=?,description=? WHERE id=?')->execute([$name, $desc, $id]);

        // Only update members when the caller explicitly sent a member list (admin UI)
        if ($isAdmin && $memberIds !== null) {
            $db->prepare('DELETE FROM band_members WHERE band_id=?')->execute([$id]);
            $ins = $db->prepare('INSERT OR IGNORE INTO band_members (user_id,band_id) VALUES (?,?)');
            foreach ($memberIds as $uid) { $ins->execute([(int)$uid, $id]); }
        }
    } else {
        // Create — any logged-in user; creator is auto-added as member
        $db->prepare('INSERT INTO bands (name,description) VALUES (?,?)')->execute([$name, $desc]);
        $id = $db->lastInsertId();

        if ($isAdmin && $memberIds !== null) {
            // Admin UI: use the supplied member list
            $ins = $db->prepare('INSERT OR IGNORE INTO band_members (user_id,band_id) VALUES (?,?)');
            foreach ($memberIds as $uid) { $ins->execute([(int)$uid, $id]); }
        } else {
            // Regular user: add creator as member
            $db->prepare('INSERT OR IGNORE INTO band_members (user_id,band_id) VALUES (?,?)')->execute([$user['id'], $id]);
        }
    }

    echo json_encode(['ok' => true, 'id' => $id]);
    exit;
}

if ($method === 'DELETE') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $bandId = (int)($data['band_id'] ?? 0);
    $userId = (int)($data['user_id'] ?? 0);
    $id     = (int)($data['id'] ?? 0);

    if ($bandId && $userId) {
        // Remove a member — allowed for admins or fellow band members
        if (!$isAdmin) {
            $chk = $db->prepare('SELECT 1 FROM band_members WHERE band_id=? AND user_id=?');
            $chk->execute([$bandId, $user['id']]);
            if (!$chk->fetch()) {
                echo json_encode(['ok' => false, 'error' => 'Geen toegang']); exit;
            }
        }
        $db->prepare('DELETE FROM band_members WHERE band_id=? AND user_id=?')->execute([$bandId, $userId]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($id) {
        // Delete entire band — admin only
        requireAdmin();
        $db->prepare('DELETE FROM bands WHERE id=?')->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Geen id']); exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
