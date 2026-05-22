<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$user = currentUser();
$pageTitle = 'Nummers — LiveGig';
require __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-music-note-beamed"></i> Repertoire
            <?php if ($user['band_name']): ?>
            <span class="badge bg-secondary ms-2"><?= htmlspecialchars($user['band_name']) ?></span>
            <?php endif; ?>
        </h4>
        <div class="d-flex gap-2">
            <input type="search" id="song-filter" class="form-control form-control-sm" placeholder="Zoeken..." style="width:200px">
            <button class="btn btn-danger btn-sm" onclick="openAddSong()">
                <i class="bi bi-plus-lg"></i> Nummer toevoegen
            </button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-dark table-hover table-sm mb-0" id="songs-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Titel</th>
                        <th>Artiest</th>
                        <th class="text-center">BPM</th>
                        <th>Duur</th>
                        <th>Wie start</th>
                        <th>Beschrijving</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="songs-tbody">
                    <tr><td colspan="8" class="text-muted">Laden...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Song Modal -->
<div class="modal fade" id="songModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="songModalTitle">Nummer toevoegen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Music search -->
                <div class="mb-3 p-3 bg-black rounded">
                    <label class="form-label small text-muted">Zoek nummer online (MusicBrainz)</label>
                    <div class="input-group">
                        <input type="text" id="search-query" class="form-control" placeholder="Bijv. Highway to Hell ACDC">
                        <button class="btn btn-outline-secondary" onclick="searchMusic()">
                            <i class="bi bi-search"></i> Zoek
                        </button>
                    </div>
                    <div id="search-results" class="mt-2"></div>
                </div>

                <form id="song-form">
                    <input type="hidden" id="song-id">
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label">Titel *</label>
                            <input type="text" id="song-title" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">BPM</label>
                            <input type="number" id="song-bpm" class="form-control" min="1" max="400">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Artiest *</label>
                            <input type="text" id="song-artist" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Duur</label>
                            <input type="text" id="song-duration" class="form-control" placeholder="mm:ss">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Wie start</label>
                            <input type="text" id="song-starts" class="form-control" placeholder="Bijv. Drums, Gitaar">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Beschrijving / notities</label>
                            <textarea id="song-description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button type="button" class="btn btn-danger" onclick="saveSong()">
                    <i class="bi bi-check-lg"></i> Opslaan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete confirm -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Nummer verwijderen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Weet je zeker dat je <strong id="delete-name"></strong> wilt verwijderen?
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Nee</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">Verwijderen</button>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script>
var _deleteSongId = null;
$(function() { loadSongsTable(); });

function loadSongsTable() {
    var bandId = ' . ($user['band_id'] ?? 'null') . ';
    $.get("/api/songs.php", {band_id: bandId}, function(data) {
        renderSongsTable(data.songs || []);
    });
}

