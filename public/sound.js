/*
This file is part of Blast! software for live gig performances, called BlastLive.

BlastLive is free software: you can redistribute it and/or modify it under the terms
of the GNU General Public License as published by the Free Software Foundation, either
version 3 of the License, or (at your option) any later version.

BlastLive is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with Foobar.
If not, see <https://www.gnu.org/licenses/>.

https://anthony-dandrea.medium.com/low-latency-web-audio-doesnt-have-to-be-hard-7e602a772319
*/

const audioContext = new (window.AudioContext || window.webkitAudioContext)();
const audioSrc = 'https://storage.googleapis.com/absolute-bot-264323/audio/DB90_Samples/QuarterNote-loudest.mp3';

let buffer = null;
let startOffset = null;

fetch(audioSrc, onSuccess);

function fetch(url, resolve) {
    var request = new XMLHttpRequest();
    request.open('GET', url, true);
    request.responseType = 'arraybuffer';
    request.onload = function () { resolve(request); };
    request.send();
}

function onSuccess(request) {
    var audioData = request.response;
    audioContext.decodeAudioData(audioData,
        (buf) => { buffer = buf; },
        (e) => { console.error('Error decoding audio buffer: ' + e.message); }
    );
}

function preloadBufferNode() {
    const source = audioContext.createBufferSource();
    source.buffer = buffer;
    source.connect(audioContext.destination);
    return source;
}

function createSource(callback) {
    if (!buffer) return;
    const source = preloadBufferNode();
    source.onended = callback;
    startOffset = startOffset || audioContext.currentTime + 60 / 120;
    source.start(startOffset);
}
