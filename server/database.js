const Database = require('better-sqlite3');
const bcrypt = require('bcryptjs');
const path = require('path');

const DB_PATH = path.join(__dirname, 'data', 'livegig.db');
let db;
let _adminExists = false; // cached – flips to true once an admin is created, never back

function getDb() {
    if (!db) {
        db = new Database(DB_PATH);
        db.pragma('journal_mode = WAL');
        db.pragma('foreign_keys = ON');
    }
    return db;
}

function hasAdmin() {
    if (_adminExists) return true;
    const db = getDb();
    const row = db.prepare("SELECT COUNT(*) as c FROM users WHERE role = 'admin'").get();
    _adminExists = row.c > 0;
    return _adminExists;
}

function initDatabase() {
    const db = getDb();

    // Schema
    db.exec(`
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS songs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            title TEXT NOT NULL,
            artist TEXT NOT NULL DEFAULT '',
            bpm TEXT NOT NULL DEFAULT '',
            starts TEXT NOT NULL DEFAULT '',
            description TEXT NOT NULL DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS setlists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            public_token TEXT UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS setlist_songs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            setlist_id INTEGER NOT NULL,
            song_id INTEGER NOT NULL,
            position INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY (setlist_id) REFERENCES setlists(id) ON DELETE CASCADE,
            FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE
        );
    `);

    seedSystemSongs(db);
    // Note: default setlists are seeded after first admin is created via /setup

    console.log('Database initialised at', DB_PATH);
}

function createFirstAdmin(username, password) {
    const db = getDb();
    if (hasAdmin()) throw new Error('Admin already exists');
    const hash = bcrypt.hashSync(password, 10);
    const result = db.prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)').run(username, hash, 'admin');
    const adminId = result.lastInsertRowid;
    _adminExists = true;
    seedDefaultSetlists(db, adminId);
    console.log(`First admin created: ${username}`);
    return { id: adminId, username, role: 'admin' };
}

