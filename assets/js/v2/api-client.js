/**
 * V2 API Client - Kommunikation mit dem Backend
 * 
 * Wrapper um fetch() mit CSRF-Token, Error-Handling, Toast-Nachrichten.
 * Alle API-Calls gehen über die bestehenden endpoints.php.
 */

const V2Api = (function() {
    'use strict';

    function getConfig() {
        return globalThis.V2_CONFIG || {};
    }
    
    function getApiUrl() {
        return getConfig().apiUrl;
    }
    
    function getCsrfToken() {
        return getConfig().csrfToken;
    }
    
    /**
     * GET-Request an die API
     */
    async function get(action, params = {}) {
        const url = new URL(getApiUrl(), window.location.href);
        url.searchParams.set('action', action);
        Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
        
        const res = await fetch(url.toString(), {
            credentials: 'same-origin'
        });
        
        if (!res.ok) {
            throw new Error(`API Error ${res.status}: ${res.statusText}`);
        }
        
        // get_canvas_css und get_canvas_js liefern kein JSON
        const contentType = res.headers.get('Content-Type') || '';
        if (contentType.includes('text/css') || contentType.includes('javascript')) {
            return await res.text();
        }
        
        return await res.json();
    }
    
    /**
     * POST-Request an die API (mit CSRF)
     */
    async function post(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('csrf_token', getCsrfToken());
        
        // Alle Daten als FormData-Felder
        Object.entries(data).forEach(([key, value]) => {
            if (value instanceof File) {
                formData.append(key, value);
            } else if (typeof value === 'object') {
                formData.append(key, JSON.stringify(value));
            } else {
                formData.append(key, String(value));
            }
        });
        
        const res = await fetch(getApiUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });
        
        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            throw new Error(body.error || `API Error ${res.status}`);
        }
        
        return await res.json();
    }
    
    // === Tile Operations ===
    
    /**
     * Alle Tiles laden (raw data)
     */
    async function getTiles() {
        return await get('get_tiles');
    }
    
    /**
     * Canvas-Layout als gerenderte Abschnittsfragmente laden.
     */
    async function renderCanvasLayout() {
        return await get('render_canvas_layout');
    }
    
    /**
     * Eine Tile als HTML rendern (Preview während Editing)
     */
    async function renderTile(tileData) {
        const res = await fetch(getApiUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({
                action: 'render_tile_html',
                tile: tileData
            })
        });
        
        // Fallback: render_tile_html ist POST mit JSON body,
        // aber endpoints.php liest action aus POST oder GET
        if (!res.ok) {
            throw new Error(`Render failed: ${res.status}`);
        }
        
        return await res.json();
    }
    
    /**
     * Tile speichern (neu oder update)
     */
    async function saveTile(tileData) {
        return await post('save_tile', { tile: tileData });
    }
    
    /**
     * Tile löschen
     */
    async function deleteTile(id) {
        return await post('delete_tile', { id: id });
    }
    
    /**
     * Positionen aktualisieren (nach Drag & Drop oder Move Up/Down)
     */
    async function updatePositions(positions) {
        return await post('update_positions', { positions: positions });
    }
    
    // === Settings ===
    
    async function getSettings() {
        return await get('get_settings');
    }
    
    async function saveSettings(settingsData) {
        return await post('save_settings', { settings: settingsData });
    }
    
    // === Uploads ===
    
    async function uploadImage(file) {
        const formData = new FormData();
        formData.append('action', 'upload_image');
        formData.append('csrf_token', getCsrfToken());
        formData.append('file', file);
        
        const res = await fetch(getApiUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });
        
        return await res.json();
    }
    
    async function uploadDownload(file) {
        const formData = new FormData();
        formData.append('action', 'upload_download');
        formData.append('csrf_token', getCsrfToken());
        formData.append('file', file);
        
        const res = await fetch(getApiUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });
        
        return await res.json();
    }
    
    async function uploadHeader(file) {
        const formData = new FormData();
        formData.append('action', 'upload_header');
        formData.append('csrf_token', getCsrfToken());
        formData.append('file', file);
        
        const res = await fetch(getApiUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });
        
        return await res.json();
    }

    async function uploadBackground(file) {
        const formData = new FormData();
        formData.append('action', 'upload_background');
        formData.append('csrf_token', getCsrfToken());
        formData.append('file', file);

        const res = await fetch(getApiUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });

        return await res.json();
    }

    async function listFiles(type) {
        return await get('list_files', { type: type });
    }
    
    // === Generator ===
    
    async function publish() {
        return await post('generate');
    }

    async function quickRestoreLastPublish() {
        return await post('quick_restore_last_publish');
    }
    
    async function preview() {
        // Preview gibt HTML zurück, kein JSON
        const url = new URL(getApiUrl(), window.location.href);
        url.searchParams.set('action', 'preview');
        
        const res = await fetch(url.toString(), {
            credentials: 'same-origin'
        });
        
        return await res.text();
    }
    
    // === Session ===
    
    async function extendSession() {
        return await post('extend_session');
    }
    
    // === Public API ===
    return {
        get, post,
        getTiles, renderCanvasLayout, renderTile,
        saveTile, deleteTile, updatePositions,
        getSettings, saveSettings,
        uploadImage, uploadDownload, uploadHeader, uploadBackground, listFiles,
        publish, preview, quickRestoreLastPublish,
        extendSession
    };
})();

export { V2Api };
export default V2Api;
