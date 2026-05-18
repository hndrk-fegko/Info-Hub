/**
 * V2 State Manager - Zentraler State für den WYSIWYG Editor
 * 
 * Single Source of Truth für:
 * - Tile-Daten (raw JSON)
 * - Gerenderte Abschnitts-HTML (vom Server)
 * - Settings
 * - Editor-State (selected tile, dirty, etc.)
 * 
 * Events:
 * - state:tiles-changed     → Tiles wurden geändert
 * - state:selection-changed  → Andere Tile ausgewählt
 * - state:settings-changed   → Settings geändert
 * - state:dirty-changed      → Dirty-Status geändert
 */

const V2State = (function() {
    'use strict';

    function getConfig() {
        return window.V2_CONFIG || {};
    }
    
    // === Private State ===
    let _tiles = [];              // Raw tile data [{id, type, position, size, style, data, ...}]
    let _renderedSections = [];   // [{id, html, markerTileId, tileIds, visible, ...}]
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
        if (getConfig().debugMode) {
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
        _renderedSections = config.renderedSections || [];
        _settings = config.settings || {};
        _tileTypes = config.tileTypes || {};
        _selectedTileId = null;
        _isDirty = false;
        
        if (getConfig().debugMode) {
            console.log(`[State] Initialized: ${_tiles.length} tiles, ${Object.keys(_tileTypes).length} types`);
        }
    }
    
    // === Tiles ===
    
    function getTiles() {
        return _tiles;
    }
    
    function getRenderedSections() {
        return _renderedSections;
    }
    
    function getTileById(id) {
        return _tiles.find(t => t.id === id) || null;
    }
    
    function getRenderedSectionById(id) {
        return _renderedSections.find(section => section.id === id) || null;
    }
    
    /**
     * Aktualisiert Tiles und gerenderten HTML nach Server-Response
     */
    function setTiles(rawTiles, renderedSections) {
        _tiles = rawTiles;
        if (renderedSections) {
            _renderedSections = renderedSections;
        }
        emit('state:tiles-changed', { tiles: _tiles, sections: _renderedSections });
    }
    
    /**
     * Entfernt eine Tile
     */
    function removeTile(id) {
        _tiles = _tiles.filter(t => t.id !== id);
        
        if (_selectedTileId === id) {
            _selectedTileId = null;
            emit('state:selection-changed', { selectedId: null });
        }
        
        setDirty(true);
        emit('state:tiles-changed', { tiles: _tiles, sections: _renderedSections });
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
        getTiles, getRenderedSections,
        getTileById, getRenderedSectionById,
        setTiles, removeTile,
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

window.V2State = V2State;

export { V2State };
export default V2State;
