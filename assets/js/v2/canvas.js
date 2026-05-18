import V2State from './state.js';
import V2Api from './api-client.js';
import V2Settings from './settings.js';
import { showToast } from './toast.js';

/**
 * V2 Canvas - WYSIWYG Tile Grid mit Server-gerendetem HTML
 * 
 * ARCHITEKTUR-KERNSTÜCK:
 * Statt für jeden Tile-Typ einen eigenen JS-Renderer zu schreiben,
 * nutzen wir die PHP render() Methoden als Single Source of Truth.
 * Dieses Modul platziert das server-gerenderte HTML in Section-Containern und
 * legt Editor-Overlays (Selection, Toolbar-Trigger) darüber.
 * 
 * → Neue Tile-Typen brauchen KEIN zusätzliches JS im Editor!
 */

const V2_CONFIG = window.V2_CONFIG || {};

let _interactiveModules = {
    dragDrop: null,
    insert: null,
    editModal: null,
    contextMenu: null
};
let _interactiveModulesPromise = null;

function getContextMenuModule() {
    return _interactiveModules.contextMenu;
}

function getInsertModule() {
    return _interactiveModules.insert;
}

function getEditModalModule() {
    return _interactiveModules.editModal;
}

async function loadInteractiveModules() {
    if (_interactiveModulesPromise) {
        return _interactiveModulesPromise;
    }

    _interactiveModulesPromise = Promise.all([
        import('./drag-drop.js'),
        import('./insert.js'),
        import('./edit-modal.js'),
        import('./context-menu.js')
    ]).then(([dragDropModule, insertModule, editModalModule, contextMenuModule]) => {
        _interactiveModules = {
            dragDrop: dragDropModule.default,
            insert: insertModule.default,
            editModal: editModalModule.default,
            contextMenu: contextMenuModule.default
        };

        return _interactiveModules;
    });

    return _interactiveModulesPromise;
}

