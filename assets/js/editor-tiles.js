/**
 * Editor Tiles - Tile-Rendering, Context-Menus, Quick-Edit, Sichtbarkeit, CRUD
 * 
 * Abhängigkeiten: editor-core.js (apiPost, showToast, escapeHtml, tiles, settings)
 */

// ===== Tile Rendering =====
function renderTiles() {
    const container = document.getElementById('tilesList');
    const countEl = document.getElementById('tileCount');
    
    if (tiles.length === 0) {
        container.innerHTML = '<div class="tiles-list empty">Noch keine Kacheln vorhanden. Füge deine erste Kachel hinzu!</div>';
        container.classList.add('empty');
    } else {
        container.classList.remove('empty');
        container.innerHTML = tiles.map(tile => renderTileCard(tile)).join('');
    }
    
    countEl.textContent = `${tiles.length} Kachel${tiles.length !== 1 ? 'n' : ''}`;
}

function renderTileCard(tile) {
    const typeInfo = tileTypes[tile.type] || { name: tile.type };
    
    // Separator-Tiles bekommen eine spezielle Darstellung
    if (tile.type === 'separator') {
        return renderSeparatorCard(tile, typeInfo);
    }
    
    let title = tile.data?.title || tile.data?.name || 'Ohne Titel';
    
    // Bei Iframe ohne Titel: URL anzeigen
    if (tile.type === 'iframe' && !tile.data?.title && tile.data?.url) {
        try {
            const url = new URL(tile.data.url);
            title = url.hostname;
        } catch (e) {
            title = 'Iframe';
        }
    }
    
    // Countdown-Status prüfen
    let countdownBadge = '';
    if (tile.type === 'countdown' && tile.data?.targetDate) {
        const targetDate = tile.data.targetDate;
        const targetTime = tile.data.targetTime || '00:00';
        const target = new Date(`${targetDate}T${targetTime}:00`);
        const now = new Date();
        
        if (target <= now) {
            const hideOnExpiry = tile.data.hideOnExpiry;
            if (hideOnExpiry) {
                countdownBadge = '<span class="countdown-status-badge expired-hidden" title="Countdown abgelaufen - wird ausgeblendet">⏱️ Abgelaufen & ausgeblendet</span>';
            } else {
                countdownBadge = '<span class="countdown-status-badge expired" title="Countdown abgelaufen">⏱️ Abgelaufen</span>';
            }
        }
    }
    
    // Prüfe auf doppelte Positionen
    const duplicatePos = tiles.filter(t => t.position === tile.position).length > 1;
    const posClass = duplicatePos ? 'duplicate-pos' : '';
    
    // Visibility Status berechnen
    const visStatus = getVisibilityStatus(tile);
    const hiddenClass = visStatus.effectivelyHidden ? 'tile-hidden' : '';
    
    return `
        <div class="tile-card ${hiddenClass}" data-id="${tile.id}">
            <div class="tile-info">
                <div class="tile-title">
                    ${escapeHtml(title)}
                    ${countdownBadge}
                </div>
                <div class="tile-meta">
                    <span class="tile-type-badge">${escapeHtml(typeInfo.name)}</span>
                    <button class="quick-edit-btn ${posClass}" onclick="showPositionEdit(event, '${tile.id}')" title="Position ändern">
                        📍 ${tile.position}
                    </button>
                    <button class="quick-edit-btn" onclick="showSizeMenu(event, '${tile.id}')" title="Größe ändern">
                        📐 ${getSizeLabel(tile.size)}
                    </button>
                    <button class="quick-edit-btn" onclick="showStyleMenu(event, '${tile.id}')" title="Stil ändern">
                        ${tile.style === 'flat' ? '▭' : '▢'} ${tile.style === 'flat' ? 'Flat' : 'Card'}
                    </button>
                    <button class="quick-edit-btn" onclick="showColorMenu(event, '${tile.id}')" title="Farbe ändern">
                        🎨 ${getColorLabel(tile.colorScheme)}
                    </button>
                    <div class="visibility-control">
                        <button class="quick-edit-btn ${visStatus.btnClass}" onclick="toggleVisibility('${tile.id}')" title="${visStatus.tooltip}">
                            ${visStatus.icon} ${visStatus.label}
                        </button>
                        <button class="quick-edit-btn visibility-dropdown-btn ${visStatus.hasSchedule ? 'has-schedule' : ''}" onclick="showVisibilitySchedule(event, '${tile.id}')" title="Zeitsteuerung">
                            ▼
                        </button>
                    </div>
                    ${visStatus.notExported ? '<span class="not-exported-badge" title="Diese Kachel wird beim Veröffentlichen NICHT exportiert">⛔ Nicht im Export</span>' : ''}
                </div>
            </div>
            <div class="tile-actions">
                <button class="btn btn-icon" onclick="duplicateTile('${tile.id}')" title="Duplizieren">
                    📋
                </button>
                <button class="btn btn-icon" onclick="editTile('${tile.id}')" title="Bearbeiten">
                    ✏️
                </button>
                <button class="btn btn-icon" onclick="deleteTile('${tile.id}')" title="Löschen">
                    🗑️
                </button>
            </div>
        </div>
    `;
}

