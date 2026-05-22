<?php
require_once __DIR__ . '/includes/auth.php';
sessionStart();
if (currentUser()) { header('Location: /dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (login($username, $password)) {
        header('Location: /dashboard.php');
        exit;
    }
    $error = 'Gebruikersnaam of wachtwoord onjuist.';
}
?>
<!doctype html>
<html lang="nl" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LiveGig — Inloggen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body class="login-page">
<div class="d-flex align-items-center justify-content-center min-vh-100">
    <div class="login-card">
        <div class="text-center mb-4">
            <div class="login-logo">
                <div class="beat-dot active"></div>
                <div class="beat-dot"></div>
                <div class="beat-dot"></div>
                <div class="beat-dot active"></div>
            </div>
            <h1 class="mt-3 fw-bold text-white">LiveGig</h1>
            <p class="text-muted">Click track & setlist beheer</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Gebruikersnaam</label>
                <input type="text" name="username" class="form-control" autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="mb-4">
                <label class="form-label">Wachtwoord</label>
                <input type="password" name="password" class="form-control">
            </div>
            <button type="submit" class="btn btn-danger w-100 fw-bold">
                <i class="bi bi-play-fill"></i> Inloggen
            </button>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