const V2Canvas = (function() {
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
        
        // Render initial section layout from server-provided data
        renderAllSections();
        
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
     * Rendert alle Sections in den Canvas-Container.
     * Nutzt das vorgerenderte HTML vom Server (V2State.getRenderedSections())
      * PARALLEL RENDER CONTRACT:
      * Erwartet aktuell die Struktur aus GeneratorService::renderCanvasSections()
      * und den Mount-Point aus backend/v2/editor.php (#tileGrid).
      * Änderungen an Section-/Tile-Wrappern müssen in beiden PHP-Stellen mitgepflegt werden.
     */
    function renderAllSections() {
        if (!_gridEl) return;
        
        const renderedSections = V2State.getRenderedSections();
        
        _gridEl.innerHTML = '';
        
        if (renderedSections.length === 0) {
            _gridEl.innerHTML = `
                <div class="v2-empty-grid">
                    <p>Noch keine Kacheln vorhanden</p>
                    <button type="button" class="v2-add-tile-btn" data-v2-action="addTile">+ Erste Kachel erstellen</button>
                </div>
            `;
            return;
        }
        
        renderedSections.forEach(section => {
            const sectionEl = createSectionElement(section);
            if (sectionEl) {
                _gridEl.appendChild(sectionEl);
            }
        });
        
        reinitTileScripts();
    }
    
    /**
     * Baut eine Canvas-Section aus dem server-gerenderten HTML.
     */
    function createSectionElement(sectionRender) {
        const fragment = document.createElement('div');
        fragment.innerHTML = (sectionRender.html || '').trim();

        const sectionEl = fragment.firstElementChild;
        if (!sectionEl) {
            return null;
        }

        const sectionGrid = sectionEl.querySelector('.tile-grid');
        if (!sectionGrid) {
            return sectionEl;
        }

        if (sectionRender.markerTileId) {
            sectionGrid.prepend(createSectionMarkerWrapper(sectionRender));
        }

        Array.from(sectionGrid.children)
            .filter(child => child.classList.contains('tile'))
            .forEach(tileEl => {
                const wrapper = createTileWrapperFromElement(tileEl);
                sectionGrid.replaceChild(wrapper, tileEl);
                wrapper.prepend(tileEl);
            });

        return sectionEl;
    }

    /**
     * Erstellt einen Editor-Wrapper um eine bereits gerenderte Tile.
     */
    function createTileWrapperFromElement(tileEl) {
        const tileId = tileEl.dataset.tileId;
        const rawTile = tileId ? V2State.getTileById(tileId) : null;
        const tileType = rawTile?.type || getTileTypeFromElement(tileEl);
        const wrapper = document.createElement('div');
        wrapper.className = 'v2-tile-wrapper';
        wrapper.dataset.tileId = tileId || '';
        wrapper.dataset.tileType = tileType || '';

        applyVisibilityState(wrapper, rawTile, { visible: rawTile?.visible });

        const overlay = document.createElement('div');
        overlay.className = 'v2-tile-overlay';
        overlay.dataset.tileId = tileId || '';
        overlay.innerHTML = `<span class="v2-tile-type-badge">${escapeHtml(getTileTypeName(tileType))}</span>`;
        attachSelectionHandlers(overlay, tileId, true);
        wrapper.appendChild(overlay);
        
        return wrapper;
    }

    /**
     * Erstellt den editor-spezifischen Marker für einen Abschnitt.
     */
    function createSectionMarkerWrapper(sectionRender) {
        const tileId = sectionRender.markerTileId;
        const rawTile = tileId ? V2State.getTileById(tileId) : null;
        const wrapper = document.createElement('div');
        wrapper.className = 'v2-tile-wrapper v2-section-marker-wrapper';
        wrapper.dataset.tileId = tileId || '';
        wrapper.dataset.tileType = 'section';

        applyVisibilityState(wrapper, rawTile, { visible: sectionRender.visible });

        const marker = document.createElement('div');
        marker.className = 'v2-section-marker';
        marker.innerHTML = `
            <div class="v2-section-marker__content">
                <span class="v2-section-marker__label">Abschnitt</span>
                <strong class="v2-section-marker__title">${escapeHtml(sectionRender.markerTitle || 'Ohne Titel')}</strong>
                <span class="v2-section-marker__chip">${escapeHtml(getSectionBackgroundLabel(sectionRender.backgroundMode))}</span>
                ${sectionRender.overlayEnabled ? '<span class="v2-section-marker__chip">Overlay</span>' : ''}
            </div>
            <button type="button" class="v2-section-marker__edit">Bearbeiten</button>
        `;

        const editButton = marker.querySelector('.v2-section-marker__edit');
        editButton.addEventListener('click', (e) => {
            e.stopPropagation();
            if (tileId) {
                V2State.selectTile(tileId);
                V2.editSelectedTile();
            }
        });

        attachSelectionHandlers(marker, tileId, true);
        wrapper.appendChild(marker);
        return wrapper;
    }

    function applyVisibilityState(wrapper, rawTile, renderMeta) {
        const visStatus = getVisibilityStatus(rawTile, renderMeta || {});

        if (visStatus.effectivelyHidden) {
            wrapper.classList.add('v2-tile-hidden');
        }
        if (visStatus.visualClass) {
            wrapper.classList.add(visStatus.visualClass);
        }
        if (visStatus.badgeLabel) {
            wrapper.dataset.visibilityLabel = visStatus.badgeLabel;
        }
    }

    function attachSelectionHandlers(targetEl, tileId, openEditOnDoubleClick) {
        if (!tileId) {
            return;
        }

        targetEl.addEventListener('click', (e) => {
            e.stopPropagation();
            V2State.selectTile(tileId);
        });

        if (openEditOnDoubleClick) {
            targetEl.addEventListener('dblclick', (e) => {
                e.stopPropagation();
                V2State.selectTile(tileId);
                V2.editSelectedTile();
            });
        }

        targetEl.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            e.stopPropagation();
            V2State.selectTile(tileId);
            const contextMenu = getContextMenuModule();
            if (contextMenu) {
                contextMenu.showTileMenu(tileId, e.clientX, e.clientY);
            }
        });

        let longPressTimer = null;
        targetEl.addEventListener('touchstart', (e) => {
            longPressTimer = setTimeout(() => {
                longPressTimer = null;
                const touch = e.touches[0];
                V2State.selectTile(tileId);
                const contextMenu = getContextMenuModule();
                if (contextMenu) {
                    contextMenu.showTileMenu(tileId, touch.clientX, touch.clientY);
                }
            }, 500);
        }, { passive: true });

        const cancelLongPress = () => {
            if (longPressTimer !== null) {
                clearTimeout(longPressTimer);
                longPressTimer = null;
            }
        };

        targetEl.addEventListener('touchend', cancelLongPress, { passive: true });
        targetEl.addEventListener('touchmove', cancelLongPress, { passive: true });
        targetEl.addEventListener('touchcancel', cancelLongPress, { passive: true });
    }

    function getVisibilityStatus(rawTile, tileRender) {
        if (rawTile) {
            const contextMenu = getContextMenuModule();
            if (contextMenu) {
                return contextMenu.getVisibilityStatus(rawTile);
            }
        }

        if (tileRender.visible === false) {
            return {
                effectivelyHidden: true,
                badgeLabel: 'Nicht im Export',
                visualClass: 'v2-visibility-hidden'
            };
        }

        return {
            effectivelyHidden: false,
            badgeLabel: '',
            visualClass: ''
        };
    }

    function getTileTypeFromElement(tileEl) {
        const tileClass = Array.from(tileEl.classList).find(className => className.startsWith('tile-') && className !== 'tile');
        return tileClass ? tileClass.replace('tile-', '') : '';
    }

    function getSectionBackgroundLabel(mode) {
        switch (mode) {
            case 'accent1': return 'Akzent 1';
            case 'accent2': return 'Akzent 2';
            case 'accent3': return 'Akzent 3';
            case 'image': return 'Bild';
            default: return 'Standard';
        }
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
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
        // Cleanup before re-init to prevent listener/interval leaks
        if (typeof cleanupCountdowns === 'function') {
            try { cleanupCountdowns(); } catch(e) { /* ignore */ }
        }
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
        renderAllSections();
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
    
    const _noLayoutTypes = ['separator', 'section'];
    const _noColorTypes = ['separator'];
    const _tileColorOptions = [
        { value: 'default', label: 'Standard' },
        { value: 'white', label: 'Weiß' },
        { value: 'accent1', label: 'Akzent 1' },
        { value: 'accent2', label: 'Akzent 2' },
        { value: 'accent3', label: 'Akzent 3' }
    ];
    const _sectionColorOptions = [
        { value: 'default', label: 'Standard' },
        { value: 'accent1', label: 'Akzent 1' },
        { value: 'accent2', label: 'Akzent 2' },
        { value: 'accent3', label: 'Akzent 3' },
        { value: 'image', label: 'Bild' }
    ];
    
    function showToolbar(tileId) {
        const toolbar = document.getElementById('tileToolbar');
        const wrapper = _gridEl.querySelector(`.v2-tile-wrapper[data-tile-id="${tileId}"]`);
        if (!toolbar || !wrapper) return;
        
        // Toolbar-Werte aus Tile-Daten setzen
        const tile = V2State.getTileById(tileId);
        if (tile) {
            document.getElementById('tbSize').value = tile.size || 'medium';
            document.getElementById('tbStyle').value = tile.style || 'card';
            syncToolbarColorControl(tile);

            const showLayout = !_noLayoutTypes.includes(tile.type);
            const showColor = !_noColorTypes.includes(tile.type);

            toolbar.querySelectorAll('[data-tb-group="layout"]').forEach(el => {
                el.style.display = showLayout ? '' : 'none';
            });
            toolbar.querySelectorAll('[data-tb-group="color"]').forEach(el => {
                el.style.display = showColor ? '' : 'none';
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

    function syncToolbarColorControl(tile) {
        const colorSelect = document.getElementById('tbColor');
        if (!colorSelect || !tile) return;

        const isSection = tile.type === 'section';
        const options = isSection ? _sectionColorOptions : _tileColorOptions;
        const currentValue = isSection ? (tile.data?.backgroundMode || 'default') : (tile.colorScheme || 'default');

        colorSelect.innerHTML = options.map(option => {
            return `<option value="${option.value}">${option.label}</option>`;
        }).join('');
        colorSelect.title = isSection ? 'Abschnittshintergrund' : 'Farbe';
        colorSelect.value = options.some(option => option.value === currentValue) ? currentValue : 'default';
    }

    function openTileEditorAtField(tile, fieldName, dataOverrides = {}) {
        if (!tile) {
            return;
        }

        const editModal = getEditModalModule();
        if (!editModal) {
            return;
        }

        const modalTile = {
            ...tile,
            data: {
                ...(tile.data || {}),
                ...dataOverrides
            }
        };

        editModal.open(modalTile);

        requestAnimationFrame(() => {
            window.setTimeout(() => {
                const fieldInput = document.getElementById(`field-${fieldName}`);
                const fileInput = document.getElementById(`file-${fieldName}`);
                const wrapper = (fieldInput || fileInput)?.closest('.v2-field');
                const focusTarget = wrapper?.querySelector('.v2-upload-controls button')
                    || wrapper?.querySelector('select, input:not([type="hidden"]), textarea, button')
                    || fieldInput
                    || fileInput;

                if (wrapper && typeof wrapper.scrollIntoView === 'function') {
                    wrapper.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }

                if (focusTarget && typeof focusTarget.focus === 'function') {
                    focusTarget.focus();
                }
            }, 0);
        });
    }
    
    // === Document Events ===
    
    function onDocumentClick(e) {
        // Click outside any tile → deselect (but not when clicking modal/toolbar/popup)
        if (!e.target.closest('.v2-tile-wrapper') && !e.target.closest('.v2-tile-toolbar') && !e.target.closest('.modal') && !e.target.closest('.v2-modal-overlay') && !e.target.closest('.v2-type-popup') && !e.target.closest('.v2-context-menu')) {
            V2State.deselectAll();
        }
    }
    
    function onKeyDown(e) {
        // Don't handle keyboard shortcuts when typing in inputs
        if (e.target.matches('input, textarea, select')) return;
        // Don't handle when a modal or popup is open
        if (document.querySelector('.v2-modal-overlay') || document.querySelector('.v2-type-popup[style*="block"]')) return;
        const contextMenu = getContextMenuModule();
        if (contextMenu && contextMenu.isOpen()) return;
        
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
            case 'd':
            case 'D':
                if ((e.ctrlKey || e.metaKey) && selectedId) {
                    e.preventDefault();
                    V2.duplicateSelectedTile();
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
                V2Api.renderCanvasLayout()
            ]);
            
            if (tilesRes.success && renderRes.success) {
                V2State.setTiles(tilesRes.tiles, renderRes.sections);
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
        if (_gridEl) _gridEl.classList.add('v2-canvas-loading');
        try {
            const [tilesRes, renderRes] = await Promise.all([
                V2Api.getTiles(),
                V2Api.renderCanvasLayout()
            ]);
            
            if (tilesRes.success && renderRes.success) {
                V2State.setTiles(tilesRes.tiles, renderRes.sections);
            } else {
                const errMsg = tilesRes.error || renderRes.error || 'Serverfehler';
                console.error('[Canvas] reloadAll API error:', errMsg);
                V2.toast('Laden fehlgeschlagen: ' + errMsg, 'error');
            }
        } catch(err) {
            console.error('[Canvas] reloadAll failed:', err);
            V2.toast('Fehler beim Laden', 'error');
        } finally {
            if (_gridEl) _gridEl.classList.remove('v2-canvas-loading');
        }
    }
    
    // === Public API ===
    return {
        init,
        renderAllSections,
        refreshTile,
        reloadAll,
        reinitTileScripts,
        syncToolbarColorControl,
        openTileEditorAtField
    };
})();


// =====================================================
// V2 - Hauptmodul (globale Funktionen für onclick etc.)
// =====================================================

const V2 = (function() {
    'use strict';

    let _shellActionsBound = false;

    function closePageMenus() {
        document.querySelectorAll('.v2-page-menu__dropdown[open]').forEach((menu) => {
            menu.removeAttribute('open');
        });
    }

    function initPageMenuInteractions() {
        document.addEventListener('click', (event) => {
            document.querySelectorAll('.v2-page-menu__dropdown[open]').forEach((menu) => {
                if (!menu.contains(event.target)) {
                    menu.removeAttribute('open');
                }
            });
        });
    }

    function bindShellActions() {
        if (_shellActionsBound) {
            return;
        }

        document.addEventListener('click', handleShellActionClick);
        document.addEventListener('change', handleShellActionChange);
        document.addEventListener('keydown', handleShellActionKeydown);
        _shellActionsBound = true;
    }

    function handleShellActionClick(event) {
        const actionTarget = event.target.closest('[data-v2-action]');
        if (!actionTarget || actionTarget.disabled) {
            return;
        }

        const handler = getShellActionHandler(actionTarget.dataset.v2Action || '');
        if (!handler) {
            return;
        }

        event.preventDefault();
        handler();
    }

    function handleShellActionChange(event) {
        const control = event.target.closest('[data-v2-change]');
        if (!control) {
            return;
        }

        switch (control.dataset.v2Change) {
            case 'size':
                changeSize(control.value);
                break;
            case 'style':
                changeStyle(control.value);
                break;
            case 'color':
                changeColor(control.value);
                break;
            default:
                break;
        }
    }

    function handleShellActionKeydown(event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const actionTarget = event.target.closest('[data-v2-action]');
        if (!actionTarget || actionTarget.disabled) {
            return;
        }

        if (actionTarget.matches('button, a, input, select, textarea, summary')) {
            return;
        }

        const handler = getShellActionHandler(actionTarget.dataset.v2Action || '');
        if (!handler) {
            return;
        }

        event.preventDefault();
        handler();
    }

    function getShellActionHandler(actionName) {
        switch (actionName) {
            case 'addTile':
                return addTile;
            case 'editSelectedTile':
                return editSelectedTile;
            case 'openContextMenu':
                return openContextMenu;
            case 'openPreview':
                return openPreview;
            case 'openSettings':
                return openSettings;
            case 'publish':
                return publish;
            case 'quickRestoreLastPublish':
                return quickRestoreLastPublish;
            case 'logout':
                return logout;
            default:
                return null;
        }
    }
    
    // === Init ===
    
    async function init() {
        cleanupLegacyPublishedHeader();
        initPageMenuInteractions();
        bindShellActions();

        // State initialisieren mit Server-Daten
        V2State.init({
            tiles: V2_CONFIG.tiles,
            renderedSections: V2_CONFIG.renderedSections,
            settings: V2_CONFIG.settings,
            tileTypes: V2_CONFIG.tileTypes
        });

        const interactiveModules = await loadInteractiveModules();
        
        // Canvas initialisieren
        V2Canvas.init();
        
        // Drag & Drop initialisieren
        interactiveModules.dragDrop?.init();
        
        // Insert-Buttons initialisieren
        interactiveModules.insert?.init();
        
        // Edit-Modal initialisieren
        interactiveModules.editModal?.init();
        
        // Settings-Modal initialisieren
        V2Settings.init();

        // Rechtsklick-Kontextmenü initialisieren
        interactiveModules.contextMenu?.init();
        
        // Session-Timer starten
        initSessionTimer();
        
        // Unsaved-changes guard
        window.addEventListener('beforeunload', function(e) {
            if (V2State.isDirty()) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        if (V2_CONFIG.debugMode) {
            console.log('[V2] WYSIWYG Editor ready');
        }
    }
    
    // === Tile Actions ===
    
    function addTile() {
        // Nutze das Insert-System falls verfügbar (zeigt Typ-Popup)
        const insertModule = getInsertModule();
        const addBtn = document.querySelector('.v2-add-tile-btn');
        if (insertModule && addBtn) {
            const tiles = V2State.getTiles();
            const insertIndex = tiles.length; // am Ende
            insertModule.showTypePopup(addBtn, insertIndex);
            return;
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
            size: ['separator', 'section'].includes(type) ? 'full' : 'medium',
            style: ['separator', 'section'].includes(type) ? 'flat' : 'card',
            colorScheme: 'default',
            data: { title: 'Neue ' + types[type].name }
        };

        if (type === 'section') {
            newTile.data.backgroundMode = 'default';
            newTile.data.backgroundAttachment = 'content';
            newTile.data.backgroundDisplay = 'cover';
            newTile.data.overlayEnabled = false;
            newTile.data.overlayColorEnabled = true;
            newTile.data.overlayColor = '#000000';
            newTile.data.overlayOpacity = 35;
            newTile.data.overlayBlurEnabled = false;
            newTile.data.overlayBlurStrength = 24;
        }
        
        saveTileAndRefresh(newTile);
    }
    
    async function saveTileAndRefresh(tileData, reselect = true) {
        try {
            const result = await V2Api.saveTile(tileData);
            if (result.success) {
                toast('Gespeichert', 'success');
                V2State.setDirty(true);
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
        const editModal = getEditModalModule();
        if (editModal) {
            editModal.open(tile);
            return;
        }

        // Fallback: prompt-basiert
        const title = prompt('Titel bearbeiten:', tile.data?.title || '');
        if (title === null) return;
        const updatedTile = { ...tile, data: { ...tile.data, title: title } };
        saveTileAndRefresh(updatedTile);
    }
    
    async function duplicateSelectedTile() {
        const id = V2State.getSelectedTileId();
        if (!id) return;
        
        const tile = V2State.getTileById(id);
        if (!tile) return;
        
        // Deep clone without id so API creates a new tile
        const clone = JSON.parse(JSON.stringify(tile));
        delete clone.id;
        
        // Place right after original (position + 5)
        clone.position = (tile.position || 0) + 5;
        
        // Append "(Kopie)" to title if present
        if (clone.data?.title) {
            clone.data.title += ' (Kopie)';
        }
        
        await saveTileAndRefresh(clone, true);
    }
    
    function openContextMenu() {
        const selectedId = V2State.getSelectedTileId();
        if (!selectedId) return;
        const contextMenu = getContextMenuModule();
        if (!contextMenu) return;

        const btn = document.getElementById('tbMoreBtn');
        if (btn) {
            const rect = btn.getBoundingClientRect();
            contextMenu.showTileMenu(selectedId, rect.left, rect.bottom + 4);
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
                V2State.setDirty(true);
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

        if (tile.type === 'section' && newColor === 'image') {
            V2Canvas.syncToolbarColorControl(tile);
            V2Canvas.openTileEditorAtField(tile, 'backgroundImage', { backgroundMode: 'image' });
            return;
        }

        const updatedTile = tile.type === 'section'
            ? {
                ...tile,
                data: {
                    ...(tile.data || {}),
                    backgroundMode: newColor
                }
            }
            : { ...tile, colorScheme: newColor };

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
                V2State.setDirty(true);
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
    
    let _previewWindow = null;
    
    async function publish() {
        if (!confirm('Seite jetzt veröffentlichen?')) return;
        
        const pubBtn = document.querySelector('[data-v2-action="publish"]');
        if (pubBtn) {
            pubBtn.disabled = true;
            pubBtn.textContent = '⏳ Wird veröffentlicht...';
        }
        
        try {
            const result = await V2Api.publish();
            if (result.success) {
                const tileCount = V2State.getTiles().length;
                toast(`Seite veröffentlicht! 🚀 (${tileCount} Kachel${tileCount !== 1 ? 'n' : ''})`, 'success');
                V2State.setDirty(false);

                if (typeof result.backupCount === 'number') {
                    V2_CONFIG.backupCount = result.backupCount;
                    const backupCountNote = document.getElementById('v2BackupCountNote');
                    if (backupCountNote) {
                        backupCountNote.textContent = formatBackupCount(result.backupCount);
                    }
                }

                updateQuickRestoreState(result.quickRestore || {
                    available: true,
                    publishedLabel: new Date().toLocaleString('de-DE', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    }),
                    targetLabel: result.quickRestore?.targetLabel || new Date().toLocaleString('de-DE', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })
                });
                
                cleanupLegacyPublishedHeader();
                if (!updatePublishedGeneratedValue(new Date()) && !document.querySelector('.v2-page-menu')) {
                    window.location.reload();
                    return;
                }
                
                // Refresh preview window if open
                if (_previewWindow && !_previewWindow.closed) {
                    _previewWindow.location.reload();
                }
            } else {
                toast('Veröffentlichung fehlgeschlagen: ' + (result.message || ''), 'error');
            }
        } catch(err) {
            console.error('[V2] publish failed:', err);
            toast('Veröffentlichung fehlgeschlagen', 'error');
        } finally {
            if (pubBtn) {
                pubBtn.disabled = false;
                pubBtn.textContent = '🚀 Veröffentlichen';
            }
        }
    }
    
    function openPreview() {
        _previewWindow = window.open(V2_CONFIG.apiUrl + '?action=preview', 'infohub_preview');
    }
    
    function openSettings() {
        V2Settings.open();
    }

    function formatBackupCount(count) {
        return count === 1 ? '1 Sicherung' : `${count} Sicherungen`;
    }

    function cleanupLegacyPublishedHeader() {
        document.querySelectorAll('.v2-published-link, .v2-last-generated').forEach((element) => {
            element.remove();
        });
    }

    function updatePublishedGeneratedValue(date = new Date()) {
        const label = document.getElementById('v2PublishedGeneratedValue');
        if (!label) {
            return false;
        }

        const dd = String(date.getDate()).padStart(2, '0');
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const yyyy = String(date.getFullYear());
        const hh = String(date.getHours()).padStart(2, '0');
        const mi = String(date.getMinutes()).padStart(2, '0');
        label.textContent = `${dd}.${mm}.${yyyy} ${hh}:${mi}`;
        return true;
    }

    function updateQuickRestoreState(quickRestore) {
        const button = document.getElementById('v2QuickRestoreItem');
        const note = document.getElementById('v2QuickRestoreNote');
        const isAvailable = Boolean(quickRestore && quickRestore.available);

        V2_CONFIG.quickRestore = quickRestore || { available: false, publishedLabel: '', targetLabel: '' };

        if (!button || !note) {
            return;
        }

        if (!isAvailable) {
            button.hidden = true;
            button.disabled = true;
            note.textContent = '';
            return;
        }

        const targetLabel = quickRestore.targetLabel || '';
        note.textContent = targetLabel
            ? `Rollback auf Stand ${targetLabel} · nur für die letzte Veröffentlichung dieser Session`
            : 'Nur die letzte Veröffentlichung dieser Session · danach deaktiviert bis neu veröffentlicht wird';
        button.hidden = false;
        button.disabled = false;
    }

    async function quickRestoreLastPublish() {
        const publishedLabel = V2_CONFIG.quickRestore?.publishedLabel || 'dieser Session';
        const targetLabel = V2_CONFIG.quickRestore?.targetLabel || 'dem vorherigen Stand';
        const confirmed = window.confirm(
            `Die Veröffentlichung vom ${publishedLabel} wird zurückgenommen. Zielstand des Rollbacks: ${targetLabel}. Dabei wird nur die veröffentlichte Website wiederhergestellt; der Editor-Stand bleibt unverändert. Der aktuelle Stand wird vorher automatisch gesichert.`
        );

        if (!confirmed) {
            return;
        }

        try {
            const result = await V2Api.quickRestoreLastPublish();
            const safetyInfo = result.safetyBackupId ? ` Sicherheitskopie: ${result.safetyBackupId}.` : '';
            toast(`Veröffentlichung zurückgenommen.${safetyInfo}`, 'success');
            updateQuickRestoreState({ available: false, publishedLabel: '' });

            if (_previewWindow && !_previewWindow.closed) {
                _previewWindow.location.reload();
            }
        } catch (err) {
            console.error('[V2] quick restore failed:', err);
            toast(err.message || 'Quick-Restore fehlgeschlagen', 'error');
        }
    }
    
    function logout() {
        window.location.href = '../login.php?logout=1';
    }
    
    // === Toast Notifications ===
    
    function toast(message, type = 'info') {
        showToast(message, type);
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
        addTile, editSelectedTile, duplicateSelectedTile, deleteSelectedTile,
        changeSize, changeStyle, changeColor,
        moveUp, moveDown,
        publish, openPreview, openSettings, logout, quickRestoreLastPublish,
        openContextMenu,
        toast
    };
})();

// === Boot ===
let _v2AutoInitStarted = false;

function autoInitV2WhenReady() {
    if (_v2AutoInitStarted) {
        return;
    }

    _v2AutoInitStarted = true;
    void V2.init();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoInitV2WhenReady);
} else {
    autoInitV2WhenReady();
}

export { V2, V2Canvas };
export default V2;