// Spezielle Darstellung für Separator-Tiles im Editor
function renderSeparatorCard(tile, typeInfo) {
    const height = tile.data?.height ?? 40;
    const showLine = tile.data?.showLine ?? false;
    const lineWidth = tile.data?.lineWidth ?? 'medium';
    
    // Prüfe auf doppelte Positionen
    const duplicatePos = tiles.filter(t => t.position === tile.position).length > 1;
    const posClass = duplicatePos ? 'duplicate-pos' : '';
    
    // Visibility Status
    const visStatus = getVisibilityStatus(tile);
    const hiddenClass = visStatus.effectivelyHidden ? 'tile-hidden' : '';
    
    // Beschreibung erstellen
    let description = `${height}px`;
    if (showLine) {
        const widthLabels = { small: 'kurz', medium: 'mittel', large: 'voll' };
        description += ` · Linie ${widthLabels[lineWidth] || lineWidth}`;
    }
    
    return `
        <div class="tile-card tile-card-separator ${hiddenClass}" data-id="${tile.id}">
            <div class="tile-info">
                <div class="tile-title separator-title">
                    ─────── ${escapeHtml(typeInfo.name)} ───────
                </div>
                <div class="tile-meta">
                    <span class="tile-type-badge">${escapeHtml(description)}</span>
                    <button class="quick-edit-btn ${posClass}" onclick="showPositionEdit(event, '${tile.id}')" title="Position ändern">
                        📍 ${tile.position}
                    </button>
                    <div class="visibility-control">
                        <button class="quick-edit-btn ${visStatus.btnClass}" onclick="toggleVisibility('${tile.id}')" title="${visStatus.tooltip}">
                            ${visStatus.icon} ${visStatus.label}
                        </button>
                    </div>
                    ${visStatus.notExported ? '<span class="not-exported-badge" title="Dieser Trenner wird beim Veröffentlichen NICHT exportiert">⛔ Nicht im Export</span>' : ''}
                </div>
            </div>
            <div class="tile-actions">
                <button class="btn btn-icon" onclick="editTile('${tile.id}')" title="Bearbeiten">
                    ✏️
                </button>
                <button class="btn btn-icon" onclick="deleteTile('${tile.id}')" title="Löschen">
                    🗑️
                </button>
            </div>
        </div>
    `;
}

