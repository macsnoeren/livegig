const express = require('express');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const { getDb, hasAdmin, createFirstAdmin } = require('../database');
const { requireAuth, JWT_SECRET } = require('../middleware/auth');

const router = express.Router();

// POST /api/auth/login
router.post('/login', (req, res) => {
    const { username, password } = req.body;
    if (!username || !password) {
        return res.status(400).json({ error: 'Username and password required' });
    }

    const db = getDb();
    const user = db.prepare('SELECT * FROM users WHERE username = ?').get(username);
    if (!user || !bcrypt.compareSync(password, user.password_hash)) {
        return res.status(401).json({ error: 'Invalid credentials' });
    }

    const token = jwt.sign(
        { id: user.id, username: user.username, role: user.role },
        JWT_SECRET,
        { expiresIn: '30d' }
    );

    res.json({ token, user: { id: user.id, username: user.username, role: user.role } });
});

// GET /api/auth/me
router.get('/me', requireAuth, (req, res) => {
    res.json({ id: req.user.id, username: req.user.username, role: req.user.role });
});

// GET /api/auth/setup-required  – returns whether setup is still needed
router.get('/setup-required', (req, res) => {
    res.json({ required: !hasAdmin() });
});

// POST /api/auth/setup  – create first admin, only works when no admin exists yet
router.post('/setup', (req, res) => {
    if (hasAdmin()) {
        return res.status(403).json({ error: 'Setup already completed' });
    }
    const { username, password } = req.body;
    if (!username || !password) {
        return res.status(400).json({ error: 'Username and password required' });
    }
    if (password.length < 6) {
        return res.status(400).json({ error: 'Password must be at least 6 characters' });
    }

    try {
        const user = createFirstAdmin(username, password);
        const token = jwt.sign(
            { id: user.id, username: user.username, role: user.role },
            JWT_SECRET,
            { expiresIn: '30d' }
        );
        res.status(201).json({ token, user });
    } catch (e) {
        res.status(400).json({ error: e.message });
    }
});

module.exports = router;
