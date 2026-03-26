/**
 * V2 State Manager - Zentraler State für den WYSIWYG Editor
 * 
 * Single Source of Truth für:
 * - Tile-Daten (raw JSON)
 * - Gerenderte Tile-HTML (vom Server)
 * - Settings
 * - Editor-State (selected tile, dirty, etc.)
 * 
 * Events:
 * - state:tiles-changed     → Tiles wurden geändert
 * - state:selection-changed  → Andere Tile ausgewählt
 * - state:settings-changed   → Settings geändert
 * - state:dirty-changed      → Dirty-Status geändert
 */

window.V2State = (function() {
    'use strict';
    
    // === Private State ===
    let _tiles = [];           // Raw tile data [{id, type, position, size, style, data, ...}]
    let _renderedTiles = [];   // [{id, type, html, size, style, colorScheme, position, visible}]
    let _settings = {};        // Site settings
    let _tileTypes = {};       // Tile type metadata
    let _selectedTileId = null;
    let _isDirty = false;
    
    // === Event System ===
    const _listeners = {};
    
    function on(event, callback) {
        if (!_listeners[event]) _listeners[event] = [];
        _listeners[event].push(callback);
    }
    
    function off(event, callback) {
        if (!_listeners[event]) return;
        _listeners[event] = _listeners[event].filter(cb => cb !== callback);
    }
    
    function emit(event, data) {
        if (V2_CONFIG.debugMode) {
            console.log(`[State] ${event}`, data);
        }
        if (_listeners[event]) {
            _listeners[event].forEach(cb => {
                try { cb(data); } catch(e) { console.error(`[State] Event handler error:`, e); }
            });
        }
    }
    
    // === Initialization ===
    function init(config) {
        _tiles = config.tiles || [];
        _renderedTiles = config.renderedTiles || [];
        _settings = config.settings || {};
        _tileTypes = config.tileTypes || {};
        _selectedTileId = null;
        _isDirty = false;
        
        if (V2_CONFIG.debugMode) {
            console.log(`[State] Initialized: ${_tiles.length} tiles, ${Object.keys(_tileTypes).length} types`);
        }
    }
    
    // === Tiles ===
    
    function getTiles() {
        return _tiles;
    }
    
    function getRenderedTiles() {
        return _renderedTiles;
    }
    
    function getTileById(id) {
        return _tiles.find(t => t.id === id) || null;
    }
    
    function getRenderedTileById(id) {
        return _renderedTiles.find(t => t.id === id) || null;
    }
    
    /**
     * Aktualisiert Tiles und gerenderten HTML nach Server-Response
     */
    function setTiles(rawTiles, renderedTiles) {
        _tiles = rawTiles;
        if (renderedTiles) {
            _renderedTiles = renderedTiles;
        }
        emit('state:tiles-changed', { tiles: _tiles, rendered: _renderedTiles });
    }
    
    /**
     * Update rendered HTML für eine einzelne Tile (nach Edit)
     */
    function updateRenderedTile(id, newHtml, meta) {
        const idx = _renderedTiles.findIndex(t => t.id === id);
        if (idx !== -1) {
            _renderedTiles[idx].html = newHtml;
            if (meta) {
                Object.assign(_renderedTiles[idx], meta);
            }
        } else {
            // Neue Tile hinzufügen
            _renderedTiles.push({
                id: id,
                html: newHtml,
                ...meta
            });
        }
        
        // Auch raw tile data updaten falls mitgeliefert
        if (meta && meta._rawTile) {
            const tileIdx = _tiles.findIndex(t => t.id === id);
            if (tileIdx !== -1) {
                _tiles[tileIdx] = meta._rawTile;
            } else {
                _tiles.push(meta._rawTile);
            }
            // Nach Position sortieren
            _tiles.sort((a, b) => (a.position || 0) - (b.position || 0));
        }
        
        setDirty(true);
        emit('state:tiles-changed', { tiles: _tiles, rendered: _renderedTiles });
    }
    
    /**
     * Entfernt eine Tile
     */
    function removeTile(id) {
        _tiles = _tiles.filter(t => t.id !== id);
        _renderedTiles = _renderedTiles.filter(t => t.id !== id);
        
        if (_selectedTileId === id) {
            _selectedTileId = null;
            emit('state:selection-changed', { selectedId: null });
        }
        
        setDirty(true);
        emit('state:tiles-changed', { tiles: _tiles, rendered: _renderedTiles });
    }
    
    // === Selection ===
    
    function getSelectedTileId() {
        return _selectedTileId;
    }
    
    function selectTile(id) {
        if (_selectedTileId === id) return;
        _selectedTileId = id;
        emit('state:selection-changed', { selectedId: id, tile: getTileById(id) });
    }
    
    function deselectAll() {
        if (_selectedTileId === null) return;
        _selectedTileId = null;
        emit('state:selection-changed', { selectedId: null });
    }
    
    // === Settings ===
    
    function getSettings() {
        return _settings;
    }
    
    function setSettings(newSettings) {
        _settings = newSettings;
        emit('state:settings-changed', { settings: _settings });
    }
    
    // === Tile Types ===
    
    function getTileTypes() {
        return _tileTypes;
    }
    
    // === Dirty State ===
    
    function isDirty() {
        return _isDirty;
    }
    
    function setDirty(dirty) {
        if (_isDirty === dirty) return;
        _isDirty = dirty;
        emit('state:dirty-changed', { dirty: _isDirty });
    }
    
    // === Public API ===
    return {
        init,
        on, off, emit,
        // Tiles
        getTiles, getRenderedTiles,
        getTileById, getRenderedTileById,
        setTiles, updateRenderedTile, removeTile,
        // Selection
        getSelectedTileId, selectTile, deselectAll,
        // Settings
        getSettings, setSettings,
        // Tile Types
        getTileTypes,
        // Dirty
        isDirty, setDirty
    };
})();