function seedSystemSongs(db) {
    const count = db.prepare('SELECT COUNT(*) as c FROM songs WHERE user_id IS NULL').get();
    if (count.c > 0) return;

    const insert = db.prepare(
        'INSERT INTO songs (user_id, title, artist, bpm, starts, description) VALUES (NULL, ?, ?, ?, ?, ?)'
    );

    const songs = [
        ["Take a chance on me", "Abba", "107", "Zang", "Abba_Take a chance on me (04:04)"],
        ["Waterloo", "Abba", "148", "", "Abba_Waterloo (02:49)"],
        ["Mama mia", "Abba", "140", "", "Abba_Mama mia (03:45)"],
        ["Highway to hell", "ACDC", "116", "Bob", "ACDC_Highway to hell (03:28)"],
        ["Whole lotta Rosie", "ACDC", "159", "Maurice", "ACDC_Whole lotta Rosie (05:34)"],
        ["Rolling in the deep", "Adele", "105", "Bob", "Adele_Rolling in the deep (03:48)"],
        ["You oughta know", "Alanis Morissette", "105", "Maurice", "Alanis Morissette_You oughta know (04:10)"],
        ["This is the life", "Amy McDonalds", "95", "Bob", "Amy McDonalds_This is the life (03:54)"],
        ["Valerie", "Amy Winehouse", "106", "Maurice", "Amy Winehouse_Valerie (03:45)"],
        ["Leef (met I will survive)", "Andre Hazes Jr", "121", "Harold", "Andre Hazes Jr_Leef (met I will survive) (03:38)"],
        ["Why tell me why", "Anita Meyer", "113", "Harold", "Anita Meyer_Why tell me why (03:32)"],
        ["Nobody's wife", "Anouk", "98", "Bob", "Anouk_Nobody's wife (03:26)"],
        ["So hard", "Anouk", "129", "Bob", "Anouk_So hard (04:27)"],
        ["RU kidding me", "Anouk", "89", "Bob", "Anouk_RU kidding me (03:20)"],
        ["Good god", "Anouk", "164", "Maurice", "Anouk_Good god (02:36)"],
        ["Think", "Aretha Ericlin", "129", "Harold", "Aretha Ericlin_Think (03:21)"],
        ["Wake me up", "Avici", "124", "Bob", "Avici_Wake me up (03:30)"],
        ["I Gotta Feeling", "Black Eyed Peas", "128", "", "Black Eyed Peas_I Gotta Feeling (04:05)"],
        ["Living on a prayer", "Bon jovi", "123", "Harold", "Bon jovi_Living on a prayer (04:09)"],
        ["Love a bad name", "Bon jovi", "123", "Harold", "Bon jovi_Love a bad name (03:43)"],
        ["Uptown funk", "Bruno Mars", "115", "Harold", "Bruno Mars_Uptown funk (04:30)"],
        ["Ain't nobody", "Chaka Khan", "100", "", "Chaka Khan_Ain't nobody (04:41)"],
        ["Afraid of the dark", "Chef'Special", "167", "", "Chef'Special_Afraid of the dark (03:07)"],
        ["Mag ik dan bij jou", "Claudia de Brey", "133", "Harold", "Claudia de Brey_Mag ik dan bij jou (03:24)"],
        ["Viva la vida", "Coldplay", "138", "Harold", "Coldplay_Viva la vida (04:02)"],
        ["Dansen op de vulkaan", "Dijk de", "150", "Maurice", "Dijk de_Dansen op de vulkaan (04:27)"],
        ["ik kan het niet alleen", "Dijk de", "142", "Maurice", "Dijk de_ik kan het niet alleen (03:59)"],
        ["Als ze er niet is", "Dijk de", "84", "", "Dijk de_Als ze er niet is (03:32)"],
        ["Love me just a little bit more", "Dolly Dots", "96", "Bob", "Dolly Dots_Love me just a little bit more (03:33)"],
        ["Jolene", "Dolly Parton", "110", "Bob", "Dolly Parton_Jolene (02:42)"],
        ["Working 9 to 5", "Dolly Parton", "105", "", "Dolly Parton_Working 9 to 5 (02:42)"],
        ["Bad Girls", "Donna Summer", "121", "", "Donna Summer_Bad Girls (04:55)"],
        ["Long train running", "Dooby Brothers", "117", "Bob", "Dooby Brothers_Long train running (03:05)"],
        ["Listen to the music", "Dooby Brothers", "106", "Bob", "Dooby Brothers_Listen to the music (03:21)"],
        ["September", "Earth Wind & Fire", "126", "", "Onderdeel medley Disco"],
        ["Boogie Wonderland", "Earth Wind & Fire", "132", "", "Onderdeel medley Disco"],
        ["Perfect", "Ed Sheeran", "95", "Bob", "Ed Sheeran_Perfect (04:23)"],
        ["Sisters are doin' it for themselves", "Eurithmics", "136", "Harold", "Eurithmics_Sisters are doin' it for themselves (05:57)"],
        ["The final count down", "Europe", "118", "", "Europe_The final count down (05:09)"],
        ["Jesus he knows me", "Genesis", "95", "Harold", "Genesis_Jesus he knows me (04:17)"],
        ["Una paloma blanca", "George Baker", "133", "Maurice", "George Baker_Una paloma blanca (03:32)"],
        ["Heavy cross", "Gossip", "120", "Bob", "Gossip_Heavy cross (04:03)"],
        ["Brabant", "Guus Meeuwis", "82", "Bob", "Guus Meeuwis_Brabant (03:30)"],
        ["Jump", "Halen van", "130", "Harold", "Halen van_Jump (04:02)"],
        ["Atemlos durch die Nacht", "Helene Fischer", "128", "Bob", "Helene Fischer_Atemlos durch die Nacht (04:30)"],
        ["I love it", "Icona pop", "126", "Harold", "Icona pop_I love it (02:37)"],
        ["The Trooper", "Iron Maiden", "160", "Maurice", "Iron Maiden_The Trooper (04:13)"],
        ["Hold back the river", "James Bay", "135", "Bob", "James Bay_Hold back the river (03:59)"],
        ["I feel good", "James Brown", "143", "Zang", "James Brown_I feel good (02:46)<br>6x - 3x - pauze!"],
        ["Dance Across The Floor", "Jimmy Bo Horne", "112", "", "Jimmy Bo Horne_Dance Across The Floor (02:41)"],
        ["Een geit in het café", "Johnny Purple", "136", "Harold", "Johnny Purple_Een geit in het café (03:58)"],
        ["Son of a preacher man", "Joss Stone", "85", "Maurice", "Joss Stone_Son of a preacher man (02:29)"],
        ["Super duper love", "Joss Stone", "95", "Maurice", "Joss Stone_Super duper love (04:20)"],
        ["Oya Lélé", "K3", "135", "", "K3_Oya Lélé (03:45)"],
        ["Walking on sunshine", "Katrina & The Waves", "110", "Maurice", "Katrina & The Waves_Walking on sunshine (03:59)"],
        ["Underneath the tree", "Kelly Klarkson", "160", "Harold", "Kelly Klarkson_Underneath the tree (03:50)"],
        ["War", "Kensington", "126", "Maurice", "Kensington_War (02:57)"],
        ["I've got the music in me", "Kiki Dee", "119", "Eric", "Kiki Dee_I've got the music in me (05:02)"],
        ["Op een onbewoond eiland", "Kinderen voor kinderen", "72", "", "Kinderen voor kinderen_Op een onbewoond eiland (03:01)"],
        ["Sex on fire", "Kings of Leon", "153", "Bob", "Kings of Leon_Sex on fire (03:23)"],
        ["Always Remember Us This Way", "Lady Gaga", "130", "Harold", "Lady Gaga_Always Remember Us This Way (03:30)"],
        ["Narcotic", "Liquido", "102", "Maurice", "Liquido_Narcotic (03:56)"],
        ["Like a prayer", "Madonna", "111", "Harold", "Madonna_Like a prayer (05:43)"],
        ["Dance with somebody", "Mando Diao", "150", "Maurice", "Mando Diao_Dance with somebody (04:02)"],
        ["I was made for lovin you", "Maria mena", "128", "Bob", "Maria mena_I was made for lovin you (04:31)"],
        ["All I want for Christmas", "Mariah Carey", "150", "Harold", "Mariah Carey_All I want for Christmas (04:01)"],
        ["Shackles", "Mary Mary", "100", "Harold", "Mary Mary_Shackles (03:18)"],
        ["Billy Jean", "Michael Jackson", "120", "Bob", "Michael Jackson_Billie Jean (04:54), Into | C1 | B1 | R1 | C2 | B2 | R2 | OUTRO"],
        ["Beat it", "Micheal Jackson", "139", "", "Micheal Jackson_Beat it (04:18)"],
        ["Lady marmelade", "Patty labelle", "115", "Maurice", "Patty labelle_Lady marmelade (03:41)"],
        ["Raise your glass", "Pink", "122", "Bob", "Pink_Raise your glass (03:00)"],
        ["Jump", "Pointer sisters", "130", "Harold", "Pointer sisters_Jump (04:23)"],
        ["Non non rien change", "Poppy's les", "95", "Bob", "Poppy's les_Non non rien change (03:10)"],
        ["Purple Rain", "Prince", "113", "", "Prince_Purple Rain (08:14)"],
        ["Don't stop me now", "Queen", "156", "Harold", "Queen_Don't stop me now (03:29)"],
        ["Du Hast", "Rammstein", "125", "Harold", "Rammstein_Du Hast (03:54)"],
        ["Angels", "Robbie Williams", "150", "Harold", "Robbie Williams_Angels (04:25)"],
        ["Coming home", "Sheppard", "145", "iedereen", "Sheppard_Coming home (03:38)"],
        ["Don't you (forget about me)", "Simple minds", "111", "", "Simple minds_Don't you (forget about me) (04:23)"],
        ["We are family", "Sister Sledge", "119", "", "Sister Sledge_We are family (03:37)"],
        ["Boys don't cry", "The Cure", "169", "Bob", "The Cure_Boys don't cry (02:38)"],
        ["Iedereen is van de wereld", "The scene", "118", "Bob", "The scene_Iedereen is van de wereld (03:48)"],
        ["Disco inferno", "The Trammps", "131", "Eric", "The Trammps_Disco inferno (04:20)"],
        ["River Deep", "Tina Turner", "83", "Harold", "Tina Turner_River Deep (03:53)"],
        ["Proud mary", "Tina Turner", "171", "Eric", "Tina Turner_Proud mary (05:27)"],
        ["Nutbush City Limits", "Tina Turner", "150", "Bob", "Tina Turner_Nutbush City Limits (03:20)"],
        ["Lola Montez", "Volbeat", "152", "Bob", "Volbeat_Lola Montez (04:28)"],
        ["Shut up and dance", "Walk the Moon", "128", "Bob", "Walk the Moon_Shut up and dance (03:19)"],
        ["Play that funky music", "Wild Cherry", "110", "Eric", "Wild Cherry_Play that funky music (04:55)"],
        ["Met jou kan ik het aan", "Emma en Anouk", "", "Maurice", "Emma en Anouk_Met jou kan ik het aan (00:00)"],
        ["Be more kind", "Frank Turner", "136", "Harold", "Frank Turner_Be more kind (04:07)"],
        ["Black Velvet", "Alannah Myles", "91", "Eric", "Alannah Myles_Black Velvet (04:47)"],
        ["Sexy als ik dans", "Nielson", "94", "Bob", "Nielson_Sexy als ik dans (03:35)"],
        ["Lang zal ze leven", "Lang zal ze leven", "102", "Zang", "Lang zal ze leven (00:30)"],
        ["Seven Nation Army", "The White Stripes", "124", "Eric", "The White Stripes_Seven Nation Army (03:51)"],
        ["Bright Eyes", "Ronde", "124", "Maurice", "Ronde_Bright Eyes (02:51)"],
        ["Noodgeval", "Goldband", "139", "Maurice", "Goldband_Noodgeval (03:34)"],
        ["Music was my first love", "John Miles", "91", "Harold", "John_Miles_Music_was_my_first_love"],
        ["Disco medley", "-", "112", "Maurice", "Let's dance"],
        ["Stop", "Sam Brown", "186", "Harold 6/8", ""],
        ["Tina Turner Medley", "Tina Turner", "100", "Harold", "Tempo onbekend"],
        ["Abba Medley", "Abba", "100", "Zang", "Tempo onbekend"],
        ["Anouk Medley", "Anouk", "98", "Bob", "Nobody's wife"],
        ["Queen Medley", "Queen", "100", "Harold", "Tempo onbekend"],
        ["Bon Jovi Medley", "Bon Jovi", "100", "Harold", "Tempo onbekend"],
        ["ACDC Medley", "ACDC", "100", "Bob", "Tempo onbekend"],
        ["Hardcore Medley", "Hardcore", "100", "Harold", "Tempo onbekend"],
        ["Goud", "Suzan en Freek", "110", "Bob", "Intro | C1 | R1 | C2 | R2 | B | R3 <br>Starten C1 tweede rondje<br>C2 laatste rondje stil en eerste rondje B stil<br>R3 anders spelen."],
        ["Let me entertain you", "Robby Williams", "125", "Harold", "Intro | Zang (hihat) | R1 (drum) | C1 | R2 | C2 | B1 (rustig) | R3 | C3 | I (stil) | R4 | (solo) | outro"],
        ["Engelbewaarder", "Marco Schuitmaker", "130", "Maurice", "Intro | C1 | C2 | R1 | C3 | C4 | R2 | B1 | R3 |"],
        ["Genau dieses gefuhl", "Helene Fischer", "103", "Maurice", "Intro | C1 (stop) | R1 | C2 | R2 | OUTRO"],
        ["You're the one that I want", "Grease", "120", "Maurice", "Intro | C1 | C2 | R1 | C3 | C4 | R2 (2x)"],
        ["Terug in de tijd", "Yves Berendse", "123", "Harold", "Intro | R | C (bas start) | R | B (toms) | R"],
        ["Bloed zweet en tranen", "Andre Hazes", "68", "Maurice", "6/8 => Intro | C1 (rand) | R1 | C2 | R2 | B | R | R (solo)"],
        ["De laatste", "Amer en Snelle", "122", "Harold", "Rekening nog niet betaald een stop"],
        ["Die with a smile", "Lady Gaga", "158", "Bob", "Intro | C1 | R | I | C2 | R | B | Outro"],
        ["Ik wil dat je liegt", "Hannah Mae", "107", "Bob", ""],
        ["The Time of my life", "Bill Medley", "109", "Zang", ""],
        ["Jaren 80 Medley", "?", "", "Harold", ""],
        ["Sinds 1 dag of 2 (32 jaar)", "Doe Maar", "114", "Gitaar", ""],
        ["Your love", "The Outfield", "130", "Bob", ""],
        ["Rain down on me", "Kane", "133", "Gitaar", ""],
        ["Verdammt ich lieb dich", "Matthias Reim", "100", "Gitaar", ""],
        ["Echte Liefde Is Te Koop", "Samuel Welten", "131", "Toetsen / Zang", ""],
    ];

    const seedMany = db.transaction((songs) => {
        for (const s of songs) insert.run(...s);
    });
    seedMany(songs);
    console.log(`Seeded ${songs.length} system songs`);
}

