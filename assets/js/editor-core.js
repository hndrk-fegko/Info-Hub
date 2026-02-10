/**
 * Editor Core - Shared Foundation
 * 
 * Stellt bereit: API-Helfer, globaler Zustand, Toast-Benachrichtigungen, Utilities.
 * Keine Abhängigkeiten zu anderen Editor-Modulen.
 * 
 * Wird als ERSTES geladen.
 */

// ===== Configuration =====
const API_URL = window.CONFIG?.apiUrl || 'api/endpoints.php';
const CSRF_TOKEN = window.CONFIG?.csrfToken || '';

// ===== API Helpers =====
async function apiPost(action, data = {}) {
    const body = new URLSearchParams();
    body.append('action', action);
    body.append('csrf_token', CSRF_TOKEN);
    
    for (const [key, value] of Object.entries(data)) {
        body.append(key, typeof value === 'object' ? JSON.stringify(value) : value);
    }
    
    const response = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    });
    
    return response.json();
}

async function apiPostFormData(formData) {
    formData.append('csrf_token', CSRF_TOKEN);
    const response = await fetch(API_URL, { method: 'POST', body: formData });
    return response.json();
}

// ===== Shared State =====
let tiles = window.CONFIG?.tiles || [];
let tileTypes = window.CONFIG?.tileTypes || {};
let settings = window.CONFIG?.settings || {};
let currentEditTile = null;
let fileBrowserCallback = null;
let currentFileType = 'images';
let previewWindow = null;

// ===== Toast Notifications =====
function showToast(type, message, duration = 4000) {
    const container = document.getElementById('toastContainer');
    
    const icons = {
        success: '✅',
        error: '❌',
        info: 'ℹ️',
        warning: '⚠️'
    };
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${icons[type] || 'ℹ️'}</span>
        <span class="toast-message">${escapeHtml(message)}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100px)';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// ===== Utilities =====
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
