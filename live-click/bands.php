<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$user = currentUser();
$pageTitle = 'Bands — LiveGig';
require __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-people-fill"></i> Mijn bands</h4>
        <button class="btn btn-danger btn-sm" onclick="openAddBand()">
            <i class="bi bi-plus-lg"></i> Band aanmaken
        </button>
    </div>

    <div id="bands-container" class="row g-3">
        <div class="col-12 text-muted">Laden...</div>
    </div>
</div>

<!-- Band Modal -->
<div class="modal fade" id="bandModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="bandModalTitle">Band aanmaken</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="band-id">
                <div class="mb-3">
                    <label class="form-label">Bandnaam <span class="text-danger">*</span></label>
                    <input type="text" id="band-name" class="form-control" placeholder="Bijv. The Rolling Stones">
                </div>
                <div class="mb-3">
                    <label class="form-label">Beschrijving</label>
                    <textarea id="band-description" class="form-control" rows="2"></textarea>
                </div>
                <?php if ($user['role'] === 'admin'): ?>
                <div class="mb-3" id="members-section">
                    <label class="form-label">Leden</label>
                    <div id="band-members-list" class="small"></div>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button class="btn btn-danger" onclick="saveBand()">
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
                <h5 class="modal-title">Band verwijderen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Weet je zeker dat je <strong id="delete-name"></strong> wilt verwijderen?
                <div class="small text-warning mt-2"><i class="bi bi-exclamation-triangle"></i> Alle nummers en setlists van deze band worden ook verwijderd.</div>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                <button class="btn btn-danger" onclick="confirmDelete()">Verwijderen</button>
            </div>
        </div>
    </div>
</div>

<?php
$isAdmin = $user['role'] === 'admin';
$extraScripts = '<script>
var _allBands  = [];
var _allUsers  = [];
var _deleteBandId = null;
var _isAdmin = ' . ($isAdmin ? 'true' : 'false') . ';

$(function() {
    loadBands();
    ' . ($isAdmin ? 'loadAllUsers();' : '') . '
});

function loadBands() {
    $.get("api/bands.php", function(data) {
        _allBands = data.bands || [];
        renderBands(_allBands);
    });
}

function loadAllUsers() {
    $.get("api/users.php", function(data) {
        _allUsers = data.users || [];
    });
}

function renderBands(bands) {
    var c = $("#bands-container"); c.empty();
    if (!bands.length) {
        c.html(\'<div class="col-12"><div class="card"><div class="card-body text-muted">\'
            + \'Je bent nog geen lid van een band. Maak een nieuwe band aan om te beginnen.\'
            + \'</div></div></div>\');
        return;
    }
    bands.forEach(function(b, i) {
        var members = (b.members || []).map(function(m){ return escHtml(m.username); }).join(", ");
        var isActive = (b.id == ' . ($user['band_id'] ?? 0) . ');
        var activeIndicator = isActive ? \'<span class="badge bg-danger ms-2">Actief</span>\' : \'\';
        var deleteBtn = _isAdmin
            ? \'<button class="btn btn-xs btn-outline-danger ms-1" onclick="openDeleteBand(\' + b.id + \',\\\'\' + escHtml(b.name) + \'\\\')" title="Verwijderen"><i class="bi bi-trash"></i></button>\'
            : \'\';
        c.append(\'<div class="col-md-6 col-lg-4">\'
            + \'<div class="card h-100"><div class="card-body d-flex flex-column">\'
            + \'<div class="d-flex justify-content-between align-items-start mb-2">\'
            + \'<h6 class="fw-bold mb-0">\' + escHtml(b.name) + activeIndicator + \'</h6>\'
            + \'<div class="d-flex gap-1">\'
            + \'<button class="btn btn-xs btn-outline-secondary" onclick="openEditBand(\' + i + \')" title="Bewerken"><i class="bi bi-pencil"></i></button>\'
            + deleteBtn
            + \'</div></div>\'
            + (b.description ? \'<p class="text-muted small mb-2">\' + escHtml(b.description) + \'</p>\' : \'\')
            + \'<p class="small mb-0 mt-auto"><i class="bi bi-people text-muted me-1"></i>\'
            + (members || \'<span class="text-muted">Geen leden</span>\') + \'</p>\'
            + \'</div></div></div>\');
    });
}

function openAddBand() {
    $("#bandModalTitle").text("Band aanmaken");
    $("#band-id").val("");
    $("#band-name").val("");
    $("#band-description").val("");
    if (_isAdmin) renderMemberCheckboxes([]);
    new bootstrap.Modal("#bandModal").show();
}

function openEditBand(i) {
    var b = _allBands[i];
    if (!b) return;
    $("#bandModalTitle").text("Band bewerken");
    $("#band-id").val(b.id);
    $("#band-name").val(b.name);
    $("#band-description").val(b.description || "");
    if (_isAdmin) renderMemberCheckboxes((b.members || []).map(function(m){ return m.id; }));
    new bootstrap.Modal("#bandModal").show();
}

function renderMemberCheckboxes(selectedIds) {
    var c = $("#band-members-list"); c.empty();
    if (!_allUsers.length) { c.text("Geen gebruikers beschikbaar."); return; }
    _allUsers.forEach(function(u) {
        c.append(\'<div class="form-check">\'
            + \'<input class="form-check-input" type="checkbox" id="bm_\' + u.id + \'" value="\' + u.id + \'" \' + (selectedIds.includes(u.id) ? "checked" : "") + \'>\'
            + \'<label class="form-check-label" for="bm_\' + u.id + \'">\' + escHtml(u.username) + \'</label>\'
            + \'</div>\');
    });
}

function saveBand() {
    var name = $("#band-name").val().trim();
    if (!name) { alert("Bandnaam is verplicht."); return; }
    var data = {
        id: $("#band-id").val(),
        name: name,
        description: $("#band-description").val().trim()
    };
    if (_isAdmin) {
        var memberIds = [];
        $("#band-members-list input:checked").each(function() { memberIds.push(parseInt($(this).val())); });
        data.member_ids = memberIds;
    }
    $.post("api/bands.php", JSON.stringify(data), function(r) {
        if (r.ok) {
            bootstrap.Modal.getInstance("#bandModal").hide();
            loadBands();
        } else { alert(r.error || "Fout bij opslaan"); }
    }, "json");
}

function openDeleteBand(id, name) {
    _deleteBandId = id;
    $("#delete-name").text(name);
    new bootstrap.Modal("#deleteModal").show();
}

function confirmDelete() {
    $.ajax({ url: "api/bands.php", type: "DELETE", data: JSON.stringify({id: _deleteBandId}),
        contentType: "application/json", success: function(r) {
            bootstrap.Modal.getInstance("#deleteModal").hide();
            loadBands();
        }
    });
}
</script>';
require __DIR__ . '/includes/footer.php';
?>