function seedDefaultSetlists(db, adminId) {
    const count = db.prepare('SELECT COUNT(*) as c FROM setlists').get();
    if (count.c > 0) return;

    const { v4: uuidv4 } = require('uuid');

    // DB song IDs = data.js id + 1  (autoincrement starts at 1)
    const set1Songs = [83, 108, 96, 117, 102, 104, 65, 46, 8, 21, 63, 30, 49, 45, 61];
    const set2Songs = [98, 121, 105, 114, 113, 82, 124, 87, 54, 76, 112, 11, 16, 25, 85, 43];

    const insertSetlist = db.prepare(
        'INSERT INTO setlists (user_id, title, public_token) VALUES (?, ?, ?)'
    );
    const insertSong = db.prepare(
        'INSERT INTO setlist_songs (setlist_id, song_id, position) VALUES (?, ?, ?)'
    );

    const createSetlist = db.transaction((userId, title, songIds) => {
        const result = insertSetlist.run(userId, title, uuidv4());
        const setlistId = result.lastInsertRowid;
        songIds.forEach((songId, pos) => {
            const song = db.prepare('SELECT id FROM songs WHERE id = ?').get(songId);
            if (song) insertSong.run(setlistId, songId, pos);
        });
    });

    createSetlist(adminId, 'Set 1', set1Songs);
    createSetlist(adminId, 'Set 2', set2Songs);
    console.log('Seeded default setlists (Set 1, Set 2)');
}

module.exports = { getDb, initDatabase, hasAdmin, createFirstAdmin };
