const express = require('express');
const { getDb } = require('../database');
const { requireAuth, requireAdmin } = require('../middleware/auth');

const router = express.Router();

// GET /api/songs  – system songs (user_id IS NULL) + own songs
router.get('/', requireAuth, (req, res) => {
    const db = getDb();
    let songs;
    if (req.user.role === 'admin') {
        songs = db.prepare(
            'SELECT * FROM songs ORDER BY artist COLLATE NOCASE, title COLLATE NOCASE'
        ).all();
    } else {
        songs = db.prepare(
            'SELECT * FROM songs WHERE user_id IS NULL OR user_id = ? ORDER BY artist COLLATE NOCASE, title COLLATE NOCASE'
        ).all(req.user.id);
    }
    res.json(songs);
});

// POST /api/songs
router.post('/', requireAuth, (req, res) => {
    const { title, artist, bpm, starts, description } = req.body;
    if (!title) return res.status(400).json({ error: 'Title is required' });

    const db = getDb();
    // Only admin can create system songs (user_id = NULL)
    const userId = (req.user.role === 'admin' && req.body.system) ? null : req.user.id;

    const result = db.prepare(
        'INSERT INTO songs (user_id, title, artist, bpm, starts, description) VALUES (?, ?, ?, ?, ?, ?)'
    ).run(userId, title, artist || '', bpm || '', starts || '', description || '');

    res.status(201).json(db.prepare('SELECT * FROM songs WHERE id = ?').get(result.lastInsertRowid));
});

// PUT /api/songs/:id
router.put('/:id', requireAuth, (req, res) => {
    const db = getDb();
    const song = db.prepare('SELECT * FROM songs WHERE id = ?').get(req.params.id);
    if (!song) return res.status(404).json({ error: 'Song not found' });

    if (req.user.role !== 'admin' && song.user_id !== req.user.id) {
        return res.status(403).json({ error: 'Not allowed' });
    }

    const { title, artist, bpm, starts, description } = req.body;
    db.prepare(
        'UPDATE songs SET title=?, artist=?, bpm=?, starts=?, description=? WHERE id=?'
    ).run(title ?? song.title, artist ?? song.artist, bpm ?? song.bpm,
          starts ?? song.starts, description ?? song.description, song.id);

    res.json(db.prepare('SELECT * FROM songs WHERE id = ?').get(song.id));
});

// DELETE /api/songs/:id
router.delete('/:id', requireAuth, (req, res) => {
    const db = getDb();
    const song = db.prepare('SELECT * FROM songs WHERE id = ?').get(req.params.id);
    if (!song) return res.status(404).json({ error: 'Song not found' });

    if (req.user.role !== 'admin' && song.user_id !== req.user.id) {
        return res.status(403).json({ error: 'Not allowed' });
    }

    db.prepare('DELETE FROM songs WHERE id = ?').run(song.id);
    res.json({ success: true });
});

module.exports = router;
