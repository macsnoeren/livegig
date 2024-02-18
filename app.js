/* Blast! - Live
   Author: Maurice Snoeren (drummer of Blast!)
   Live gig application!
*/

var _bpm = 80;
var _ctStarted = false;
var _counter = 0;
var _autoStop = 10;

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
    if ( _ctStarted ) {
        resetColorClickTrackDisplay();
        setColorClickTrackDisplay(_counter);
        _counter = (_counter + 1) % 4;    
        setTimeout(executeClickTrack, 1000*60/_bpm);
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
    $("#clicktrack_bpm").text(b);
}

function populateSongs(songs) {
    $("#songs").html("");
    for ( var i=0; i < songs.length; i++ ) {
        s = songs[i];
        $("#songs").append('<a id="songs_' + (i+1) + '" href="javascript:startClickTrack(' + s.bpm + ')" class="list-group-item list-group-item-action list-group-item-dark"><p class="h1">' + 
        (i+1) + '. ' + s.title + '</p><p>' + s.artist + ' (' + s.bpm + ' BPM)</p><p>' + s.description + '</p></a>');
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