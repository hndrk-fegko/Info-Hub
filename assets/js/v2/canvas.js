/**
 * V2 Canvas - WYSIWYG Tile Grid mit Server-gerendetem HTML
 * 
 * ARCHITEKTUR-KERNSTÜCK:
 * Statt für jeden Tile-Typ einen eigenen JS-Renderer zu schreiben,
 * nutzen wir die PHP render() Methoden als Single Source of Truth.
 * Dieses Modul platziert das server-gerenderte HTML im Grid und
 * legt Editor-Overlays (Selection, Toolbar-Trigger) darüber.
 * 
 * → Neue Tile-Typen brauchen KEIN zusätzliches JS im Editor!
 */

window.V2Canvas = (function() {
    'use strict';
    
    let _gridEl = null;
    let _initialized = false;
    
    // === Initialization ===
    
    function init() {
        _gridEl = document.getElementById('tileGrid');
        if (!_gridEl) {
            console.error('[Canvas] #tileGrid not found');
            return;
        }
        
        // Render initial tiles from server-provided data
        renderAllTiles();
        
        // Listen to state changes
        V2State.on('state:tiles-changed', onTilesChanged);
        V2State.on('state:selection-changed', onSelectionChanged);
        
        // Click outside tiles → deselect
        document.addEventListener('click', onDocumentClick);
        
        // Keyboard shortcuts
        document.addEventListener('keydown', onKeyDown);
        
        // Reposition toolbar on window resize (handles row-wrap changes)
        window.addEventListener('resize', () => {
            const selectedId = V2State.getSelectedTileId();
            if (selectedId) {
                const toolbar = document.getElementById('tileToolbar');
                const wrapper = _gridEl.querySelector(`.v2-tile-wrapper[data-tile-id="${selectedId}"]`);
                if (toolbar && wrapper && toolbar.style.display !== 'none') {
                    positionToolbar(toolbar, wrapper);
                }
            }
        });
        
        _initialized = true;
        
        if (V2_CONFIG.debugMode) {
            console.log('[Canvas] Initialized');
        }
    }
    
    // === Rendering ===
    
    /**
     * Rendert alle Tiles in den Grid-Container
     * Nutzt das vorgerenderte HTML vom Server (V2State.getRenderedTiles())
     */
    function renderAllTiles() {
        if (!_gridEl) return;
        
        const rendered = V2State.getRenderedTiles();
        
        // Grid leeren
        _gridEl.innerHTML = '';
        
        if (rendered.length === 0) {
            _gridEl.innerHTML = `
                <div class="v2-empty-grid">
                    <p>Noch keine Kacheln vorhanden</p>
                    <button class="v2-add-tile-btn" onclick="V2.addTile()">+ Erste Kachel erstellen</button>
                </div>
            `;
            return;
        }
        
        // Jede Tile ins Grid einfügen, mit Editor-Overlay
        rendered.forEach(tile => {
            const wrapper = createTileWrapper(tile);
            _gridEl.appendChild(wrapper);
        });
        
        // Tile-spezifische JS Init-Funktionen aufrufen (Countdown, Accordion, etc.)
        reinitTileScripts();
    }
    
    /**
     * Erstellt einen Editor-Wrapper um eine server-gerenderte Tile.
     * Der Wrapper enthält:
     * - Das originale HTML (pixelgenau wie auf der echten Seite)
     * - Einen unsichtbaren Overlay für Klick-Selektion
     * - Visuelles Feedback bei Hover/Selection
     */
    function createTileWrapper(tileRender) {
        const wrapper = document.createElement('div');
        wrapper.className = 'v2-tile-wrapper';
        wrapper.dataset.tileId = tileRender.id;
        wrapper.dataset.tileType = tileRender.type;
        
        // Hidden-Tiles visuell markieren aber trotzdem zeigen
        if (tileRender.visible === false) {
            wrapper.classList.add('v2-tile-hidden');
        }
        
        // Das server-gerenderte HTML direkt einfügen
        // WICHTIG: Das HTML enthält schon das <div class="tile tile-xxx size-xxx ..."> Wrapper-Element
        wrapper.innerHTML = `
            ${tileRender.html}
            <div class="v2-tile-overlay" data-tile-id="${tileRender.id}">
                <span class="v2-tile-type-badge">${getTileTypeName(tileRender.type)}</span>
            </div>
        `;
        
        // Click handler auf den Overlay
        const overlay = wrapper.querySelector('.v2-tile-overlay');
        overlay.addEventListener('click', (e) => {
            e.stopPropagation();
            V2State.selectTile(tileRender.id);
        });
        
        // Doppelklick → Edit öffnen
        overlay.addEventListener('dblclick', (e) => {
            e.stopPropagation();
            V2State.selectTile(tileRender.id);
            V2.editSelectedTile();
        });
        
        return wrapper;
    }
    
    /**
     * Gibt den lesbaren Namen eines Tile-Typs zurück
     */
    function getTileTypeName(type) {
        const types = V2State.getTileTypes();
        return (types[type] && types[type].name) || type;
    }
    
    /**
     * Re-initialisiert tile-spezifische JS-Funktionen
     * (z.B. Countdowns, Accordions nach Re-Render)
     */
    function reinitTileScripts() {
        // Countdown Init
        if (typeof initCountdowns === 'function') {
            try { initCountdowns(); } catch(e) { /* ignore */ }
        }
        // Accordion Init  
        if (typeof initAccordions === 'function') {
            try { initAccordions(); } catch(e) { /* ignore */ }
        }
        // Lightbox - re-bind
        if (typeof initLightbox === 'function') {
            try { initLightbox(); } catch(e) { /* ignore */ }
        }
        // Iframe Modal
        if (typeof initIframeModals === 'function') {
            try { initIframeModals(); } catch(e) { /* ignore */ }
        }
        // Text-Kontrast für Akzentfarben (WCAG)
        if (typeof adjustTextContrast === 'function') {
            try { adjustTextContrast(); } catch(e) { /* ignore */ }
        }
    }
    
    // === State Event Handlers ===
    
    function onTilesChanged(data) {
        renderAllTiles();
        // Re-select if previously selected tile still exists
        const selectedId = V2State.getSelectedTileId();
        if (selectedId) {
            highlightTile(selectedId);
        }
    }
    
    function onSelectionChanged(data) {
        // Remove old selection
        document.querySelectorAll('.v2-tile-wrapper.v2-selected').forEach(el => {
            el.classList.remove('v2-selected');
        });
        
        if (data.selectedId) {
            highlightTile(data.selectedId);
            showToolbar(data.selectedId);
        } else {
            hideToolbar();
        }
    }
    
    function highlightTile(id) {
        const wrapper = _gridEl.querySelector(`[data-tile-id="${id}"]`);
        if (wrapper) {
            wrapper.classList.add('v2-selected');
        }
    }
    
    // === Toolbar ===
    
    // Tile-Typen ohne Size/Style/Color Controls
    const _noAppearanceTypes = ['separator'];
    
    function showToolbar(tileId) {
        const toolbar = document.getElementById('tileToolbar');
        const wrapper = _gridEl.querySelector(`.v2-tile-wrapper[data-tile-id="${tileId}"]`);
        if (!toolbar || !wrapper) return;
        
        // Toolbar-Werte aus Tile-Daten setzen
        const tile = V2State.getTileById(tileId);
        if (tile) {
            document.getElementById('tbSize').value = tile.size || 'medium';
            document.getElementById('tbStyle').value = tile.style || 'card';
            document.getElementById('tbColor').value = tile.colorScheme || 'default';
            
            // Appearance-Controls je nach Typ ein-/ausblenden
            const showAppearance = !_noAppearanceTypes.includes(tile.type);
            toolbar.querySelectorAll('[data-tb-group="appearance"]').forEach(el => {
                el.style.display = showAppearance ? '' : 'none';
            });
        }
        
        // Toolbar über der Tile positionieren
        positionToolbar(toolbar, wrapper);
        toolbar.style.display = 'flex';
    }
    
    function positionToolbar(toolbar, wrapper) {
        const rect = wrapper.getBoundingClientRect();
        const toolbarHeight = 40; // approximate
        
        // Über der Tile positionieren
        let top = rect.top - toolbarHeight - 8 + window.scrollY;
        let left = rect.left + window.scrollX;
        
        // Nicht über den oberen Rand hinaus
        if (top < 60) { // 60px = toolbar height
            top = rect.bottom + 8 + window.scrollY;
        }
        
        // Nicht über den rechten Rand hinaus
        const toolbarWidth = toolbar.offsetWidth || 400;
        if (left + toolbarWidth > window.innerWidth) {
            left = window.innerWidth - toolbarWidth - 8;
        }
        
        toolbar.style.top = top + 'px';
        toolbar.style.left = Math.max(8, left) + 'px';
    }
    
    function hideToolbar() {
        const toolbar = document.getElementById('tileToolbar');
        if (toolbar) toolbar.style.display = 'none';
    }
    
    // === Document Events ===
    
    function onDocumentClick(e) {
        // Click outside any tile → deselect (but not when clicking modal/toolbar/popup)
        if (!e.target.closest('.v2-tile-wrapper') && !e.target.closest('.v2-tile-toolbar') && !e.target.closest('.modal') && !e.target.closest('.v2-modal-overlay') && !e.target.closest('.v2-type-popup')) {
            V2State.deselectAll();
        }
    }
    
    function onKeyDown(e) {
        // Don't handle keyboard shortcuts when typing in inputs
        if (e.target.matches('input, textarea, select')) return;
        
        const selectedId = V2State.getSelectedTileId();
        
        switch(e.key) {
            case 'Escape':
                V2State.deselectAll();
                break;
            case 'Delete':
            case 'Backspace':
                if (selectedId) {
                    e.preventDefault();
                    V2.deleteSelectedTile();
                }
                break;
            case 'Enter':
                if (selectedId) {
                    e.preventDefault();
                    V2.editSelectedTile();
                }
                break;
            case 'n':
            case 'N':
                if (!e.ctrlKey && !e.metaKey) {
                    V2.addTile();
                }
                break;
        }
    }
    
    // === Refresh einzelner Tile ===
    
    /**
     * Rendert eine einzelne Tile neu (nach Edit/Save).
     * Holt neue HTML via API und aktualisiert das DOM.
     */
    async function refreshTile(tileId) {
        try {
            // Tile-Daten und HTML vom Server holen
            const [tilesRes, renderRes] = await Promise.all([
                V2Api.getTiles(),
                V2Api.renderAllTiles()
            ]);
            
            if (tilesRes.success && renderRes.success) {
                V2State.setTiles(tilesRes.tiles, renderRes.tiles);
            }
        } catch(err) {
            console.error('[Canvas] refreshTile failed:', err);
            V2.toast('Fehler beim Aktualisieren', 'error');
        }
    }
    
    /**
     * Kompletter Reload aller Tiles vom Server
     */
    async function reloadAll() {
        try {
            const [tilesRes, renderRes] = await Promise.all([
                V2Api.getTiles(),
                V2Api.renderAllTiles()
            ]);
            
            if (tilesRes.success && renderRes.success) {
                V2State.setTiles(tilesRes.tiles, renderRes.tiles);
            }
        } catch(err) {
            console.error('[Canvas] reloadAll failed:', err);
            V2.toast('Fehler beim Laden', 'error');
        }
    }
    
    // === Public API ===
    return {
        init,
        renderAllTiles,
        refreshTile,
        reloadAll,
        reinitTileScripts
    };
})();


