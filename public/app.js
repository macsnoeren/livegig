/*
  LiveGig - App logic
  Click track, song list, setlist management.
  Author: Maurice Snoeren (drummer of Blast!)
*/

var _bpm = 80;
var _ctStarted = false;
var _counter = 0;
var _autoStopTime = 25;         // Seconds
var _autoStop = _autoStopTime;  // Automode on by default
var _soundOn = false;
var _tsPrevious = 0;

var _allSongs = [];         // Loaded from API
var _setlistsData = [];     // Loaded from API

// ── Click track ──────────────────────────────────────────────────────────────

function startClickTrack(b) {
    _bpm = parseInt(b) || 80;
    setClickTrackBPMDisplay(_bpm);
    if (!_ctStarted) {
        _counter = 0;
        _ctStarted = true;
        executeClickTrack();
        if (_autoStop !== 0) {
            setTimeout(stopClickTrack, _autoStop * 1000);
        }
    }
}

function executeClickTrack() {
    var ts = performance.now();
    if (_ctStarted) {
        // High-precision timing: wake up 20ms early, then spin-wait for exact moment
        if (_tsPrevious === 0 || (ts - _tsPrevious) > (1000 * 60 / _bpm) - 20) {
            while ((ts - _tsPrevious) < (1000 * 60 / _bpm)) {
                ts = performance.now();
            }
            resetColorClickTrackDisplay();
            if (_soundOn) {
                createSource(() => {});
            }
            setColorClickTrackDisplay(_counter);
            _counter = (_counter + 1) % 4;

            $("#debug").text("Timing: " + (ts - _tsPrevious).toFixed(1) + " ms");
            _tsPrevious = ts;
        }
        setTimeout(executeClickTrack, 0);
    }
}

function stopClickTrack() {
    _ctStarted = false;
    _counter = 0;
    _tsPrevious = 0;
    resetColorClickTrackDisplay();
    setClickTrackBPMDisplay("--");
}

function resetColorClickTrackDisplay() {
    for (var i = 0; i < 4; i++) {
        $("#clicktrack_" + (i + 1)).css("background-color", "#BBB");
    }
}

function setColorClickTrackDisplay(i) {
    $("#clicktrack_" + (i + 1)).css("background-color", "#F00");
}

function setClickTrackBPMDisplay(b) {
    $("#clicktrack_bpm").text(b + " BPM");
}

function toggleAutoMode() {
    _autoStop = $("#automode").is(":checked") ? _autoStopTime : 0;
}

function toggleSoundMode() {
    _soundOn = $("#soundmode").is(":checked");
}

// ── Song list rendering ───────────────────────────────────────────────────────

function populateSongs(songs) {
    $("#songs").html("");
    if (!songs || songs.length === 0) {
        $("#songs").html('<p class="text-secondary p-3">Geen nummers gevonden.</p>');
        return;
    }
    songs.forEach((s, i) => {
        const bpmVal = parseInt(s.bpm) || 0;
        const bpmBtn = bpmVal > 0
            ? `href="javascript:startClickTrack(${bpmVal})"`
            : `href="#"`;
        $("#songs").append(
            `<a id="songs_${i+1}" ${bpmBtn} class="list-group-item list-group-item-action list-group-item-dark">
                <p class="h2 mb-1">${i+1}. ${escHtml(s.title)}</p>
                <p class="mb-1 text-secondary">${escHtml(s.artist)} &bull; ${bpmVal > 0 ? bpmVal + ' BPM' : '?'} &bull; start: <strong class="text-light">${escHtml(s.starts)}</strong></p>
                ${s.description ? `<p class="mb-0 text-secondary small">${s.description}</p>` : ''}
            </a>`
        );
    });
}

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Setlist dropdown ──────────────────────────────────────────────────────────

function populateAvailableLists(setlists) {
    $("#availablelists").html("");
    // "All songs" option
    $("#availablelists").append(
        '<li><a class="dropdown-item" href="javascript:populateSongs(_allSongs)">Alle nummers</a></li>'
    );
    if (setlists && setlists.length > 0) {
        $("#availablelists").append('<li><hr class="dropdown-divider"></li>');
        setlists.forEach((sl, i) => {
            $("#availablelists").append(
                `<li><a class="dropdown-item" href="javascript:loadSetlist(${sl.id})">${escHtml(sl.title)}</a></li>`
            );
        });
    }
}

function loadSetlist(id) {
    const sl = _setlistsData.find(s => s.id === id);
    if (sl && sl.songs) {
        populateSongs(sl.songs);
    }
}
