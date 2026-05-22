<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$user = currentUser();
$pageTitle = 'Dashboard — LiveGig';
require __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 py-3">

    <?php if (!$user['band_id']): ?>
    <div class="alert alert-warning alert-dismissible page-alert fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Je bent nog niet aan een band gekoppeld.</strong>
        <?php if ($user['role'] === 'admin'): ?>
            Ga naar <a href="admin.php" class="alert-link">Admin → Bands</a> om jezelf toe te voegen.
        <?php else: ?>
            Een admin moet jou koppelen aan een band. Neem contact op met de beheerder.
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <!-- Setlist panel -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-ol"></i> Actieve setlist</span>
                    <select id="setlist-select" class="form-select form-select-sm w-auto" onchange="loadSetlist(this.value)">
                        <option value="">— kies setlist —</option>
                    </select>
                </div>
                <div id="setlist-songs" class="list-group list-group-flush overflow-auto" style="max-height:calc(100vh - 220px)">
                    <div class="list-group-item text-muted">Selecteer een setlist</div>
                </div>
            </div>
        </div>

        <!-- Current song detail -->
        <div class="col-lg-8">
            <div class="card mb-3" id="song-detail-card" style="display:none!important">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <h3 id="detail-title" class="mb-0 fw-bold text-white"></h3>
                            <p id="detail-artist" class="text-muted mb-1"></p>
                            <p id="detail-desc" class="small mb-0"></p>
                        </div>
                        <div class="col-auto text-end">
                            <div class="bpm-big" id="detail-bpm">--</div>
                            <div class="text-muted small">BPM</div>
                            <div class="mt-1">Wie start: <strong id="detail-starts">--</strong></div>
                            <div class="text-muted small" id="detail-duration"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- All songs fallback / search -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-music-note-beamed"></i> Alle nummers</span>
                    <input type="search" id="song-search" class="form-control form-control-sm w-auto" placeholder="Zoek nummer...">
                </div>
                <div id="all-songs" class="list-group list-group-flush overflow-auto" style="max-height:calc(100vh - 280px)">
                    <div class="list-group-item text-muted">Laden...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script>
var BAND_ID = ' . ($user['band_id'] ? (int)$user['band_id'] : 'null') . ';
$(function() {
    loadAllSongs();
    loadSetlistDropdown();

    $("#song-search").on("input", function() {
        filterSongs($(this).val());
    });
});
</script>';
require __DIR__ . '/includes/footer.php';
?>