// =====================================================
// V2 - Hauptmodul (globale Funktionen für onclick etc.)
// =====================================================

window.V2 = (function() {
    'use strict';
    
    // === Init ===
    
    function init() {
        // State initialisieren mit Server-Daten
        V2State.init({
            tiles: V2_CONFIG.tiles,
            renderedTiles: V2_CONFIG.renderedTiles,
            settings: V2_CONFIG.settings,
            tileTypes: V2_CONFIG.tileTypes
        });
        
        // Canvas initialisieren
        V2Canvas.init();
        
        // Drag & Drop initialisieren
        if (typeof V2DragDrop !== 'undefined') {
            V2DragDrop.init();
        }
        
        // Insert-Buttons initialisieren
        if (typeof V2Insert !== 'undefined') {
            V2Insert.init();
        }
        
        // Edit-Modal initialisieren
        if (typeof V2EditModal !== 'undefined') {
            V2EditModal.init();
        }
        
        // Settings-Modal initialisieren
        if (typeof V2Settings !== 'undefined') {
            V2Settings.init();
        }
        
        // Session-Timer starten
        initSessionTimer();
        
        if (V2_CONFIG.debugMode) {
            console.log('[V2] WYSIWYG Editor ready');
        }
    }
    
    // === Tile Actions ===
    
    function addTile() {
        // Nutze das Insert-System falls verfügbar (zeigt Typ-Popup)
        if (typeof V2Insert !== 'undefined') {
            const addBtn = document.querySelector('.v2-add-tile-btn');
            if (addBtn) {
                const tiles = V2State.getTiles();
                const insertIndex = tiles.length; // am Ende
                V2Insert.showTypePopup(addBtn, insertIndex);
                return;
            }
        }
        
        // Fallback: prompt-basiert
        const types = V2State.getTileTypes();
        const typeNames = Object.entries(types).map(([key, t]) => `${key} (${t.name})`);
        const input = prompt('Tile-Typ wählen:\n\n' + typeNames.join('\n') + '\n\nTyp eingeben:');
        if (!input) return;
        
        const type = input.trim().split(' ')[0];
        if (!types[type]) {
            toast('Unbekannter Typ: ' + type, 'error');
            return;
        }
        
        const tiles = V2State.getTiles();
        const maxPos = tiles.reduce((max, t) => Math.max(max, t.position || 0), 0);
        
        const newTile = {
            type: type,
            position: maxPos + 10,
            size: 'medium',
            style: 'card',
            colorScheme: 'default',
            data: { title: 'Neue ' + types[type].name }
        };
        
        saveTileAndRefresh(newTile);
    }
    
    async function saveTileAndRefresh(tileData, reselect = true) {
        try {
            const result = await V2Api.saveTile(tileData);
            if (result.success) {
                toast('Gespeichert', 'success');
                const tileId = result.tile?.id || tileData.id;
                if (reselect && tileId) {
                    // Deselect first, reload, then re-select after DOM reflow
                    V2State.deselectAll();
                    await V2Canvas.reloadAll();
                    requestAnimationFrame(() => {
                        V2State.selectTile(tileId);
                    });
                } else {
                    await V2Canvas.reloadAll();
                }
            } else {
                toast('Fehler: ' + (result.errors || result.error || 'Unbekannt'), 'error');
            }
        } catch(err) {
            console.error('[V2] saveTile failed:', err);
            toast('Speichern fehlgeschlagen', 'error');
        }
    }
    
    function editSelectedTile() {
        const id = V2State.getSelectedTileId();
        if (!id) return;
        
        const tile = V2State.getTileById(id);
        if (!tile) return;
        
        // Dynamisches Edit-Modal basierend auf fieldMeta des Tile-Typs
        if (typeof V2EditModal !== 'undefined') {
            V2EditModal.open(tile);
        } else {
            // Fallback: prompt-basiert
            const title = prompt('Titel bearbeiten:', tile.data?.title || '');
            if (title === null) return;
            const updatedTile = { ...tile, data: { ...tile.data, title: title } };
            saveTileAndRefresh(updatedTile);
        }
    }
    
    async function deleteSelectedTile() {
        const id = V2State.getSelectedTileId();
        if (!id) return;
        
        const tile = V2State.getTileById(id);
        const name = tile?.data?.title || tile?.type || 'diese Kachel';
        
        if (!confirm(`"${name}" wirklich löschen?`)) return;
        
        try {
            const result = await V2Api.deleteTile(id);
            if (result.success) {
                V2State.deselectAll();
                toast('Kachel gelöscht', 'success');
                await V2Canvas.reloadAll();
            } else {
                toast('Löschen fehlgeschlagen', 'error');
            }
        } catch(err) {
            console.error('[V2] deleteTile failed:', err);
            toast('Löschen fehlgeschlagen', 'error');
        }
    }
    
    // === Quick-Edit Actions (Toolbar) ===
    
    async function changeSize(newSize) {
        const id = V2State.getSelectedTileId();
        if (!id) return;
        
        const tile = V2State.getTileById(id);
        if (!tile) return;
        
        const updatedTile = { ...tile, size: newSize };
        await saveTileAndRefresh(updatedTile);
    }
    
    async function changeStyle(newStyle) {
        const id = V2State.getSelectedTileId();
        if (!id) return;
        
        const tile = V2State.getTileById(id);
        if (!tile) return;
        
        const updatedTile = { ...tile, style: newStyle };
        await saveTileAndRefresh(updatedTile);
    }
    
    async function changeColor(newColor) {
        const id = V2State.getSelectedTileId();
        if (!id) return;
        
        const tile = V2State.getTileById(id);
        if (!tile) return;
        
        const updatedTile = { ...tile, colorScheme: newColor };
        await saveTileAndRefresh(updatedTile);
    }
    
    async function moveUp() {
        const id = V2State.getSelectedTileId();
        if (!id) return;
        await swapPosition(id, -1);
    }
    
    async function moveDown() {
        const id = V2State.getSelectedTileId();
        if (!id) return;
        await swapPosition(id, 1);
    }
    
    async function swapPosition(tileId, direction) {
        const tiles = V2State.getTiles();
        const idx = tiles.findIndex(t => t.id === tileId);
        if (idx === -1) return;
        
        const swapIdx = idx + direction;
        if (swapIdx < 0 || swapIdx >= tiles.length) return;
        
        // Positionen tauschen
        const positions = tiles.map((t, i) => ({
            id: t.id,
            position: (i === idx) ? tiles[swapIdx].position :
                     (i === swapIdx) ? tiles[idx].position :
                     t.position
        }));
        
        try {
            const result = await V2Api.updatePositions(positions);
            if (result.success) {
                await V2Canvas.reloadAll();
                // Force re-select: deselect first so selectTile always fires
                V2State.deselectAll();
                // Use rAF to let DOM settle before repositioning toolbar
                requestAnimationFrame(() => {
                    V2State.selectTile(tileId);
                });
            }
        } catch(err) {
            console.error('[V2] swapPosition failed:', err);
            toast('Position ändern fehlgeschlagen', 'error');
        }
    }
    
    // === Publishing ===
    
    async function publish() {
        if (!confirm('Seite jetzt veröffentlichen?')) return;
        
        try {
            const result = await V2Api.publish();
            if (result.success) {
                toast('Seite veröffentlicht! 🚀', 'success');
                V2State.setDirty(false);
            } else {
                toast('Veröffentlichung fehlgeschlagen: ' + (result.message || ''), 'error');
            }
        } catch(err) {
            console.error('[V2] publish failed:', err);
            toast('Veröffentlichung fehlgeschlagen', 'error');
        }
    }
    
    function openPreview() {
        window.open(V2_CONFIG.apiUrl + '?action=preview', '_blank');
    }
    
    function openSettings() {
        if (typeof V2Settings !== 'undefined') {
            V2Settings.open();
        } else {
            toast('Settings-Modul nicht geladen', 'error');
        }
    }
    
    function logout() {
        window.location.href = '../login.php?action=logout';
    }
    
    // === Toast Notifications ===
    
    function toast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        
        const toast = document.createElement('div');
        toast.className = `v2-toast v2-toast-${type}`;
        toast.textContent = message;
        container.appendChild(toast);
        
        // Animate in
        requestAnimationFrame(() => toast.classList.add('v2-toast-visible'));
        
        // Auto-remove
        setTimeout(() => {
            toast.classList.remove('v2-toast-visible');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    // === Session Timer (Activity-Tracking + Auto-Extend + Restore) ===
    
    let _sessionLastActivity = Date.now();
    let _sessionWarningShown = false;
    let _lastExtendCall = 0;
    
    function initSessionTimer() {
        const timeout = V2_CONFIG.sessionTimeout || 3600;
        const warningBefore = V2_CONFIG.sessionWarning || 300;
        const display = document.getElementById('sessionTimeDisplay');
        const timer = document.getElementById('sessionTimer');
        if (!display) return;
        
        // Restore last activity from sessionStorage (survives reload)
        const stored = sessionStorage.getItem('v2_lastActivity');
        if (stored) {
            const storedTime = parseInt(stored, 10);
            // Only restore if it's recent enough (within timeout)
            if (Date.now() - storedTime < timeout * 1000) {
                _sessionLastActivity = storedTime;
            }
        }
        
        // Activity tracking
        const activityEvents = ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'];
        activityEvents.forEach(evt => {
            document.addEventListener(evt, () => {
                _sessionLastActivity = Date.now();
                sessionStorage.setItem('v2_lastActivity', String(_sessionLastActivity));
                _sessionWarningShown = false;
                extendSessionIfNeeded();
            }, { passive: true });
        });
        
        // Timer update every second
        function update() {
            const inactiveSecs = Math.floor((Date.now() - _sessionLastActivity) / 1000);
            const remaining = Math.max(0, timeout - inactiveSecs);
            
            const mins = Math.floor(remaining / 60);
            const secs = remaining % 60;
            
            if (remaining <= 300 && remaining > 0) {
                display.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
            } else if (remaining > 0) {
                display.textContent = `${mins}min`;
            } else {
                display.textContent = '0:00';
            }
            
            // Warning state
            if (timer) {
                timer.classList.toggle('v2-session-warning', remaining < warningBefore);
            }
            
            // Session expired → redirect
            if (remaining <= 0) {
                sessionStorage.removeItem('v2_lastActivity');
                window.location.href = '../login.php';
                return;
            }
            
            // Show warning dialog when close to expiry
            if (remaining <= warningBefore && !_sessionWarningShown) {
                _sessionWarningShown = true;
                toast('Session läuft bald ab! Klicke irgendwo um sie zu verlängern.', 'warning');
            }
        }
        
        update();
        setInterval(update, 1000);
    }
    
    async function extendSessionIfNeeded() {
        // Max every 5 minutes
        if (Date.now() - _lastExtendCall < 300000) return;
        _lastExtendCall = Date.now();
        
        try {
            await V2Api.extendSession();
        } catch(e) {
            console.warn('[V2] Session extend failed:', e);
        }
    }
    
    // === Public API ===
    return {
        init,
        addTile, editSelectedTile, deleteSelectedTile,
        changeSize, changeStyle, changeColor,
        moveUp, moveDown,
        publish, openPreview, openSettings, logout,
        toast
    };
})();

// === Boot ===
document.addEventListener('DOMContentLoaded', () => {
    V2.init();
});
