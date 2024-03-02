/* Blast! - Live
   Author: Maurice Snoeren (drummer of Blast!)
   Live gig application!
*/

var _counter = 0;
var _totalImages = _images.length;
var _diaTime = 10000;
var _diaRun = false;
var _diaVideo = false;

function preloadImages () {
    for ( let i=0; i < _images.length; i++ ) {
        let img = new Image();
        img.src = 'images/dias/' + _images[i];
    }
}

function startDiaShow() {
    _diaRun = true;
    showLogo(true);
    showVideo(false);
    stopVideo();
    nextFrame();
}

function nextFrame() {
    if ( _diaRun ) {
        let img = 'images/dias/' + encodeURIComponent(_images[_counter].trim()); // Encoding so files are shown
        img = img.replace(/\(/g, '\\\('); // For background-image URL curly braces are not shown
        img = img.replace(/\)/g, '\\\)');
        
        if ( img.search(/mp4$/) >= 0 || img.search(/webm$/) >= 0 ) { // Video found!
            console.log('Show video: ' + img);
            diaStartVideo(img);
            _diaVideo = true;

        } else { // image found
            if ( _diaVideo ) {
                diaStopVideo(img);
                _diaVideo = false;
            }
            console.log('Show image: ' + img);
            $('body').css('background-image', "url(" + img + ")");
        }

        _counter++;
        if ( _counter >= _totalImages ) {
            _counter = 0;
        }

        setTimeout(nextFrame, _diaTime);
   
    } else {
        blackScreen();
    }
}

function blackScreen () {
    $('body').css('background-image', "none");
    showLogo(false);
    showVideo(false);
    stopVideo();
}

function showLogo(state) {
    if ( state ) {
        $('#logo').show();
    } else {
        $('#logo').hide();        
    }
}

function showVideo(state) {
    if ( state ) {
        $('#background-video').show();
    } else {
        $('#background-video').hide();
    }
}

function playVideo() {
    $('#background-video').get(0).currentTime = 0;
    $('#background-video').get(0).load();
    $('#background-video').get(0).play();
}

function stopVideo() {
    $('#background-video').get(0).pause();
}

function diaStartVideo(video) {
    $('#background-video').get(0).pause();
    $('#background-video').get(0).src = video;
    $('#background-video').get(0).load();
    $('#background-video').get(0).play();
    showVideo(true);
}

function diaStopVideo(video) {
    stopVideo();
    showVideo(false);
    //$('#background-video').get(0).src = 'videos/test.mp4';
    //$('#background-video').get(0).load();
}

