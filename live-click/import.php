<?php
/**
 * One-time import script: imports all songs from the original data.js
 * into the SQLite database under a default band "Blast!".
 *
 * Run once via CLI: php import.php
 * Or via browser (admin only): /import.php
 */

$cli = (php_sapi_name() === 'cli');

if (!$cli) {
    require_once __DIR__ . '/includes/auth.php';
    requireAdmin();
}

require_once __DIR__ . '/includes/db.php';
$db = getDB();

// Create or fetch the "Blast!" band
$stmt = $db->prepare('SELECT id FROM bands WHERE name=?');
$stmt->execute(['Blast!']);
$band = $stmt->fetch();
if (!$band) {
    $db->prepare('INSERT INTO bands (name, description) VALUES (?,?)')->execute(['Blast!', 'Default band']);
    $bandId = (int)$db->lastInsertId();
} else {
    $bandId = (int)$band['id'];
}

// Raw songs from data.js (stripped down)
$songs = [
    ["Take a chance on me","Abba","107","","04:04","Zang"],
    ["Waterloo","Abba","148","","02:49",""],
    ["Mama mia","Abba","140","","03:45",""],
    ["Highway to hell","ACDC","116","","03:28","Bob"],
    ["Whole lotta Rosie","ACDC","159","","05:34","Maurice"],
    ["Rolling in the deep","Adele","105","","03:48","Bob"],
    ["You oughta know","Alanis Morissette","105","","04:10","Maurice"],
    ["This is the life","Amy McDonalds","95","","03:54","Bob"],
    ["Valerie","Amy Winehouse","106","","03:45","Maurice"],
    ["Leef (met I will survive)","Andre Hazes Jr","121","","03:38","Harold"],
    ["Why tell me why","Anita Meyer","113","","03:32","Harold"],
    ["Nobody's wife","Anouk","98","","03:26","Bob"],
    ["So hard","Anouk","129","","04:27","Bob"],
    ["RU kidding me","Anouk","89","","03:20","Bob"],
    ["Good god","Anouk","164","","02:36","Maurice"],
    ["Think","Aretha Franklin","129","","03:21","Harold"],
    ["Wake me up","Avicii","124","","03:30","Bob"],
    ["I Gotta Feeling","Black Eyed Peas","128","","04:05",""],
    ["Living on a prayer","Bon Jovi","123","","04:09","Harold"],
    ["Love a bad name","Bon Jovi","123","","03:43","Harold"],
    ["Uptown funk","Bruno Mars","115","","04:30","Harold"],
    ["Ain't nobody","Chaka Khan","100","","04:41",""],
    ["Afraid of the dark","Chef'Special","167","","03:07",""],
    ["Mag ik dan bij jou","Claudia de Brey","133","","03:24","Harold"],
    ["Viva la vida","Coldplay","138","","04:02","Harold"],
    ["Dansen op de vulkaan","De Dijk","150","","04:27","Maurice"],
    ["Ik kan het niet alleen","De Dijk","142","","03:59","Maurice"],
    ["Als ze er niet is","De Dijk","84","","03:32",""],
    ["Love me just a little bit more","Dolly Dots","96","","03:33","Bob"],
    ["Jolene","Dolly Parton","110","","02:42","Bob"],
    ["Working 9 to 5","Dolly Parton","105","","02:42",""],
    ["Bad Girls","Donna Summer","121","","04:55",""],
    ["Long train running","Doobie Brothers","117","","03:05","Bob"],
    ["Listen to the music","Doobie Brothers","106","","03:21","Bob"],
    ["Perfect","Ed Sheeran","95","","04:23","Bob"],
    ["Sisters are doin' it for themselves","Eurythmics","136","","05:57","Harold"],
    ["The Final Countdown","Europe","118","","05:09",""],
    ["Jesus he knows me","Genesis","95","","04:17","Harold"],
    ["Una paloma blanca","George Baker","133","","03:32","Maurice"],
    ["Heavy cross","Gossip","120","","04:03","Bob"],
    ["Brabant","Guus Meeuwis","82","","03:30","Bob"],
    ["Jump","Van Halen","130","","04:02","Harold"],
    ["Atemlos durch die Nacht","Helene Fischer","128","","04:30","Bob"],
    ["I love it","Icona Pop","126","","02:37","Harold"],
    ["The Trooper","Iron Maiden","160","","04:13","Maurice"],
    ["Hold back the river","James Bay","135","","03:59","Bob"],
    ["I feel good","James Brown","143","","02:46","Zang"],
    ["Dance Across The Floor","Jimmy Bo Horne","112","","02:41",""],
    ["Son of a preacher man","Joss Stone","85","","02:29","Maurice"],
    ["Super duper love","Joss Stone","95","","04:20","Maurice"],
    ["Walking on sunshine","Katrina & The Waves","110","","03:59","Maurice"],
    ["Underneath the tree","Kelly Clarkson","160","","03:50","Harold"],
    ["War","Kensington","126","","02:57","Maurice"],
    ["I've got the music in me","Kiki Dee","119","","05:02","Eric"],
    ["Op een onbewoond eiland","Kinderen voor Kinderen","72","","03:01",""],
    ["Sex on fire","Kings of Leon","153","","03:23","Bob"],
    ["Always Remember Us This Way","Lady Gaga","130","","03:30","Harold"],
    ["Narcotic","Liquido","102","","03:56","Maurice"],
    ["Like a prayer","Madonna","111","","05:43","Harold"],
    ["Dance with somebody","Mando Diao","150","","04:02","Maurice"],
    ["I was made for lovin you","Maria Mena","128","","04:31","Bob"],
    ["All I want for Christmas","Mariah Carey","150","","04:01","Harold"],
    ["Shackles","Mary Mary","100","","03:18","Harold"],
    ["Billie Jean","Michael Jackson","120","","04:54","Bob"],
    ["Beat it","Michael Jackson","139","","04:18",""],
    ["Lady Marmalade","Patti LaBelle","115","","03:41","Maurice"],
    ["Raise your glass","Pink","122","","03:00","Bob"],
    ["Jump","Pointer Sisters","130","","04:23","Harold"],
    ["Don't stop me now","Queen","156","","03:29","Harold"],
    ["Du Hast","Rammstein","125","","03:54","Harold"],
    ["Angels","Robbie Williams","150","","04:25","Harold"],
    ["Coming home","Sheppard","145","","03:38","Iedereen"],
    ["Don't you (forget about me)","Simple Minds","111","","04:23",""],
    ["We are family","Sister Sledge","119","","03:37",""],
    ["Boys don't cry","The Cure","169","","02:38","Bob"],
    ["Iedereen is van de wereld","The Scene","118","","03:48","Bob"],
    ["Disco inferno","The Trammps","131","","04:20","Eric"],
    ["River Deep","Tina Turner","83","","03:53","Harold"],
    ["Proud mary","Tina Turner","171","","05:27","Eric"],
    ["Nutbush City Limits","Tina Turner","150","","03:20","Bob"],
    ["Lola Montez","Volbeat","152","","04:28","Bob"],
    ["Shut up and dance","Walk the Moon","128","","03:19","Bob"],
    ["Play that funky music","Wild Cherry","110","","04:55","Eric"],
    ["Be more kind","Frank Turner","136","","04:07","Harold"],
    ["Black Velvet","Alannah Myles","91","","04:47","Eric"],
    ["Sexy als ik dans","Nielson","94","","03:35","Bob"],
    ["Lang zal ze leven","Trad.","102","","00:30","Zang"],
    ["Seven Nation Army","The White Stripes","124","","03:51","Eric"],
    ["Bright Eyes","Art Garfunkel","124","","02:51","Maurice"],
    ["Noodgeval","Goldband","139","","03:34","Maurice"],
    ["Music was my first love","John Miles","91","","","Harold"],
    ["Goud","Suzan en Freek","110","Intro | C1 | R1 | C2 | R2 | B | R3","","Bob"],
    ["Let me entertain you","Robbie Williams","125","Intro | Zang | R1 | C1 | R2 | C2 | B1 | R3 | C3 | outro","","Harold"],
    ["Engelbewaarder","Marco Schuitmaker","130","Intro | C1 | C2 | R1 | C3 | C4 | R2 | B1 | R3","","Maurice"],
    ["Genau dieses Gefühl","Helene Fischer","103","Intro | C1 | R1 | C2 | R2 | OUTRO","","Maurice"],
    ["You're the one that I want","Grease","120","Intro | C1 | C2 | R1 | C3 | C4 | R2","","Maurice"],
    ["Terug in de tijd","Yves Berendse","123","Intro | R | C | R | B | R","","Harold"],
    ["Bloed zweet en tranen","André Hazes","68","6/8 - Intro | C1 | R1 | C2 | R2 | B | R","","Maurice"],
    ["De laatste","Amer & Snelle","122","","","Harold"],
    ["Die with a smile","Lady Gaga","158","Intro | C1 | R | I | C2 | R | B | Outro","","Bob"],
    ["Ik wil dat je liegt","Hannah Mae","107","","","Bob"],
    ["The Time of my life","Bill Medley","109","","","Zang"],
    ["Sins 1 dag of 2","Doe Maar","114","","","Gitaar"],
    ["Your love","The Outfield","130","","","Bob"],
    ["Rain down on me","Kane","133","","","Gitaar"],
    ["Verdammt ich lieb dich","Matthias Reim","100","","","Gitaar"],
    ["Echte Liefde Is Te Koop","Samuel Welten","131","","","Toetsen / Zang"],
    ["Abba Medley","Abba","100","","","Zang"],
    ["Anouk Medley","Anouk","98","Nobody's wife","","Bob"],
    ["Queen Medley","Queen","100","","","Harold"],
    ["Bon Jovi Medley","Bon Jovi","100","","","Harold"],
    ["ACDC Medley","ACDC","100","","","Bob"],
    ["Disco Medley","Various","112","Let's dance","","Maurice"],
];

