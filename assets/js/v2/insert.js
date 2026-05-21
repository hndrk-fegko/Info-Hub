import V2State from './state.js';
import V2Api from './api-client.js';
import V2EditModal from './edit-modal.js';
import { V2Canvas } from './canvas.js';
import { showToast } from './toast.js';

/**
 * V2 Insert - Floating "+" Indikator zwischen Grid-Zellen
 * 
 * ARCHITEKTUR: Ein einziger schwebender Indikator (NICHT im Grid-DOM!),
 * der sich per Maus-Tracking zwischen Grid-Zeilen UND -Spalten positioniert.
 * Klick öffnet das Tile-Typ-Popup.
 * 
 * Erkennt sowohl horizontale Gaps (zwischen Zeilen) als auch
 * vertikale Gaps (zwischen Tiles in derselben Zeile).
 */

const V2_CONFIG = window.V2_CONFIG || {};

const V2Insert = (function() {
    'use strict';
    
    let _gridEl = null;
    let _indicator = null;
    let _popup = null;
    let _currentInsertIndex = -1;
    let _gaps = [];              // Cache: horizontale + vertikale Gaps
    let _hideTimeout = null;
    let _currentOrientation = 'horizontal';
    
    function init() {
        _gridEl = document.getElementById('tileGrid');
        if (!_gridEl) return;
        
        createIndicator();
        createTypePopup();
        
        // Maus-Tracking über dem Grid
        _gridEl.addEventListener('mousemove', onGridMouseMove);
        _gridEl.addEventListener('mouseleave', () => scheduleHide(200));
        
        // Gaps bei Tile-Änderung neu berechnen
        V2State.on('state:tiles-changed', () => {
            _gaps = [];  // Cache invalidieren
            hideIndicator();
        });
        
        if (V2_CONFIG.debugMode) {
            console.log('[Insert] Floating indicator initialized (h+v)');
        }
    }
    
    /**
     * Erstellt den schwebenden Insert-Indikator.
     * Wird in .v2-canvas platziert (position: relative), NICHT im Grid.
     */
    function createIndicator() {
        _indicator = document.createElement('div');
        _indicator.className = 'v2-insert-indicator';
        _indicator.innerHTML = `
            <div class="v2-insert-line"></div>
            <button class="v2-insert-trigger" title="Kachel einfügen">+</button>
            <div class="v2-insert-line"></div>
        `;
        _indicator.style.display = 'none';
        
        // In .v2-canvas platzieren (relativ positioniert)
        const canvas = _gridEl.closest('.v2-canvas');
        if (canvas) {
            canvas.style.position = 'relative';
            canvas.appendChild(_indicator);
        } else {
            _gridEl.parentElement.appendChild(_indicator);
        }
        
        // Klick auf den + Button
        _indicator.querySelector('.v2-insert-trigger').addEventListener('click', (e) => {
            e.stopPropagation();
            showTypePopup(e.target, _currentInsertIndex);
        });
        
        // Hover auf dem Indikator selbst soll ihn sichtbar halten
        _indicator.addEventListener('mouseenter', () => cancelHide());
        _indicator.addEventListener('mouseleave', () => scheduleHide(300));
    }
    
    /**
     * Erkennt alle Gaps im Grid: horizontale (zwischen Zeilen) + vertikale (innerhalb einer Zeile).
     * Jeder Gap hat: { orientation, x, y, width, height, insertIndex }
     *   - orientation: 'horizontal' | 'vertical'
     *   - x, y: Position relativ zum Grid (CSS-Offset)
     *   - width/height: Ausdehnung der Linie
     *   - insertIndex: Position im Tiles-Array für Einfügung
     */
    function computeGaps() {
        const wrappers = _gridEl.querySelectorAll('.v2-tile-wrapper');
        if (wrappers.length === 0) return [];
        
        const gridRect = _gridEl.getBoundingClientRect();
        
        // Tile-Positionen sammeln (Index = DOM-Reihenfolge = sortierte Tile-Position)
        const items = Array.from(wrappers).map((w, i) => {
            const rect = w.getBoundingClientRect();
            return {
                index: i,
                left: rect.left,
                right: rect.right,
                top: rect.top,
                bottom: rect.bottom,
                cx: rect.left + rect.width / 2,
                cy: rect.top + rect.height / 2
            };
        });
        
        // In Zeilen gruppieren (gleicher Top ± 5px Toleranz)
        const rows = [];
        let currentRow = null;
        
        // Sort by top, then left
        const sorted = [...items].sort((a, b) => a.top - b.top || a.left - b.left);
        
        sorted.forEach(item => {
            if (!currentRow || item.top > currentRow.maxBottom - 5) {
                currentRow = {
                    items: [item],
                    minTop: item.top,
                    maxBottom: item.bottom
                };
                rows.push(currentRow);
            } else {
                currentRow.items.push(item);
                currentRow.maxBottom = Math.max(currentRow.maxBottom, item.bottom);
            }
        });
        
        // Sort items within each row by left position
        rows.forEach(row => {
            row.items.sort((a, b) => a.left - b.left);
        });
        
        const gaps = [];
        const gridLeft = gridRect.left;
        const gridTop = gridRect.top;
        const gridWidth = gridRect.width;
        
        // === HORIZONTALE GAPS (zwischen Zeilen) ===
        
        // Vor der ersten Zeile
        if (rows.length > 0) {
            const firstRow = rows[0];
            const firstIdx = Math.min(...firstRow.items.map(it => it.index));
            gaps.push({
                orientation: 'horizontal',
                x: 0,
                y: firstRow.minTop - gridTop - 6,
                width: gridWidth,
                height: 0,
                insertIndex: firstIdx,
                _midX: gridLeft + gridWidth / 2,
                _midY: firstRow.minTop - 6
            });
        }
        
        // Zwischen den Zeilen
        for (let i = 0; i < rows.length - 1; i++) {
            const rowA = rows[i];
            const rowB = rows[i + 1];
            const gapMidY = (rowA.maxBottom + rowB.minTop) / 2;
            // insertIndex = kleinster Index der nächsten Zeile
            const insertIdx = Math.min(...rowB.items.map(it => it.index));
            gaps.push({
                orientation: 'horizontal',
                x: 0,
                y: gapMidY - gridTop,
                width: gridWidth,
                height: 0,
                insertIndex: insertIdx,
                _midX: gridLeft + gridWidth / 2,
                _midY: gapMidY
            });
        }
        
        // Nach der letzten Zeile
        if (rows.length > 0) {
            const lastRow = rows[rows.length - 1];
            const lastIdx = Math.max(...lastRow.items.map(it => it.index));
            gaps.push({
                orientation: 'horizontal',
                x: 0,
                y: lastRow.maxBottom - gridTop + 6,
                width: gridWidth,
                height: 0,
                insertIndex: lastIdx + 1,
                _midX: gridLeft + gridWidth / 2,
                _midY: lastRow.maxBottom + 6
            });
        }
        
        // === VERTIKALE GAPS (innerhalb einer Zeile, zwischen Spalten) ===
        
        rows.forEach(row => {
            if (row.items.length < 2) return;
            
            for (let j = 0; j < row.items.length - 1; j++) {
                const left = row.items[j];
                const right = row.items[j + 1];
                
                const gapMidX = (left.right + right.left) / 2;
                const gapTop = row.minTop;
                const gapHeight = row.maxBottom - row.minTop;
                
                // insertIndex: vor dem rechten Element einfügen
                gaps.push({
                    orientation: 'vertical',
                    x: gapMidX - gridLeft,
                    y: gapTop - gridTop,
                    width: 0,
                    height: gapHeight,
                    insertIndex: right.index,
                    _midX: gapMidX,
                    _midY: gapTop + gapHeight / 2
                });
            }
        });
        
        return gaps;
    }
    
    // === Drop-Mode (während Drag & Drop) ===
    
    let _dropMode = false;
    
    /**
     * Aktiviert/deaktiviert den Drop-Modus.
     * Im Drop-Modus: Indikator zeigt Drop-Zone statt Add-Button.
     */
    function setDropMode(enabled) {
        _dropMode = enabled;
        if (_indicator) {
            _indicator.classList.toggle('v2-insert-drop-mode', enabled);
        }
        if (!enabled) {
            hideIndicator();
        }
    }
    
    /**
     * Gibt gecachte Gaps zurück (berechnet bei Bedarf).
     */
    function getGaps() {
        if (_gaps.length === 0) {
            _gaps = computeGaps();
        }
        return _gaps;
    }
    
    /**
     * Findet den nächsten Gap zu einer Mausposition.
     * @param {number} mouseX - clientX
     * @param {number} mouseY - clientY
     * @param {number} maxDist - Max-Entfernung in px (default 60)
     * @returns {{ gap: object, dist: number } | null}
     */
    function findNearestGap(mouseX, mouseY, maxDist) {
        const gaps = getGaps();
        if (gaps.length === 0) return null;
        
        let bestGap = null;
        let bestDist = maxDist || 60;
        
        for (const gap of gaps) {
            let dist;
            if (gap.orientation === 'horizontal') {
                dist = Math.abs(mouseY - gap._midY);
            } else {
                const gridRect = _gridEl.getBoundingClientRect();
                const gapTopAbs = gridRect.top + gap.y;
                const gapBottomAbs = gapTopAbs + gap.height;
                if (mouseY < gapTopAbs - 10 || mouseY > gapBottomAbs + 10) {
                    continue;
                }
                dist = Math.abs(mouseX - gap._midX);
            }
            
            if (dist < bestDist) {
                bestDist = dist;
                bestGap = gap;
            }
        }
        
        return bestGap ? { gap: bestGap, dist: bestDist } : null;
    }
    
    /**
     * Maus-Tracking: Nächsten Gap (horizontal oder vertikal) finden
     */
    function onGridMouseMove(e) {
        // Nicht anzeigen während Drag & Drop (wird von drag-drop.js gesteuert)
        if (_dropMode || document.querySelector('.v2-dragging')) {
            return;
        }
        
        // Gaps bei Bedarf neu berechnen (Cache)
        if (_gaps.length === 0) {
            _gaps = computeGaps();
        }
        if (_gaps.length === 0) return;
        
        const result = findNearestGap(e.clientX, e.clientY, 40);
        
        if (result) {
            cancelHide();
            showIndicator(result.gap);
        } else {
            scheduleHide(150);
        }
    }
    
    function showIndicator(gap) {
        if (!_indicator) return;
        _currentInsertIndex = gap.insertIndex;
        
        const gridOffsetTop = _gridEl.offsetTop;
        const gridOffsetLeft = _gridEl.offsetLeft;
        
        // Orientierung wechseln
        if (gap.orientation !== _currentOrientation) {
            _currentOrientation = gap.orientation;
            _indicator.classList.toggle('v2-insert-vertical', gap.orientation === 'vertical');
        }
        
        _indicator.style.display = 'flex';
        
        if (gap.orientation === 'horizontal') {
            // Volle Breite, horizontal positioniert
            _indicator.style.top = (gridOffsetTop + gap.y) + 'px';
            _indicator.style.left = '20px';
            _indicator.style.right = '20px';
            _indicator.style.width = '';
            _indicator.style.height = '';
        } else {
            // Vertikal: an Gap-X, über die Zeilenhöhe
            _indicator.style.top = (gridOffsetTop + gap.y) + 'px';
            _indicator.style.left = (gridOffsetLeft + gap.x) + 'px';
            _indicator.style.right = '';
            _indicator.style.width = '';
            _indicator.style.height = gap.height + 'px';
        }
    }
    
    function hideIndicator() {
        if (_indicator && (!_popup || _popup.style.display !== 'block')) {
            _indicator.style.display = 'none';
        }
    }
    
    function scheduleHide(delay) {
        cancelHide();
        _hideTimeout = setTimeout(hideIndicator, delay);
    }
    
    function cancelHide() {
        if (_hideTimeout) {
            clearTimeout(_hideTimeout);
            _hideTimeout = null;
        }
    }
    
    // ===== Type Popup =====
    
    function createTypePopup() {
        _popup = document.createElement('div');
        _popup.className = 'v2-type-popup';
        _popup.style.display = 'none';
        
        const types = V2State.getTileTypes();
        let html = '<div class="v2-type-popup-header">Kachel-Typ wählen</div>';
        html += '<div class="v2-type-popup-grid">';
        
        const typeIcons = {
            'infobox': '📝',
            'download': '📎',
            'image': '🖼️',
            'link': '🔗',
            'quote': '💬',
            'separator': '➖',
            'section': '🧱',
            'contact': '👤',
            'countdown': '⏰',
            'iframe': '🌐',
            'accordion': '📋'
        };
        
        Object.entries(types).forEach(([key, type]) => {
            const icon = typeIcons[key] || '📦';
            html += `
                <button class="v2-type-option" data-type="${key}" title="${type.description || type.name}">
                    <span class="v2-type-icon">${icon}</span>
                    <span class="v2-type-name">${type.name}</span>
                </button>
            `;
        });
        
        html += '</div>';
        html += '<button class="v2-type-popup-close" title="Schließen">&times;</button>';
        _popup.innerHTML = html;
        document.body.appendChild(_popup);
        
        // Close button
        _popup.querySelector('.v2-type-popup-close').addEventListener('click', (e) => {
            e.stopPropagation();
            hideTypePopup();
        });
        
        // Click außerhalb schließt Popup
        document.addEventListener('click', (e) => {
            if (_popup.style.display !== 'block') return;
            if (!e.target.closest('.v2-type-popup') && 
                !e.target.closest('.v2-insert-trigger') &&
                !e.target.closest('.v2-add-tile-btn')) {
                hideTypePopup();
            }
        });
        
        // Escape schließt Popup
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && _popup.style.display === 'block') {
                e.stopPropagation();
                hideTypePopup();
            }
        });
    }
    
    /**
     * Zeigt das Typ-Popup, aufrufbar auch von addTile() (canvas.js)
     */
    function showTypePopup(triggerEl, insertIndex) {
        if (!_popup) return;
        
        // Handler binden
        _popup.querySelectorAll('.v2-type-option').forEach(btn => {
            btn.onclick = () => {
                const type = btn.dataset.type;
                hideTypePopup();
                insertTileAtPosition(type, insertIndex);
            };
        });
        
        const rect = triggerEl.getBoundingClientRect();
        _popup.style.display = 'block';
        
        const popupW = _popup.offsetWidth;
        const popupH = _popup.offsetHeight;
        
        let left = rect.left + rect.width / 2 - popupW / 2;
        let top = rect.bottom + 8;
        
        // Viewport-Clamp
        left = Math.max(8, Math.min(left, window.innerWidth - popupW - 8));
        if (top + popupH > window.innerHeight - 8) {
            top = rect.top - popupH - 8;
        }
        
        _popup.style.left = left + 'px';
        _popup.style.top = top + 'px';
    }
    
    function hideTypePopup() {
        if (_popup) _popup.style.display = 'none';
    }
    
    /**
     * Fügt eine neue Tile an einer bestimmten Position ein
     */
    async function insertTileAtPosition(type, insertIndex) {
        const tiles = V2State.getTiles();
        const types = V2State.getTileTypes();
        
        let position;
        if (tiles.length === 0) {
            position = 10;
        } else if (insertIndex <= 0) {
            position = (tiles[0]?.position || 10) - 10;
        } else if (insertIndex >= tiles.length) {
            position = (tiles[tiles.length - 1]?.position || 0) + 10;
        } else {
            const before = tiles[insertIndex - 1]?.position || 0;
            const after = tiles[insertIndex]?.position || before + 20;
            const gap = after - before;
            if (gap >= 2) {
                // Enough room to bisect
                position = Math.round((before + after) / 2);
            } else {
                // Gap too small → rebalance all positions first
                tiles.forEach((t, i) => { t.position = (i + 1) * 10; });
                const rebalanced = tiles.map(t => ({ id: t.id, position: t.position }));
                try { await V2Api.updatePositions(rebalanced); } catch(e) { /* best effort */ }
                const bNew = tiles[insertIndex - 1].position;
                const aNew = tiles[insertIndex].position;
                position = Math.round((bNew + aNew) / 2);
            }
        }
        
        const newTile = {
            type: type,
            position: position,
            size: ['separator', 'section'].includes(type) ? 'full' : 'medium',
            style: ['separator', 'section'].includes(type) ? 'flat' : 'card',
            colorScheme: 'default',
            data: { title: 'Neue ' + (types[type]?.name || type) }
        };
        
        // Typ-spezifische Defaults
        if (type === 'quote') newTile.data.quote = 'Zitat hier eingeben...';
        if (type === 'separator') { newTile.data.height = 20; newTile.data.showLine = true; }
        if (type === 'section') {
            newTile.data.backgroundMode = 'default';
            newTile.data.backgroundAttachment = 'content';
            newTile.data.backgroundMotionPercent = 0;
            newTile.data.backgroundDisplay = 'cover';
            newTile.data.overlayEnabled = false;
            newTile.data.overlayColorEnabled = true;
            newTile.data.overlayColor = '#000000';
            newTile.data.overlayOpacity = 35;
            newTile.data.overlayBlurEnabled = false;
            newTile.data.overlayBlurStrength = 24;
        }
        if (type === 'link') { newTile.data.url = ''; newTile.data.linkText = 'Mehr erfahren'; }
        if (type === 'image') { newTile.data.image = ''; }
        if (type === 'contact') { newTile.data.name = ''; }
        if (type === 'download') { newTile.data.file = ''; }
        if (type === 'iframe') { newTile.data.url = ''; }
        if (type === 'countdown') { 
            newTile.data.targetDate = new Date(Date.now() + 86400000 * 7).toISOString().split('T')[0];
            newTile.data.targetTime = '00:00';
        }
        if (type === 'accordion') {
            newTile.data.section1_heading = 'Abschnitt 1';
            newTile.data.section1_content = 'Inhalt hier...';
        }
        
        // Types that need user input before saving → open edit modal first
        const needsInput = ['image', 'download', 'iframe', 'link', 'contact'];
        if (needsInput.includes(type)) {
            // Open edit modal with the draft tile (no id = new tile)
            V2EditModal.open(newTile);
            return;
        }
        
        // Simple types: save directly
        try {
            const result = await V2Api.saveTile(newTile);
            if (result.success) {
                showToast(types[type]?.name + ' eingefügt', 'success');
                await V2Canvas.reloadAll();
                if (result.tile?.id) {
                    V2State.selectTile(result.tile.id);
                }
            } else {
                showToast('Fehler: ' + (result.error || 'Unbekannt'), 'error');
            }
        } catch(err) {
            console.error('[Insert] insertTile failed:', err);
            showToast('Einfügen fehlgeschlagen', 'error');
        }
    }
    
    /**
     * Invalidiert den Gap-Cache (aufgerufen bei Resize etc.)
     */
    function invalidateCache() {
        _gaps = [];
    }
    
    return {
        init,
        showTypePopup,
        hideTypePopup,
        invalidateCache,
        // Für Drag & Drop:
        setDropMode,
        getGaps,
        findNearestGap,
        showIndicator,
        hideIndicator
    };
})();

export { V2Insert };
export default V2Insert;
