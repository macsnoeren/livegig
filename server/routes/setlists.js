const express = require('express');
const { v4: uuidv4 } = require('uuid');
const { getDb } = require('../database');
const { requireAuth } = require('../middleware/auth');

const router = express.Router();

function getSetlistWithSongs(db, setlistId) {
    const setlist = db.prepare('SELECT * FROM setlists WHERE id = ?').get(setlistId);
    if (!setlist) return null;
    setlist.songs = db.prepare(`
        SELECT s.*, ss.position FROM songs s
        JOIN setlist_songs ss ON ss.song_id = s.id
        WHERE ss.setlist_id = ?
        ORDER BY ss.position
    `).all(setlistId);
    return setlist;
}

// GET /api/setlists  – own setlists (admin sees all)
router.get('/', requireAuth, (req, res) => {
    const db = getDb();
    const rows = req.user.role === 'admin'
        ? db.prepare('SELECT * FROM setlists ORDER BY created_at DESC').all()
        : db.prepare('SELECT * FROM setlists WHERE user_id = ? ORDER BY created_at DESC').all(req.user.id);

    const setlists = rows.map(sl => getSetlistWithSongs(db, sl.id));
    res.json(setlists);
});

// GET /api/setlists/public/:token  – no auth, public view
router.get('/public/:token', (req, res) => {
    const db = getDb();
    const setlist = db.prepare('SELECT * FROM setlists WHERE public_token = ?').get(req.params.token);
    if (!setlist) return res.status(404).json({ error: 'Setlist not found' });
    res.json(getSetlistWithSongs(db, setlist.id));
});

// POST /api/setlists
router.post('/', requireAuth, (req, res) => {
    const { title, songIds } = req.body;
    if (!title) return res.status(400).json({ error: 'Title is required' });

    const db = getDb();
    const result = db.prepare(
        'INSERT INTO setlists (user_id, title, public_token) VALUES (?, ?, ?)'
    ).run(req.user.id, title, uuidv4());
    const setlistId = result.lastInsertRowid;

    if (Array.isArray(songIds)) {
        const insert = db.prepare('INSERT INTO setlist_songs (setlist_id, song_id, position) VALUES (?, ?, ?)');
        const addSongs = db.transaction((ids) => ids.forEach((id, pos) => insert.run(setlistId, id, pos)));
        addSongs(songIds);
    }

    res.status(201).json(getSetlistWithSongs(db, setlistId));
});

// PUT /api/setlists/:id  – update title and/or replace song list
router.put('/:id', requireAuth, (req, res) => {
    const db = getDb();
    const setlist = db.prepare('SELECT * FROM setlists WHERE id = ?').get(req.params.id);
    if (!setlist) return res.status(404).json({ error: 'Setlist not found' });
    if (req.user.role !== 'admin' && setlist.user_id !== req.user.id) {
        return res.status(403).json({ error: 'Not allowed' });
    }

    const { title, songIds } = req.body;
    if (title) db.prepare('UPDATE setlists SET title = ? WHERE id = ?').run(title, setlist.id);

    if (Array.isArray(songIds)) {
        db.prepare('DELETE FROM setlist_songs WHERE setlist_id = ?').run(setlist.id);
        const insert = db.prepare('INSERT INTO setlist_songs (setlist_id, song_id, position) VALUES (?, ?, ?)');
        const addSongs = db.transaction((ids) => ids.forEach((id, pos) => insert.run(setlist.id, id, pos)));
        addSongs(songIds);
    }

    res.json(getSetlistWithSongs(db, setlist.id));
});

// DELETE /api/setlists/:id
router.delete('/:id', requireAuth, (req, res) => {
    const db = getDb();
    const setlist = db.prepare('SELECT * FROM setlists WHERE id = ?').get(req.params.id);
    if (!setlist) return res.status(404).json({ error: 'Setlist not found' });
    if (req.user.role !== 'admin' && setlist.user_id !== req.user.id) {
        return res.status(403).json({ error: 'Not allowed' });
    }

    db.prepare('DELETE FROM setlists WHERE id = ?').run(setlist.id);
    res.json({ success: true });
});

// POST /api/setlists/:id/regenerate-token  – get a fresh share URL
router.post('/:id/regenerate-token', requireAuth, (req, res) => {
    const db = getDb();
    const setlist = db.prepare('SELECT * FROM setlists WHERE id = ?').get(req.params.id);
    if (!setlist) return res.status(404).json({ error: 'Setlist not found' });
    if (req.user.role !== 'admin' && setlist.user_id !== req.user.id) {
        return res.status(403).json({ error: 'Not allowed' });
    }

    const token = uuidv4();
    db.prepare('UPDATE setlists SET public_token = ? WHERE id = ?').run(token, setlist.id);
    res.json({ public_token: token });
});

module.exports = router;
