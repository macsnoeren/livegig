/* Blast! - Live
   Author: Maurice Snoeren (drummer of Blast!)
   Live gig application!
*/

var _bpm = 80;
var _ctStarted = false;
var _counter = 0;
var _autoStopTime = 25; // seconds
var _autoStop = 0;
var _soundOn = false;
var _tsPrevious = 0;

var clap = new Audio()
clap.src = 'sounds/clap-2-95736.mp3'
clap.preload = 'auto'
clap.load()

function startClickTrack(b) {
    _bpm = b;
    setClickTrackBPMDisplay(b);
    if ( !_ctStarted ) {
        _counter = 0;
        _ctStarted = true;
        executeClickTrack();

        if ( _autoStop != 0 ) {
            setTimeout(stopClickTrack, _autoStop*1000);
        }
    }
}

function executeClickTrack () {
    var ts = performance.now();

    if ( _ctStarted ) {
        // Improve precision by waiting when the timing is close enough, in this case 20ms before the actual
        // timing is required. Then wait inside the method until the actual timing is there.
        // Further improvement due to the use of performance.now(). It is 0.1ms accuracy. 
        if ( _tsPrevious == 0 || (ts - _tsPrevious) > (1000*60/_bpm) - 20 ) { // precision on 20 ms
            while ( (ts - _tsPrevious) < (1000*60/_bpm)) { // Not <=, so the timing is more accurate.
                ts = performance.now();
            }

            if ( _soundOn ) {
                clap.currentTime = 0;
                clap.play();
            }

            resetColorClickTrackDisplay();
            setColorClickTrackDisplay(_counter);
            _counter = (_counter + 1) % 4;

            $("#debug").text("Timing: " + (ts - _tsPrevious).toFixed(1) + " ms");
            _tsPrevious = ts;
        }

        setTimeout(executeClickTrack, 0); // Schedule as soon as possible to create high precision timing!
    }
}

function stopClickTrack() {
    _ctStarted = false;
    _counter = 0;
    resetColorClickTrackDisplay();
    setClickTrackBPMDisplay("--");
}

function resetColorClickTrackDisplay() {
    for ( var i=0; i < 8; i++ ) {
        $("#clicktrack_" + (i+1)).css("background-color", "#BBB");
    }
}

function setColorClickTrackDisplay(i) {
    $("#clicktrack_" + (i+1)).css("background-color", "#F00");
}

function setClickTrackBPMDisplay(b) {
    $("#clicktrack_bpm").text(b + " BPM");
}

function populateSongs(songs) {
    $("#songs").html("");
    for ( var i=0; i < songs.length; i++ ) {
        s = songs[i];
        $("#songs").append('<a id="songs_' + (i+1) + '" href="javascript:startClickTrack(' + s.bpm + ')" class="list-group-item list-group-item-action list-group-item-dark"><p class="h1">' + 
        (i+1) + '. ' + s.title + '</p><p>' + s.artist + ' (' + s.bpm + ' BPM), who starts: <b>' + s.starts + '</b></p><p>' + s.description + '</p></a>');
    }
}

function populateAvailableLists () {
    $("#availablelists").html("");

    for ( var i=0; i < getLists().length; i++ ) {
        s = getLists()[i];
        $("#availablelists").append('<li><a class="dropdown-item" href="javascript:populateList(' + i + ')">' + s.title + '</a></li>');
    }    
}

function populateList(l) {
    populateSongs(getListSongs(l));
}

function toggleAutoMode () {
    if ( $("#automode").is(":checked") ) {
        _autoStop = _autoStopTime;
    } else {
        _autoStop = 0;
    }
}

function toggleSoundMode () {
    if ( $("#soundmode").is(":checked") ) {
        _soundOn = true;
    } else {
        _soundOn = false;
    }
}
