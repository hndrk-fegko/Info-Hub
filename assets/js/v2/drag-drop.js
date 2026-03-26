/**
 * V2 Drag & Drop - Native HTML5 Drag & Drop für Tile-Sortierung
 * 
 * Kein SortableJS nötig - verwendet native DnD API.
 * Sortiert Tiles per Drag & Drop und speichert neue Positionen via API.
 * 
 * FIX: Event-Delegation auf dem Grid statt individuelle Listener pro Tile.
 * So gibt es kein Listener-Leak bei Re-Renders.
 */

window.V2DragDrop = (function() {
    'use strict';
    
    let _gridEl = null;
    let _draggedEl = null;
    let _enabled = true;
    let _boundGrid = false;  // Verhindert doppeltes Binden auf Grid-Level
    
    function init() {
        _gridEl = document.getElementById('tileGrid');
        if (!_gridEl) return;
        
        // Event delegation auf Grid-Ebene (NUR EINMAL!)
        if (!_boundGrid) {
            _gridEl.addEventListener('dragstart', onDragStart);
            _gridEl.addEventListener('dragend', onDragEnd);
            _gridEl.addEventListener('dragover', onDragOver);
            _gridEl.addEventListener('dragenter', onDragEnter);
            _gridEl.addEventListener('dragleave', onDragLeave);
            _gridEl.addEventListener('drop', onDrop);
            _boundGrid = true;
        }
        
        // Draggable-Attribut auf aktuelle Tiles setzen
        applyDraggable();
        
        // Bei Tile-Änderungen erneut draggable setzen
        V2State.on('state:tiles-changed', () => {
            requestAnimationFrame(applyDraggable);
        });
        
        // Resize → Insert-Cache invalidieren
        window.addEventListener('resize', () => {
            if (typeof V2Insert !== 'undefined') V2Insert.invalidateCache();
        });
        
        if (V2_CONFIG.debugMode) {
            console.log('[DragDrop] Initialized (event delegation)');
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
    
    function onDragStart(e) {
        if (!_enabled) return;
        
        const wrapper = closestWrapper(e.target);
        if (!wrapper) return;
        
        _draggedEl = wrapper;
        _draggedEl.classList.add('v2-dragging');
        
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', wrapper.dataset.tileId);
        
        requestAnimationFrame(() => {
            if (_draggedEl) _draggedEl.style.opacity = '0.4';
        });
        
        // Insert-Indikator während Drag verstecken
        if (typeof V2Insert !== 'undefined') V2Insert.hideTypePopup();
        
        if (V2_CONFIG.debugMode) {
            console.log('[DragDrop] Start:', wrapper.dataset.tileId);
        }
    }
    
    function onDragEnd(e) {
        if (!_draggedEl) return;
        
        _draggedEl.classList.remove('v2-dragging');
        _draggedEl.style.opacity = '';
        _draggedEl = null;
        
        // Alle Drop-Highlights entfernen
        _gridEl.querySelectorAll('.v2-drop-before, .v2-drop-after').forEach(el => {
            el.classList.remove('v2-drop-before', 'v2-drop-after');
        });
    }
    
    function onDragOver(e) {
        if (!_draggedEl) return;
        
        const wrapper = closestWrapper(e.target);
        
        // Drop auf dem Grid direkt (nicht auf einem Wrapper) = am Ende einfügen
        if (!wrapper || wrapper === _draggedEl) {
            if (e.target === _gridEl || e.target.classList.contains('v2-empty-grid')) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            }
            return;
        }
        
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        
        // Obere/untere Hälfte bestimmen
        const rect = wrapper.getBoundingClientRect();
        const midY = rect.top + rect.height / 2;
        
        // Vorherige Indikatoren auf anderen Wrappern entfernen
        _gridEl.querySelectorAll('.v2-drop-before, .v2-drop-after').forEach(el => {
            if (el !== wrapper) el.classList.remove('v2-drop-before', 'v2-drop-after');
        });
        
        wrapper.classList.remove('v2-drop-before', 'v2-drop-after');
        if (e.clientY < midY) {
            wrapper.classList.add('v2-drop-before');
        } else {
            wrapper.classList.add('v2-drop-after');
        }
    }
    
    function onDragEnter(e) {
        if (!_draggedEl) return;
        e.preventDefault();
    }
    
    function onDragLeave(e) {
        const wrapper = closestWrapper(e.target);
        if (!wrapper) return;
        // Nur entfernen wenn wirklich das Wrapper-Element verlassen wird
        if (!wrapper.contains(e.relatedTarget)) {
            wrapper.classList.remove('v2-drop-before', 'v2-drop-after');
        }
    }
    
    function onDrop(e) {
        e.preventDefault();
        if (!_draggedEl) return;
        
        const wrapper = closestWrapper(e.target);
        
        // Drop auf Grid = am Ende einfügen
        if (!wrapper || wrapper === _draggedEl) {
            if (e.target === _gridEl || e.target.classList.contains('v2-empty-grid')) {
                moveToEnd(_draggedEl.dataset.tileId);
            }
            return;
        }
        
        const draggedId = _draggedEl.dataset.tileId;
        const targetId = wrapper.dataset.tileId;
        const isBefore = wrapper.classList.contains('v2-drop-before');
        
        wrapper.classList.remove('v2-drop-before', 'v2-drop-after');
        
        reorderTile(draggedId, targetId, isBefore);
    }
    
    /**
     * Berechnet neue Positionen und speichert sie via API
     */
    async function reorderTile(draggedId, targetId, insertBefore) {
        const tiles = [...V2State.getTiles()];
        
        const dragIdx = tiles.findIndex(t => t.id === draggedId);
        const targetIdx = tiles.findIndex(t => t.id === targetId);
        
        if (dragIdx === -1 || targetIdx === -1) return;
        
        const [draggedTile] = tiles.splice(dragIdx, 1);
        
        const newTargetIdx = tiles.findIndex(t => t.id === targetId);
        const insertIdx = insertBefore ? newTargetIdx : newTargetIdx + 1;
        
        tiles.splice(insertIdx, 0, draggedTile);
        
        // Positionen neu vergeben (10er-Schritte)
        const positions = tiles.map((t, i) => ({
            id: t.id,
            position: (i + 1) * 10
        }));
        
        try {
            const result = await V2Api.updatePositions(positions);
            if (result.success) {
                V2.toast('Reihenfolge aktualisiert', 'success');
                await V2Canvas.reloadAll();
                V2State.selectTile(draggedId);
            }
        } catch(err) {
            console.error('[DragDrop] reorder failed:', err);
            V2.toast('Sortierung fehlgeschlagen', 'error');
        }
    }
    
    async function moveToEnd(tileId) {
        const tiles = V2State.getTiles();
        const maxPos = tiles.reduce((max, t) => Math.max(max, t.position || 0), 0);
        
        const positions = tiles.map(t => ({
            id: t.id,
            position: t.id === tileId ? maxPos + 10 : t.position
        }));
        
        try {
            const result = await V2Api.updatePositions(positions);
            if (result.success) {
                V2.toast('Reihenfolge aktualisiert', 'success');
                await V2Canvas.reloadAll();
            }
        } catch(err) {
            console.error('[DragDrop] moveToEnd failed:', err);
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