// ===== Visibility Status =====
function getVisibilityStatus(tile) {
    const now = new Date();
    const schedule = tile.visibilitySchedule || {};
    const showFrom = schedule.showFrom ? new Date(schedule.showFrom) : null;
    const showUntil = schedule.showUntil ? new Date(schedule.showUntil) : null;
    const hasSchedule = showFrom || showUntil;
    
    // Manuell versteckt hat höchste Priorität - wird NICHT exportiert!
    if (tile.visible === false) {
        return {
            effectivelyHidden: true,
            icon: '⛔',
            label: 'Versteckt',
            btnClass: 'visibility-hidden',
            tooltip: 'Manuell versteckt - wird NICHT exportiert! Klicken zum Einblenden.',
            hasSchedule,
            notExported: true
        };
    }
    
    // Zeitplan prüfen - diese werden exportiert, aber per JS gesteuert
    if (showFrom && now < showFrom) {
        const dateStr = formatDateShort(showFrom);
        return {
            effectivelyHidden: true,
            icon: '🕐',
            label: `Ab ${dateStr}`,
            btnClass: 'visibility-scheduled',
            tooltip: `Wird ab ${formatDateTime(showFrom)} sichtbar (im Export enthalten, per JS gesteuert)`,
            hasSchedule: true,
            notExported: false
        };
    }
    
    if (showUntil && now > showUntil) {
        return {
            effectivelyHidden: true,
            icon: '⏱️',
            label: 'Abgelaufen',
            btnClass: 'visibility-expired',
            tooltip: `War sichtbar bis ${formatDateTime(showUntil)} (im Export enthalten, per JS versteckt)`,
            hasSchedule: true,
            notExported: false
        };
    }
    
    // Sichtbar, aber mit aktivem Zeitplan
    if (showUntil && now <= showUntil) {
        const dateStr = formatDateShort(showUntil);
        return {
            effectivelyHidden: false,
            icon: '👁️',
            label: `Bis ${dateStr}`,
            btnClass: 'visibility-until',
            tooltip: `Sichtbar bis ${formatDateTime(showUntil)} (im Export enthalten)`,
            hasSchedule: true,
            notExported: false
        };
    }
    
    // Normal sichtbar
    return {
        effectivelyHidden: false,
        icon: '👁️',
        label: 'Sichtbar',
        btnClass: '',
        tooltip: 'Sichtbar - Klicken zum Verstecken',
        hasSchedule,
        notExported: false
    };
}

// ===== Formatting Helpers =====
function formatDateShort(date) {
    return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' });
}

