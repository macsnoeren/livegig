/*
  LiveGig - Server
  Node.js + Express + Socket.io + SQLite
  Author: Maurice Snoeren (drummer of Blast!)
*/

const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const path = require('path');
const cors = require('cors');

const { initDatabase, hasAdmin } = require('./database');
const authRoutes = require('./routes/auth');
const songRoutes = require('./routes/songs');
const setlistRoutes = require('./routes/setlists');
const userRoutes = require('./routes/users');

const PORT = process.env.PORT || 3000;

const app = express();
const server = http.createServer(app);
const io = new Server(server);

// Middleware
app.use(cors());
app.use(express.json());

// Redirect to /setup.html when no admin exists yet (skip API and static assets)
app.use((req, res, next) => {
    if (hasAdmin()) return next();
    if (req.path.startsWith('/api/') || req.path === '/setup.html') return next();
    res.redirect('/setup.html');
});

app.use(express.static(path.join(__dirname, 'public')));

// API routes
app.use('/api/auth', authRoutes);
app.use('/api/songs', songRoutes);
app.use('/api/setlists', setlistRoutes);
app.use('/api/users', userRoutes);

// Public setlist page – catch-all for /s/:token, serve SPA shell
app.get('/s/:token', (req, res) => {
    res.sendFile(path.join(__dirname, 'public', 'setlist-public.html'));
});

// Fallback: serve index.html for unknown routes (SPA support)
app.get('*', (req, res) => {
    res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

// Socket.io – visuals control
io.on('connection', (socket) => {
    console.log('Socket connected:', socket.id);

    // Command from remote control page → broadcast to all visuals displays
    socket.on('command', (data) => {
        console.log('Visuals command:', data);
        io.emit('command', data);
    });

    socket.on('disconnect', () => {
        console.log('Socket disconnected:', socket.id);
    });
});

// Start
initDatabase();
server.listen(PORT, () => {
    console.log(`LiveGig running on http://localhost:${PORT}`);
});
