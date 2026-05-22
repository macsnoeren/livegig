<?php
require_once __DIR__ . '/auth.php';
$user = currentUser();
$activeBands = $user ? userBands($user['id']) : [];
?>
<!doctype html>
<html lang="nl" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'LiveGig') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>

<!-- FIXED CLICK TRACK BAR -->
<nav id="clicktrack-nav" class="navbar fixed-top">
    <div class="container-fluid px-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="brand-name me-2">LiveGig</span>

            <!-- Beat dots -->
            <div class="beat-dots">
                <div id="beat_1" class="beat-dot"><span>1</span></div>
                <div id="beat_2" class="beat-dot"><span>2</span></div>
                <div id="beat_3" class="beat-dot"><span>3</span></div>
                <div id="beat_4" class="beat-dot"><span>4</span></div>
            </div>

            <!-- BPM display -->
            <div id="ct-bpm" class="ct-bpm-display">-- BPM</div>

            <!-- Song name display -->
            <div id="ct-song" class="ct-song-display text-truncate">--</div>

            <!-- Controls -->
            <button id="btn-start" class="btn btn-danger btn-sm px-3" onclick="ctStart()">
                <i class="bi bi-play-fill"></i> START
            </button>
            <button id="btn-stop" class="btn btn-outline-secondary btn-sm px-3" onclick="ctStop()">
                <i class="bi bi-stop-fill"></i> STOP
            </button>

            <!-- Options -->
            <div class="d-flex align-items-center gap-2 ms-1">
                <div class="form-check form-switch mb-0" title="Auto-stop na 25s">
                    <input class="form-check-input" type="checkbox" id="ct-automode" checked onchange="ctToggleAuto()">
                    <label class="form-check-label small" for="ct-automode">Auto</label>
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="ct-soundmode" onchange="ctToggleSound()">
                    <label class="form-check-label small" for="ct-soundmode">Sound</label>
                </div>
            </div>

            <!-- Setlist selector -->
            <?php if ($user): ?>
            <div class="dropdown ms-1">
                <button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-music-note-list"></i> Setlist
                </button>
                <ul id="setlist-dropdown" class="dropdown-menu dropdown-menu-dark">
                    <li><a class="dropdown-item text-muted" href="#">Geen band geselecteerd</a></li>
                </ul>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right side: nav + user -->
        <?php if ($user): ?>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <!-- Band switcher -->
            <?php if (count($activeBands) > 1): ?>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-people"></i> <?= htmlspecialchars($user['band_name'] ?? 'Band') ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                    <?php foreach ($activeBands as $b): ?>
                    <li>
                        <a class="dropdown-item <?= $b['id'] == $user['band_id'] ? 'active' : '' ?>"
                           href="/api/switch-band.php?band_id=<?= $b['id'] ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
                            <?= htmlspecialchars($b['name']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php elseif ($user['band_name']): ?>
            <span class="text-muted small"><i class="bi bi-people"></i> <?= htmlspecialchars($user['band_name']) ?></span>
            <?php endif; ?>

            <!-- Main nav -->
            <a href="/dashboard.php" class="btn btn-sm btn-outline-light <?= (basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'active' : '' ?>">
                <i class="bi bi-house"></i>
            </a>
            <a href="/songs.php" class="btn btn-sm btn-outline-light <?= (basename($_SERVER['PHP_SELF']) === 'songs.php') ? 'active' : '' ?>">
                <i class="bi bi-music-note-beamed"></i> Nummers
            </a>
            <a href="/setlists.php" class="btn btn-sm btn-outline-light <?= (basename($_SERVER['PHP_SELF']) === 'setlists.php') ? 'active' : '' ?>">
                <i class="bi bi-list-ol"></i> Setlists
            </a>
            <?php if ($user['role'] === 'admin'): ?>
            <a href="/admin.php" class="btn btn-sm btn-outline-warning <?= (basename($_SERVER['PHP_SELF']) === 'admin.php') ? 'active' : '' ?>">
                <i class="bi bi-shield"></i>
            </a>
            <?php endif; ?>

            <!-- User menu -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['username']) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                    <li><a class="dropdown-item" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Uitloggen</a></li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
</nav>

<!-- PAGE CONTENT WRAPPER -->
<div id="page-content">