$check = $db->prepare('SELECT id FROM songs WHERE title=? AND band_id=?');
$ins   = $db->prepare('INSERT INTO songs (title,artist,bpm,description,duration,starts,band_id) VALUES (?,?,?,?,?,?,?)');

$imported = 0;
$skipped  = 0;

foreach ($songs as $s) {
    [$title, $artist, $bpm, $desc, $duration, $starts] = $s;
    $check->execute([$title, $bandId]);
    if ($check->fetch()) { $skipped++; continue; }
    $ins->execute([$title, $artist, $bpm ?: null, $desc ?: null, $duration ?: null, $starts ?: null, $bandId]);
    $imported++;
}

// Add admin to Blast! band if not already member
$user = $db->prepare('SELECT id FROM users WHERE role=? LIMIT 1');
$user->execute(['admin']);
$admin = $user->fetch();
if ($admin) {
    $db->prepare('INSERT OR IGNORE INTO band_members (user_id,band_id) VALUES (?,?)')->execute([$admin['id'],$bandId]);
}

$msg = "Import klaar! $imported nummers geïmporteerd, $skipped overgeslagen (bestonden al).";
if ($cli) {
    echo $msg . "\n";
} else {
    echo '<!doctype html><html data-bs-theme="dark"><head><link href="assets/css/app.css" rel="stylesheet"></head><body><div class="container py-5">';
    echo '<div class="alert alert-success">' . htmlspecialchars($msg) . '</div>';
    echo '<a href="dashboard.php" class="btn btn-danger">Ga naar dashboard</a>';
    echo '</div></body></html>';
}
