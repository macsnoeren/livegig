const express = require('express');
const bcrypt = require('bcryptjs');
const { getDb } = require('../database');
const { requireAdmin } = require('../middleware/auth');

const router = express.Router();

// All routes require admin
router.use(requireAdmin);

// GET /api/users
router.get('/', (req, res) => {
    const db = getDb();
    const users = db.prepare(
        'SELECT id, username, role, created_at FROM users ORDER BY username'
    ).all();
    res.json(users);
});

// POST /api/users
router.post('/', (req, res) => {
    const { username, password, role } = req.body;
    if (!username || !password) {
        return res.status(400).json({ error: 'Username and password required' });
    }
    const validRoles = ['admin', 'user'];
    const userRole = validRoles.includes(role) ? role : 'user';

    const db = getDb();
    const existing = db.prepare('SELECT id FROM users WHERE username = ?').get(username);
    if (existing) return res.status(409).json({ error: 'Username already exists' });

    const hash = bcrypt.hashSync(password, 10);
    const result = db.prepare(
        'INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)'
    ).run(username, hash, userRole);

    res.status(201).json({
        id: result.lastInsertRowid,
        username,
        role: userRole
    });
});

// PUT /api/users/:id
router.put('/:id', (req, res) => {
    const db = getDb();
    const user = db.prepare('SELECT * FROM users WHERE id = ?').get(req.params.id);
    if (!user) return res.status(404).json({ error: 'User not found' });

    const { username, password, role } = req.body;
    const newUsername = username ?? user.username;
    const newRole = ['admin', 'user'].includes(role) ? role : user.role;
    const newHash = password ? bcrypt.hashSync(password, 10) : user.password_hash;

    db.prepare(
        'UPDATE users SET username = ?, password_hash = ?, role = ? WHERE id = ?'
    ).run(newUsername, newHash, newRole, user.id);

    res.json({ id: user.id, username: newUsername, role: newRole });
});

// DELETE /api/users/:id
router.delete('/:id', (req, res) => {
    const db = getDb();
    const user = db.prepare('SELECT * FROM users WHERE id = ?').get(req.params.id);
    if (!user) return res.status(404).json({ error: 'User not found' });

    // Prevent deleting last admin
    if (user.role === 'admin') {
        const adminCount = db.prepare("SELECT COUNT(*) as c FROM users WHERE role = 'admin'").get();
        if (adminCount.c <= 1) {
            return res.status(400).json({ error: 'Cannot delete the last admin user' });
        }
    }

    db.prepare('DELETE FROM users WHERE id = ?').run(user.id);
    res.json({ success: true });
});

module.exports = router;