function renderSongsTable(songs) {
    var tbody = $("#songs-tbody");
    tbody.empty();
    if (!songs.length) { tbody.append(\'<tr><td colspan="8" class="text-muted">Geen nummers gevonden</td></tr>\'); return; }
    songs.forEach(function(s, i) {
        tbody.append(
            \'<tr data-title="\' + escHtml(s.title) + \'" data-artist="\' + escHtml(s.artist) + \'">\' +
            \'<td class="text-muted">\' + (i+1) + \'</td>\' +
            \'<td class="fw-semibold">\' + escHtml(s.title) + \'</td>\' +
            \'<td class="text-muted">\' + escHtml(s.artist) + \'</td>\' +
            \'<td class="text-center"><span class="bpm-badge">\' + (s.bpm || "--") + \'</span></td>\' +
            \'<td class="text-muted">\' + escHtml(s.duration || "") + \'</td>\' +
            \'<td>\' + escHtml(s.starts || "") + \'</td>\' +
            \'<td class="text-muted small">\' + escHtml(s.description || "").substring(0,60) + \'</td>\' +
            \'<td><button class="btn btn-xs btn-outline-secondary me-1" onclick="openEditSong(\' + JSON.stringify(s) + \')"><i class="bi bi-pencil"></i></button>\' +
            \'<button class="btn btn-xs btn-outline-danger" onclick="openDeleteSong(\' + s.id + \',\\\'\' + escHtml(s.title) + \'\\\')"><i class="bi bi-trash"></i></button></td>\' +
            \'</tr>\'
        );
    });
}

$("#song-filter").on("input", function() {
    var q = $(this).val().toLowerCase();
    $("#songs-tbody tr").each(function() {
        var t = ($(this).data("title") || "").toLowerCase();
        var a = ($(this).data("artist") || "").toLowerCase();
        $(this).toggle(!q || t.includes(q) || a.includes(q));
    });
});

function openAddSong() {
    $("#songModalTitle").text("Nummer toevoegen");
    $("#song-form")[0].reset();
    $("#song-id").val("");
    $("#search-results").empty();
    new bootstrap.Modal("#songModal").show();
}

function openEditSong(s) {
    $("#songModalTitle").text("Nummer bewerken");
    $("#song-id").val(s.id);
    $("#song-title").val(s.title);
    $("#song-artist").val(s.artist);
    $("#song-bpm").val(s.bpm);
    $("#song-duration").val(s.duration);
    $("#song-starts").val(s.starts);
    $("#song-description").val(s.description);
    $("#search-results").empty();
    new bootstrap.Modal("#songModal").show();
}

function saveSong() {
    var data = {
        id: $("#song-id").val(),
        title: $("#song-title").val().trim(),
        artist: $("#song-artist").val().trim(),
        bpm: $("#song-bpm").val(),
        duration: $("#song-duration").val().trim(),
        starts: $("#song-starts").val().trim(),
        description: $("#song-description").val().trim(),
        band_id: ' . ($user['band_id'] ?? 'null') . '
    };
    if (!data.title || !data.artist) { alert("Titel en artiest zijn verplicht."); return; }
    $.post("/api/songs.php", data, function(r) {
        if (r.ok) {
            bootstrap.Modal.getInstance("#songModal").hide();
            loadSongsTable();
        } else { alert(r.error || "Fout bij opslaan"); }
    });
}

function openDeleteSong(id, name) {
    _deleteSongId = id;
    $("#delete-name").text(name);
    new bootstrap.Modal("#deleteModal").show();
}

function confirmDelete() {
    $.ajax({ url: "/api/songs.php", type: "DELETE", data: JSON.stringify({id: _deleteSongId}),
        contentType: "application/json", success: function(r) {
            bootstrap.Modal.getInstance("#deleteModal").hide();
            loadSongsTable();
        }
    });
}

function searchMusic() {
    var q = $("#search-query").val().trim();
    if (!q) return;
    $("#search-results").html(\'<span class="text-muted">Zoeken...</span>\');
    $.get("/api/search.php", {q: q}, function(data) {
        if (!data.results || !data.results.length) {
            $("#search-results").html(\'<span class="text-muted">Geen resultaten</span>\');
            return;
        }
        var html = \'<div class="list-group list-group-flush mt-1">\';
        data.results.slice(0,8).forEach(function(r) {
            html += \'<button class="list-group-item list-group-item-action list-group-item-dark py-1 px-2 small" onclick="fillSongFromSearch(\' + JSON.stringify(r) + \')">\'
                 + \'<strong>\' + escHtml(r.title) + \'</strong> — \' + escHtml(r.artist)
                 + (r.duration ? \' <span class="text-muted">(\' + r.duration + \')</span>\' : \'\')
                 + \'</button>\';
        });
        html += \'</div>\';
        $("#search-results").html(html);
    });
}

function fillSongFromSearch(r) {
    $("#song-title").val(r.title);
    $("#song-artist").val(r.artist);
    if (r.duration) $("#song-duration").val(r.duration);
    if (r.bpm) $("#song-bpm").val(r.bpm);
    $("#search-results").empty();
}
</script>';
require __DIR__ . '/includes/footer.php';
?>
