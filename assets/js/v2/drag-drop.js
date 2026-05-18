import V2State from './state.js';
import V2Api from './api-client.js';
import V2Insert from './insert.js';
import { V2Canvas } from './canvas.js';
import { showToast } from './toast.js';

/**
 * V2 Drag & Drop - Native HTML5 Drag & Drop für Tile-Sortierung
 * 
 * Nutzt die Insert-Gaps (aus insert.js) als Drop-Zonen.
 * Auto-Scroll am Viewport-Rand während des Drags.
 * Event-Delegation auf Grid-Level verhindert Listener-Leaks.
 */

const V2_CONFIG = window.V2_CONFIG || {};

const V2DragDrop = (function() {
    'use strict';
    
    let _gridEl = null;
    let _draggedEl = null;
    let _draggedId = null;
    let _enabled = true;
    let _boundGrid = false;
    
    // Auto-Scroll
    let _scrollRAF = null;
    const SCROLL_ZONE = 80;    // px vom Rand
    const SCROLL_SPEED = 12;   // px pro Frame
    
    // Aktueller Drop-Gap
    let _currentDropGap = null;
    
    function init() {
        _gridEl = document.getElementById('tileGrid');
        if (!_gridEl) return;
        
        // Event delegation auf Grid-Ebene (NUR EINMAL!)
        if (!_boundGrid) {
            _gridEl.addEventListener('dragstart', onDragStart);
            _gridEl.addEventListener('dragend', onDragEnd);
            _boundGrid = true;
        }
        
        // dragover/drop auf document-Level für Auto-Scroll überall
        document.addEventListener('dragover', onDragOver);
        document.addEventListener('drop', onDrop);
        
        // Draggable-Attribut auf aktuelle Tiles setzen
        applyDraggable();
        
        // Bei Tile-Änderungen erneut draggable setzen
        V2State.on('state:tiles-changed', () => {
            requestAnimationFrame(applyDraggable);
        });
        
        // Resize → Insert-Cache invalidieren
        window.addEventListener('resize', () => {
            V2Insert.invalidateCache();
        });
        
        if (V2_CONFIG.debugMode) {
            console.log('[DragDrop] Initialized (insert-gap drop zones + auto-scroll)');
        }
    }
    
    /**
     * Setzt draggable="true" auf alle Tile-Wrapper
     */
    function applyDraggable() {
        if (!_gridEl) return;
        _gridEl.querySelectorAll('.v2-tile-wrapper').forEach(w => {
            w.setAttribute('draggable', 'true');
        });
    }
    
    /**
     * Findet den nächsten .v2-tile-wrapper Vorfahren eines Elements
     */
    function closestWrapper(el) {
        return el?.closest?.('.v2-tile-wrapper');
    }
    
    // === Drag Events ===
    
    function onDragStart(e) {
        if (!_enabled) return;
        
        const wrapper = closestWrapper(e.target);
        if (!wrapper) return;
        
        // Defensive: clean up stuck state from previous drag (e.g. Alt-Tab)
        if (_draggedEl) {
            _draggedEl.classList.remove('v2-dragging');
            _draggedEl.style.opacity = '';
            stopAutoScroll();
            V2Insert.setDropMode(false);
        }
        
        _draggedEl = wrapper;
        _draggedId = wrapper.dataset.tileId;
        _draggedEl.classList.add('v2-dragging');
        
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', _draggedId);
        
        requestAnimationFrame(() => {
            if (_draggedEl) _draggedEl.style.opacity = '0.3';
        });
        
        // Insert in Drop-Mode setzen (zeigt Gaps als Drop-Zonen)
        V2Insert.hideTypePopup();
        V2Insert.invalidateCache();
        // Kurze Verzögerung damit der Drag-Start-Opacity angewendet ist
        requestAnimationFrame(() => {
            V2Insert.setDropMode(true);
        });
        
        if (V2_CONFIG.debugMode) {
            console.log('[DragDrop] Start:', _draggedId);
        }
    }
    
    function onDragEnd(e) {
        // Auto-Scroll stoppen
        stopAutoScroll();
        
        // Drop-Mode beenden
        V2Insert.setDropMode(false);
        
        if (_draggedEl) {
            _draggedEl.classList.remove('v2-dragging');
            _draggedEl.style.opacity = '';
            _draggedEl = null;
            _draggedId = null;
        }
        
        _currentDropGap = null;
    }
    
    function onDragOver(e) {
        if (!_draggedEl) return;
        
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        
        // Auto-Scroll am Viewport-Rand
        handleAutoScroll(e.clientY);
        
        // Drop-Zone anzeigen: nächsten Gap finden via Insert-Modul
        const result = V2Insert.findNearestGap(e.clientX, e.clientY, 80);
        if (result) {
            _currentDropGap = result.gap;
            V2Insert.showIndicator(result.gap);
        } else {
            _currentDropGap = null;
            V2Insert.hideIndicator();
        }
    }
    
    function onDrop(e) {
        e.preventDefault();
        if (!_draggedEl || !_draggedId) return;
        
        // Stoppe alles
        stopAutoScroll();
        
        if (_currentDropGap) {
            const insertIndex = _currentDropGap.insertIndex;
            reorderToIndex(_draggedId, insertIndex);
        } else {
            // No valid drop zone
            showToast('Kachel hier nicht ablegbar', 'info');
        }
        
        // Drop-Mode beenden
        V2Insert.setDropMode(false);
        
        if (_draggedEl) {
            _draggedEl.classList.remove('v2-dragging');
            _draggedEl.style.opacity = '';
        }
        _draggedEl = null;
        _draggedId = null;
        _currentDropGap = null;
    }
    
    // === Auto-Scroll ===
    
    function handleAutoScroll(clientY) {
        const viewH = window.innerHeight;
        
        let scrollDir = 0;
        if (clientY < SCROLL_ZONE) {
            scrollDir = -1; // nach oben
        } else if (clientY > viewH - SCROLL_ZONE) {
            scrollDir = 1;  // nach unten
        }
        
        if (scrollDir !== 0) {
            startAutoScroll(scrollDir);
        } else {
            stopAutoScroll();
        }
    }
    
    function startAutoScroll(direction) {
        if (_scrollRAF) return; // Läuft bereits
        
        function tick() {
            window.scrollBy(0, direction * SCROLL_SPEED);
            // Gaps aktualisieren (Scroll verändert Positionen)
            V2Insert.invalidateCache();
            _scrollRAF = requestAnimationFrame(tick);
        }
        _scrollRAF = requestAnimationFrame(tick);
    }
    
    function stopAutoScroll() {
        if (_scrollRAF) {
            cancelAnimationFrame(_scrollRAF);
            _scrollRAF = null;
        }
    }
    
    // === Reorder Logic ===
    
    /**
     * Verschiebt eine Tile an eine neue Position (insertIndex aus Gap).
     * insertIndex = Position im sortierten tiles-Array, wo die Tile eingefügt werden soll.
     */
    async function reorderToIndex(tileId, insertIndex) {
        const tiles = [...V2State.getTiles()];
        
        const dragIdx = tiles.findIndex(t => t.id === tileId);
        if (dragIdx === -1) return;
        
        // Skip if dropping at same position (no change needed)
        if (insertIndex === dragIdx || insertIndex === dragIdx + 1) {
            return;
        }
        
        // Tile entfernen
        const [draggedTile] = tiles.splice(dragIdx, 1);
        
        // insertIndex korrigieren: wenn Tile vor insertIndex war, verschiebt sich alles
        const adjustedIdx = insertIndex > dragIdx ? insertIndex - 1 : insertIndex;
        
        // An neuer Position einfügen
        const finalIdx = Math.max(0, Math.min(adjustedIdx, tiles.length));
        tiles.splice(finalIdx, 0, draggedTile);
        
        // Positionen neu vergeben (10er-Schritte)
        const positions = tiles.map((t, i) => ({
            id: t.id,
            position: (i + 1) * 10
        }));
        
        try {
            const result = await V2Api.updatePositions(positions);
            if (result.success) {
                V2State.setDirty(true);
                showToast('Reihenfolge aktualisiert', 'success');
                await V2Canvas.reloadAll();
                V2State.deselectAll();
                requestAnimationFrame(() => {
                    V2State.selectTile(tileId);
                });
            }
        } catch(err) {
            console.error('[DragDrop] reorder failed:', err);
            showToast('Sortierung fehlgeschlagen', 'error');
        }
    }
    
    function setEnabled(enabled) {
        _enabled = enabled;
    }
    
    return {
        init,
        setEnabled
    };
})();

export { V2DragDrop };
export default V2DragDrop;
