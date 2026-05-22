<?php
require_once __DIR__ . '/db.php';

function sessionStart(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function requireLogin(): void {
    sessionStart();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . appRelPath('login.php'));
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: ' . appRelPath('dashboard.php'));
        exit;
    }
}

// Returns a path relative to the calling script pointing to a file in the app root.
function appRelPath(string $file): string {
    $appRoot   = realpath(__DIR__ . '/..');
    $scriptDir = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
    if (!$appRoot || !$scriptDir || $scriptDir === $appRoot) return $file;
    $prefix = '';
    $dir = $scriptDir;
    while ($dir !== $appRoot && strlen($dir) > strlen($appRoot)) {
        $prefix .= '../';
        $dir = realpath($dir . '/..');
        if (!$dir) break;
    }
    return $prefix . $file;
}

function currentUser(): ?array {
    sessionStart();
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['user_username'],
        'role'     => $_SESSION['user_role'],
        'band_id'  => $_SESSION['user_band_id'] ?? null,
        'band_name'=> $_SESSION['user_band_name'] ?? null,
    ];
}

function login(string $username, string $password): bool {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) return false;

    sessionStart();
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_role']     = $user['role'];

    // Load first band membership
    $bm = $db->prepare('SELECT b.id, b.name FROM band_members bm JOIN bands b ON b.id = bm.band_id WHERE bm.user_id = ? LIMIT 1');
    $bm->execute([$user['id']]);
    $band = $bm->fetch();
    $_SESSION['user_band_id']   = $band['id'] ?? null;
    $_SESSION['user_band_name'] = $band['name'] ?? null;

    return true;
}

function logout(): void {
    sessionStart();
    session_destroy();
}

function userBands(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT b.id, b.name FROM band_members bm JOIN bands b ON b.id = bm.band_id WHERE bm.user_id = ? ORDER BY b.name');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function switchBand(int $bandId): bool {
    sessionStart();
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) return false;
    $db = getDB();
    $stmt = $db->prepare('SELECT b.id, b.name FROM band_members bm JOIN bands b ON b.id = bm.band_id WHERE bm.user_id = ? AND b.id = ?');
    $stmt->execute([$userId, $bandId]);
    $band = $stmt->fetch();
    if (!$band) return false;
    $_SESSION['user_band_id']   = $band['id'];
    $_SESSION['user_band_name'] = $band['name'];
    return true;
}
