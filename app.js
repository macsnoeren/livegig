/* Blast! - Live
   Author: Maurice Snoeren (drummer of Blast!)
   Live gig application!
*/


var _bpm = 80;
var _ctStarted = false;
var _counter = 0;

function startClickTrack(b) {
    _bpm = b;
    setClickTrackBPMDisplay(b);
    if ( !_ctStarted ) {
        _counter = 0;
        _ctStarted = true;
        executeClickTrack();
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
        $("#songs").append('<a href="#" onclick="startClickTrack(' + s.bpm + ')" class="list-group-item list-group-item-action bg-secondary text-light" aria-current="true"><p class="h1">' + 
        (i+1) + '. ' + s.title + '</p><p>, ' + s.artist + '</p><p>' + s.description + '</p></a>');
        console.log(s);
    }
}