function formatDateTime(date) {
    return date.toLocaleDateString('de-DE', { 
        day: '2-digit', 
        month: '2-digit', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getSizeLabel(size) {
    const labels = {
        'small': '1/4',
        'medium': '2/4',
        'large': '3/4',
        'full': '4/4'
    };
    return labels[size] || size;
}

function getColorLabel(color) {
    const labels = {
        'default': 'Standard',
        'white': 'Weiß',
        'accent1': 'Akzent 1',
        'accent2': 'Akzent 2',
        'accent3': 'Akzent 3'
    };
    return labels[color] || color || 'Standard';
}

// ===== Context Menu / Quick-Edit =====
let activeContextMenu = null;

function showContextMenu(event, content) {
    event.stopPropagation();
    hideContextMenu();
    
    const menu = document.getElementById('contextMenu');
    menu.querySelector('.context-menu-content').innerHTML = content;
    menu.style.display = 'block';
    
    // Position berechnen
    const rect = event.target.getBoundingClientRect();
    menu.style.top = `${rect.bottom + 5}px`;
    menu.style.left = `${rect.left}px`;
    
    // Außerhalb-Klick Handler
    activeContextMenu = menu;
    setTimeout(() => {
        document.addEventListener('click', hideContextMenu, { once: true });
    }, 10);
}

// Persistentes Context-Menu (nur schließen bei explizitem Aufruf)
function showContextMenuPersistent(event, content) {
    event.stopPropagation();
    hideContextMenu();
    
    const menu = document.getElementById('contextMenu');
    menu.querySelector('.context-menu-content').innerHTML = content;
    menu.style.display = 'block';
    
    // Position berechnen
    const rect = event.target.getBoundingClientRect();
    menu.style.top = `${rect.bottom + 5}px`;
    menu.style.left = `${rect.left}px`;
    
    activeContextMenu = menu;
    
    // Klick außerhalb (aber nicht im Menü selbst) schließt
    const closeOnOutsideClick = (e) => {
        if (!menu.contains(e.target)) {
            hideContextMenu();
            document.removeEventListener('click', closeOnOutsideClick);
        }
    };
    
    setTimeout(() => {
        document.addEventListener('click', closeOnOutsideClick);
    }, 10);
    
    // ESC-Handler
    const escHandler = (e) => {
        if (e.key === 'Escape') {
            hideContextMenu();
            document.removeEventListener('keydown', escHandler);
        }
    };
    document.addEventListener('keydown', escHandler);
}

function hideContextMenu() {
    const menu = document.getElementById('contextMenu');
    if (menu) {
        menu.style.display = 'none';
    }
    activeContextMenu = null;
}

// ===== Quick-Edit Menus =====
function showPositionEdit(event, tileId) {
    const tile = tiles.find(t => t.id === tileId);
    if (!tile) return;
    
    showContextMenuPersistent(event, `
        <div class="context-input-group">
            <label>Position:</label>
            <input type="number" id="posInput" value="${tile.position}" step="10" min="0" 
                   onclick="event.stopPropagation();"
                   onkeydown="if(event.key==='Enter'){updateTileProperty('${tileId}','position',this.value);hideContextMenu();}if(event.key==='Escape'){hideContextMenu();}">
            <button class="btn btn-small btn-primary" onclick="updateTileProperty('${tileId}','position',document.getElementById('posInput').value);hideContextMenu();">
                ✓
            </button>
        </div>
    `);
    
    setTimeout(() => {
        const input = document.getElementById('posInput');
        if (input) {
            input.focus();
            input.select();
        }
    }, 50);
}

function showSizeMenu(event, tileId) {
    const tile = tiles.find(t => t.id === tileId);
    if (!tile) return;
    
    const sizes = [
        { value: 'small', label: 'Klein (1/4)', short: 'S' },
        { value: 'medium', label: 'Mittel (2/4)', short: 'M' },
        { value: 'large', label: 'Groß (3/4)', short: 'L' },
        { value: 'full', label: 'Voll (4/4)', short: 'Voll' }
    ];
    
    showContextMenu(event, `
        <div class="context-menu-list">
            ${sizes.map(s => `
                <button class="context-menu-item ${tile.size === s.value ? 'active' : ''}" 
                        onclick="updateTileProperty('${tileId}','size','${s.value}');hideContextMenu();">
                    ${s.label}
                </button>
            `).join('')}
        </div>
    `);
}

function showStyleMenu(event, tileId) {
    const tile = tiles.find(t => t.id === tileId);
    if (!tile) return;
    
    showContextMenu(event, `
        <div class="context-menu-list">
            <button class="context-menu-item ${tile.style === 'flat' ? 'active' : ''}" 
                    onclick="updateTileStyle('${tileId}','flat');hideContextMenu();">
                ▭ Flat (transparent)
            </button>
            <button class="context-menu-item ${tile.style === 'card' ? 'active' : ''}" 
                    onclick="updateTileStyle('${tileId}','card');hideContextMenu();">
                ▢ Card (mit Schatten)
            </button>
        </div>
    `);
}

function showColorMenu(event, tileId) {
    const tile = tiles.find(t => t.id === tileId);
    if (!tile) return;
    
    const colors = [
        { value: 'default', label: 'Standard (Hintergrund)' },
        { value: 'white', label: 'Weiß' },
        { value: 'accent1', label: 'Akzent 1 (Seitentitel)' },
        { value: 'accent2', label: 'Akzent 2' },
        { value: 'accent3', label: 'Akzent 3' }
    ];
    
    // Bei Flat: Weiß ausblenden (macht keinen Sinn)
    const availableColors = tile.style === 'flat' 
        ? colors.filter(c => c.value !== 'white')
        : colors;
    
    showContextMenu(event, `
        <div class="context-menu-list">
            ${availableColors.map(c => `
                <button class="context-menu-item ${tile.colorScheme === c.value ? 'active' : ''}" 
                        onclick="updateTileProperty('${tileId}','colorScheme','${c.value}');hideContextMenu();">
                    ${c.label}
                </button>
            `).join('')}
        </div>
    `);
}

// ===== Visibility Toggle & Schedule =====
async function toggleVisibility(tileId) {
    const tile = tiles.find(t => t.id === tileId);
    if (!tile) return;
    
    // Toggle: undefined/true -> false, false -> true
    const newVisibility = tile.visible === false ? true : false;
    await updateTileProperty(tileId, 'visible', newVisibility);
    
    const status = newVisibility ? 'sichtbar' : 'versteckt';
    showToast('info', `Kachel ist jetzt ${status}`);
}

function showVisibilitySchedule(event, tileId) {
    const tile = tiles.find(t => t.id === tileId);
    if (!tile) return;
    
    const schedule = tile.visibilitySchedule || {};
    const showFrom = schedule.showFrom || '';
    const showUntil = schedule.showUntil || '';
    
    // Datetime-local Format: YYYY-MM-DDTHH:MM
    const showFromValue = showFrom ? showFrom.substring(0, 16) : '';
    const showUntilValue = showUntil ? showUntil.substring(0, 16) : '';
    
    showContextMenuPersistent(event, `
        <div class="visibility-schedule-form">
            <div class="schedule-row">
                <label>📅 Einblenden ab:</label>
                <div class="schedule-input-group">
                    <input type="datetime-local" id="scheduleShowFrom" value="${showFromValue}" 
                           onchange="updateVisibilitySchedule('${tileId}')">
                    <button type="button" class="btn btn-icon btn-small" onclick="clearScheduleField('${tileId}', 'showFrom')" title="Löschen">
                        🗑️
                    </button>
                </div>
            </div>
            <div class="schedule-row">
                <label>📅 Ausblenden ab:</label>
                <div class="schedule-input-group">
                    <input type="datetime-local" id="scheduleShowUntil" value="${showUntilValue}"
                           onchange="updateVisibilitySchedule('${tileId}')">
                    <button type="button" class="btn btn-icon btn-small" onclick="clearScheduleField('${tileId}', 'showUntil')" title="Löschen">
                        🗑️
                    </button>
                </div>
            </div>
            <div class="schedule-presets">
                <button type="button" class="btn btn-small" onclick="setSchedulePreset('${tileId}', 'week')">+1 Woche</button>
                <button type="button" class="btn btn-small" onclick="setSchedulePreset('${tileId}', 'month')">+1 Monat</button>
                <button type="button" class="btn btn-small btn-secondary" onclick="clearAllSchedule('${tileId}')">Zeitplan löschen</button>
            </div>
            <div class="schedule-hint">
                ⚠️ Zeitgesteuerte Inhalte bleiben im Quelltext sichtbar
            </div>
        </div>
    `);
}

async function updateVisibilitySchedule(tileId) {
    const tile = tiles.find(t => t.id === tileId);
    if (!tile) return;
    
    const showFrom = document.getElementById('scheduleShowFrom')?.value || null;
    const showUntil = document.getElementById('scheduleShowUntil')?.value || null;
    
    // Validierung: showFrom sollte vor showUntil sein
    if (showFrom && showUntil && new Date(showFrom) >= new Date(showUntil)) {
        showToast('warning', 'Einblende-Datum muss vor Ausblende-Datum liegen');
        return;
    }
    
    // Prüfen ob erstmalig Zeitsteuerung aktiviert wird
    const hadScheduleBefore = tile.visibilitySchedule && 
        (tile.visibilitySchedule.showFrom || tile.visibilitySchedule.showUntil);
    const hasScheduleNow = showFrom || showUntil;
    
    tile.visibilitySchedule = {};
    if (showFrom) tile.visibilitySchedule.showFrom = showFrom;
    if (showUntil) tile.visibilitySchedule.showUntil = showUntil;
    
    // Leeres Objekt entfernen
    if (Object.keys(tile.visibilitySchedule).length === 0) {
        delete tile.visibilitySchedule;
    }
    
    await quickSaveTile(tile);
    showToast('success', 'Zeitplan gespeichert');
    
    // Sicherheitshinweis anzeigen wenn erstmalig Zeitsteuerung aktiviert
    if (!hadScheduleBefore && hasScheduleNow) {
        showScheduleSecurityWarning();
    }
}

// Sicherheitshinweis für Zeitsteuerung
let scheduleWarningShown = false;
function showScheduleSecurityWarning() {
    // Nur einmal pro Session anzeigen
    if (scheduleWarningShown) return;
    scheduleWarningShown = true;
    
    showToast('warning', 
        '⚠️ Hinweis: Zeitgesteuerte Inhalte sind im Quelltext der Seite sichtbar und können von versierten Nutzern oder Suchmaschinen gefunden werden. Keine sensiblen Daten in zeitgesteuerten Kacheln verwenden!',
        10000  // 10 Sekunden anzeigen
    );
}

async function clearScheduleField(tileId, field) {
    const tile = tiles.find(t => t.id === tileId);
    if (!tile) return;
    
    if (tile.visibilitySchedule) {
        delete tile.visibilitySchedule[field];
        
        // Leeres Objekt entfernen
        if (Object.keys(tile.visibilitySchedule).length === 0) {
            delete tile.visibilitySchedule;
        }
    }
    
    // Input-Feld leeren
    const inputId = field === 'showFrom' ? 'scheduleShowFrom' : 'scheduleShowUntil';
    const input = document.getElementById(inputId);
    if (input) input.value = '';
    
    await quickSaveTile(tile);
    showToast('info', 'Zeitpunkt gelöscht');
}

async function clearAllSchedule(tileId) {
    const tile = tiles.find(t => t.id === tileId);
    if (!tile) return;
    
    delete tile.visibilitySchedule;
    
    // Input-Felder leeren
    const showFromInput = document.getElementById('scheduleShowFrom');
    const showUntilInput = document.getElementById('scheduleShowUntil');
    if (showFromInput) showFromInput.value = '';
    if (showUntilInput) showUntilInput.value = '';
    
    await quickSaveTile(tile);
    hideContextMenu();
    showToast('info', 'Zeitplan gelöscht');
}

async function setSchedulePreset(tileId, preset) {
    const tile = tiles.find(t => t.id === tileId);
    if (!tile) return;
    
    const now = new Date();
    let showUntil;
    
    switch (preset) {
        case 'week':
            showUntil = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
            break;
        case 'month':
            showUntil = new Date(now.getFullYear(), now.getMonth() + 1, now.getDate(), now.getHours(), now.getMinutes());
            break;
        default:
            return;
    }
    
    // Format für datetime-local
    const showUntilStr = showUntil.toISOString().substring(0, 16);
    
    tile.visibilitySchedule = tile.visibilitySchedule || {};
    tile.visibilitySchedule.showUntil = showUntilStr;
    
    // Input-Feld aktualisieren
    const input = document.getElementById('scheduleShowUntil');
    if (input) input.value = showUntilStr;
    
    await quickSaveTile(tile);
    showToast('success', `Sichtbar bis ${formatDateTime(showUntil)}`);
}

// ===== Tile Property Updates =====

// Style ändern mit Logik für Farbe
function updateTileStyle(tileId, newStyle) {
    const tile = tiles.find(t => t.id === tileId);
    if (!tile) return;
    
    // Wenn zu Flat wechseln und Farbe ist "weiß" → auf default setzen
    if (newStyle === 'flat' && tile.colorScheme === 'white') {
        updateTileProperties(tileId, { style: newStyle, colorScheme: 'default' });
    } else {
        updateTileProperty(tileId, 'style', newStyle);
    }
}

// Einzelne Property schnell ändern
async function updateTileProperty(tileId, property, value) {
    const tile = tiles.find(t => t.id === tileId);
    if (!tile) return;
    
    // Wert konvertieren
    if (property === 'position') {
        value = parseInt(value) || 0;
    }
    
    tile[property] = value;
    await quickSaveTile(tile);
}

// Mehrere Properties gleichzeitig ändern
async function updateTileProperties(tileId, properties) {
    const tile = tiles.find(t => t.id === tileId);
    if (!tile) return;
    
    Object.assign(tile, properties);
    await quickSaveTile(tile);
}

// Schnelles Speichern ohne Modal
async function quickSaveTile(tile) {
    try {
        const result = await apiPost('save_tile', { tile: tile });
        
        if (result.success) {
            // Tiles neu laden und sortieren
            tiles = result.tiles || tiles;
            tiles.sort((a, b) => (a.position ?? 0) - (b.position ?? 0));
            renderTiles();
            refreshPreview();
        } else {
            showToast('error', result.error || 'Fehler beim Speichern');
        }
    } catch (error) {
        console.error('Quick save error:', error);
        showToast('error', 'Netzwerkfehler');
    }
}

// ===== Tile CRUD =====
async function saveTile(event) {
    event.preventDefault();
    
    const form = document.getElementById('tileForm');
    const formData = new FormData(form);
    
    // Tile-Daten zusammenstellen
    const existingTile = formData.get('id') ? tiles.find(t => t.id === formData.get('id')) : null;
    const tileType = formData.get('type');
    
    // Separator-Tiles bekommen immer size "full" und style "flat"
    const isSeparator = tileType === 'separator';
    
    const tileData = {
        id: formData.get('id') || null,
        type: tileType,
        position: parseInt(formData.get('position')) || 10,
        size: isSeparator ? 'full' : (formData.get('size') || 'medium'),
        style: isSeparator ? 'flat' : (formData.get('style') || 'card'),
        colorScheme: isSeparator ? 'default' : (formData.get('colorScheme') || 'default'),
        visible: existingTile?.visible ?? true,
        visibilitySchedule: existingTile?.visibilitySchedule || undefined,
        data: {}
    };
    
    // Data-Felder extrahieren
    for (const [key, value] of formData.entries()) {
        if (key.startsWith('data[')) {
            const fieldName = key.match(/data\[(.+)\]/)[1];
            tileData.data[fieldName] = value;
        }
    }
    
    // Checkboxen behandeln (nicht ausgewählt = false)
    const checkboxes = form.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(cb => {
        const match = cb.name.match(/data\[(.+)\]/);
        if (match) {
            tileData.data[match[1]] = cb.checked;
        }
    });
    
    try {
        const result = await apiPost('save_tile', { tile: tileData });
        
        if (result.success) {
            // Lokale Liste aktualisieren
            if (tileData.id) {
                const index = tiles.findIndex(t => t.id === tileData.id);
                if (index !== -1) {
                    tiles[index] = result.tile;
                }
            } else {
                tiles.push(result.tile);
            }
            
            // Sortieren
            tiles.sort((a, b) => (a.position || 0) - (b.position || 0));
            
            renderTiles();
            closeTileModal();
            showToast('success', 'Kachel gespeichert!');
            refreshPreview();
        } else {
            const errors = result.errors?.join(', ') || 'Speichern fehlgeschlagen';
            showToast('error', errors);
        }
    } catch (error) {
        console.error('Save error:', error);
        showToast('error', 'Netzwerkfehler beim Speichern');
    }
}

function editTile(id) {
    openTileModal(id);
}

async function duplicateTile(id) {
    const originalTile = tiles.find(t => t.id === id);
    if (!originalTile) return;
    
    // Neue Tile-Daten erstellen (ohne Zeitsteuerung - Duplikat startet frisch)
    const duplicatedTile = {
        type: originalTile.type,
        position: (originalTile.position || 0) + 10,
        size: originalTile.size || 'medium',
        style: originalTile.style || 'card',
        colorScheme: originalTile.colorScheme || 'default',
        visible: originalTile.visible ?? true,
        // visibilitySchedule bewusst NICHT kopieren
        data: { ...originalTile.data }
    };
    
    // Titel anpassen falls vorhanden
    if (duplicatedTile.data.title) {
        duplicatedTile.data.title += ' (Kopie)';
    }
    
    try {
        const result = await apiPost('save_tile', { tile: duplicatedTile });
        
        if (result.success) {
            tiles.push(result.tile);
            tiles.sort((a, b) => (a.position || 0) - (b.position || 0));
            
            renderTiles();
            showToast('success', 'Kachel dupliziert!');
            refreshPreview();
        } else {
            const errors = result.errors?.join(', ') || 'Duplizieren fehlgeschlagen';
            showToast('error', errors);
        }
    } catch (error) {
        console.error('Duplicate error:', error);
        showToast('error', 'Netzwerkfehler beim Duplizieren');
    }
}

async function deleteTile(id) {
    if (!confirm('Kachel wirklich löschen?')) return;
    
    try {
        const result = await apiPost('delete_tile', { id });
        
        if (result.success) {
            tiles = tiles.filter(t => t.id !== id);
            renderTiles();
            showToast('success', 'Kachel gelöscht');
            refreshPreview();
        } else {
            showToast('error', result.error || 'Löschen fehlgeschlagen');
        }
    } catch (error) {
        console.error('Delete error:', error);
        showToast('error', 'Netzwerkfehler beim Löschen');
    }
}
