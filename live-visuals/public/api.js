/*
  LiveGig - API client
  Handles all communication with the backend REST API.
*/

const API_BASE = '/api';

async function apiRequest(method, path, body = null) {
    const token = localStorage.getItem('lg_token');
    const headers = { 'Content-Type': 'application/json' };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const options = { method, headers };
    if (body) options.body = JSON.stringify(body);

    let res;
    try {
        res = await fetch(API_BASE + path, options);
    } catch (e) {
        throw new Error('Network error: ' + e.message);
    }

    if (res.status === 401) {
        localStorage.removeItem('lg_token');
        localStorage.removeItem('lg_user');
        window.location.href = '/login.html';
        return;
    }

    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Request failed');
    return data;
}

const api = {
    // Auth
    login: (username, password) => apiRequest('POST', '/auth/login', { username, password }),
    me: () => apiRequest('GET', '/auth/me'),

    // Songs
    getSongs: () => apiRequest('GET', '/songs'),
    createSong: (song) => apiRequest('POST', '/songs', song),
    updateSong: (id, song) => apiRequest('PUT', `/songs/${id}`, song),
    deleteSong: (id) => apiRequest('DELETE', `/songs/${id}`),

    // Setlists
    getSetlists: () => apiRequest('GET', '/setlists'),
    createSetlist: (data) => apiRequest('POST', '/setlists', data),
    updateSetlist: (id, data) => apiRequest('PUT', `/setlists/${id}`, data),
    deleteSetlist: (id) => apiRequest('DELETE', `/setlists/${id}`),
    getPublicSetlist: (token) => fetch(`/api/setlists/public/${token}`).then(r => r.json()),
    regenerateToken: (id) => apiRequest('POST', `/setlists/${id}/regenerate-token`),

    // Users (admin)
    getUsers: () => apiRequest('GET', '/users'),
    createUser: (data) => apiRequest('POST', '/users', data),
    updateUser: (id, data) => apiRequest('PUT', `/users/${id}`, data),
    deleteUser: (id) => apiRequest('DELETE', `/users/${id}`),
};

// Auth helpers
function getStoredUser() {
    try { return JSON.parse(localStorage.getItem('lg_user')); } catch { return null; }
}

function storeAuth(token, user) {
    localStorage.setItem('lg_token', token);
    localStorage.setItem('lg_user', JSON.stringify(user));
}

function logout() {
    localStorage.removeItem('lg_token');
    localStorage.removeItem('lg_user');
    window.location.href = '/login.html';
}

// Redirect to login if not authenticated
function requireLogin() {
    if (!localStorage.getItem('lg_token')) {
        window.location.href = '/login.html';
        return false;
    }
    return true;
}

// Redirect to login if not admin
function requireAdminRole() {
    const user = getStoredUser();
    if (!user || user.role !== 'admin') {
        window.location.href = '/index.html';
        return false;
    }
    return true;
}
