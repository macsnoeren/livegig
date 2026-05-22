<?php
/**
 * LiveGig configuration
 *
 * Spotify Developer credentials:
 * 1. Ga naar https://developer.spotify.com/dashboard
 * 2. Maak een nieuwe app aan ("LiveGig")
 * 3. Zet de Client ID en Client Secret hieronder
 *
 * Let op: het audio-features endpoint (BPM) is gedepreceerd voor apps
 * aangemaakt na 27 november 2024. Oudere apps werken nog volledig.
 */
define('SPOTIFY_CLIENT_ID',     '');  // Vul in
define('SPOTIFY_CLIENT_SECRET', '');  // Vul in

// Token cache: sla Spotify access token op in sessie (verlopen na 1 uur)
define('SPOTIFY_TOKEN_CACHE_FILE', __DIR__ . '/../data/.spotify_token');

/**
 * GetSongBPM API — gratis na registratie op https://getsongbpm.com/api
 * Geeft BPM, toonsoort en dansbaarheidsscore per nummer.
 */
define('GETSONGBPM_API_KEY', '');  // Vul in
