/**
 * Editor JavaScript - Info-Hub Visual Editor Logic
 * 
 * Modules:
 * - TileManager: Tile CRUD operations
 * - ModalManager: Modal handling
 * - ToastManager: Notifications
 * - FileManager: File uploads and browser
 */

// ===== Configuration =====
const API_URL = window.CONFIG?.apiUrl || 'api/endpoints.php';
const CSRF_TOKEN = window.CONFIG?.csrfToken || '';

// ===== Helper: API Request with CSRF =====
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

// ===== State =====
let tiles = window.CONFIG?.tiles || [];
let tileTypes = window.CONFIG?.tileTypes || {};
let settings = window.CONFIG?.settings || {};
let currentEditTile = null;
let fileBrowserCallback = null;
let currentFileType = 'images';
let previewWindow = null;

// ===== Initialization =====
document.addEventListener('DOMContentLoaded', () => {
    renderTiles();
    startSessionTimer();
    initKeyboardShortcuts();
    applyPublishButtonStyle();
    checkPermissionsOnLoad();  // Permission-Check beim Load
});

// ===== Keyboard Shortcuts =====
function initKeyboardShortcuts() {
    document.addEventListener('keydown', handleKeyboardShortcut);
}

function handleKeyboardShortcut(e) {
    // Ignorieren wenn in Input/Textarea
    const activeEl = document.activeElement;
    const isTyping = activeEl.tagName === 'INPUT' || 
                     activeEl.tagName === 'TEXTAREA' || 
                     activeEl.tagName === 'SELECT' ||
                     activeEl.isContentEditable;
    
    // Prüfen welche Modals offen sind
    const tileModalOpen = document.getElementById('tileModal')?.classList.contains('active');
    const settingsModalOpen = document.getElementById('settingsModal')?.classList.contains('active');
    const fileBrowserOpen = document.getElementById('fileBrowserModal')?.classList.contains('active');
    const sessionDialogOpen = document.querySelector('.session-dialog-overlay') !== null;
    const keyboardHelpOpen = document.getElementById('keyboardHelpModal') !== null;
    const anyModalOpen = tileModalOpen || settingsModalOpen || fileBrowserOpen || sessionDialogOpen || keyboardHelpOpen;
    
    // === ESC: Modals schließen ===
    if (e.key === 'Escape') {
        if (keyboardHelpOpen) {
            document.getElementById('keyboardHelpModal')?.remove();
            return;
        }
        if (sessionDialogOpen) {
            // Session-Dialog: ESC = Eingeloggt bleiben
            continueSession();
            return;
        }
        if (fileBrowserOpen) {
            closeFileBrowser();
            return;
        }
        if (tileModalOpen) {
            closeTileModal();
            return;
        }
        if (settingsModalOpen) {
            closeSettingsModal();
            return;
        }
    }
    
    // === ENTER: In Modals bestätigen ===
    if (e.key === 'Enter' && !isTyping) {
        if (sessionDialogOpen) {
            e.preventDefault();
            continueSession();
            return;
        }
        // Hinweis: Forms haben eigene Enter-Handling via submit
    }
    
    // === Globale Shortcuts (nur wenn kein Modal offen und nicht am Tippen) ===
    if (!anyModalOpen && !isTyping) {
        switch (e.key.toLowerCase()) {
            case 'n':
                e.preventDefault();
                openTileModal();
                break;
            case 'p':
                e.preventDefault();
                openPreview();
                break;
            case 's':
                e.preventDefault();
                openSettingsModal();
                break;
            case 'v':
                e.preventDefault();
                publishSite();
                break;
            case '?':
                e.preventDefault();
                showKeyboardHelp();
                break;
        }
    }
    
    // === Ctrl+S: Speichern (überall) ===
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        if (tileModalOpen) {
            document.getElementById('tileForm')?.requestSubmit();
        } else if (settingsModalOpen) {
            document.getElementById('settingsForm')?.requestSubmit();
        } else {
            // Keine Änderungen - Toast anzeigen
            showToast('info', 'Alle Änderungen werden automatisch gespeichert');
        }
    }
}

// Keyboard-Hilfe anzeigen
function showKeyboardHelp() {
    const existingHelp = document.getElementById('keyboardHelpModal');
    if (existingHelp) {
        existingHelp.remove();
        return;
    }
    
    const modal = document.createElement('div');
    modal.id = 'keyboardHelpModal';
    modal.className = 'modal active';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h2>⌨️ Tastenkürzel</h2>
                <button type="button" class="modal-close" onclick="this.closest('.modal').remove()">×</button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr><td style="padding: 8px 0;"><kbd>N</kbd></td><td>Neue Kachel</td></tr>
                    <tr><td style="padding: 8px 0;"><kbd>P</kbd></td><td>Vorschau öffnen</td></tr>
                    <tr><td style="padding: 8px 0;"><kbd>S</kbd></td><td>Einstellungen</td></tr>
                    <tr><td style="padding: 8px 0;"><kbd>V</kbd></td><td>Veröffentlichen</td></tr>
                    <tr><td style="padding: 8px 0;"><kbd>?</kbd></td><td>Diese Hilfe</td></tr>
                    <tr><td colspan="2" style="padding: 16px 0 8px; border-top: 1px solid #eee;"><strong>In Dialogen:</strong></td></tr>
                    <tr><td style="padding: 8px 0;"><kbd>ESC</kbd></td><td>Schließen</td></tr>
                    <tr><td style="padding: 8px 0;"><kbd>Ctrl+S</kbd></td><td>Speichern</td></tr>
                    <tr><td style="padding: 8px 0;"><kbd>Enter</kbd></td><td>Bestätigen</td></tr>
                </table>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Klick außerhalb schließt
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.remove();
    });
}

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

// Berechnet den aktuellen Visibility-Status einer Tile
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

// Visibility umschalten
async function toggleVisibility(tileId) {
    const tile = tiles.find(t => t.id === tileId);
    if (!tile) return;
    
    // Toggle: undefined/true -> false, false -> true
    const newVisibility = tile.visible === false ? true : false;
    await updateTileProperty(tileId, 'visible', newVisibility);
    
    const status = newVisibility ? 'sichtbar' : 'versteckt';
    showToast('info', `Kachel ist jetzt ${status}`);
}

// Visibility Schedule Dropdown anzeigen
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

// Visibility Schedule aktualisieren
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

// Einzelnes Schedule-Feld löschen
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

// Alle Schedule-Einstellungen löschen
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

// Preset-Zeiträume setzen
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

// ===== Tile Modal =====
function openTileModal(tileId = null) {
    currentEditTile = tileId ? tiles.find(t => t.id === tileId) : null;
    
    const modal = document.getElementById('tileModal');
    const title = document.getElementById('tileModalTitle');
    const form = document.getElementById('tileForm');
    
    title.textContent = currentEditTile ? 'Kachel bearbeiten' : 'Neue Kachel';
    form.reset();
    
    if (currentEditTile) {
        document.getElementById('tileId').value = currentEditTile.id;
        document.getElementById('tileType').value = currentEditTile.type;
        document.getElementById('tilePosition').value = currentEditTile.position;
        document.getElementById('tileSize').value = currentEditTile.size || 'medium';
        document.getElementById('tileStyle').value = currentEditTile.style || 'card';
        document.getElementById('tileColorScheme').value = currentEditTile.colorScheme || 'default';
        
        updateTileFields();
        
        // Felder mit Daten füllen
        setTimeout(() => {
            if (currentEditTile.data) {
                Object.entries(currentEditTile.data).forEach(([key, value]) => {
                    const input = document.querySelector(`[name="data[${key}]"]`);
                    if (input) {
                        if (input.type === 'checkbox') {
                            input.checked = value;
                        } else {
                            input.value = value;
                        }
                    }
                });
            }
        }, 50);
    } else {
        document.getElementById('tileId').value = '';
        document.getElementById('tileType').value = '';
        document.getElementById('tileColorScheme').value = 'default';
        document.getElementById('tileFields').innerHTML = '<p class="hint">Wähle zuerst einen Typ aus.</p>';
        
        // Nächste Position berechnen
        const maxPos = tiles.reduce((max, t) => Math.max(max, t.position || 0), 0);
        document.getElementById('tilePosition').value = maxPos + 10;
    }
    
    // Farboptionen basierend auf Stil aktualisieren
    updateColorSchemeOptions();
    
    modal.classList.add('active');
}

function closeTileModal() {
    document.getElementById('tileModal').classList.remove('active');
    currentEditTile = null;
}

function updateColorSchemeOptions() {
    const style = document.getElementById('tileStyle').value;
    const colorSelect = document.getElementById('tileColorScheme');
    const whiteOption = colorSelect.querySelector('option[value="white"]');
    
    if (style === 'card') {
        // Bei Card-Mode: Weiß-Option anzeigen
        if (whiteOption) whiteOption.style.display = '';
    } else {
        // Bei Flat-Mode: Weiß-Option verstecken
        if (whiteOption) whiteOption.style.display = 'none';
        // Falls Weiß ausgewählt war, auf default zurücksetzen
        if (colorSelect.value === 'white') {
            colorSelect.value = 'default';
        }
    }
}

function updateTileFields() {
    const type = document.getElementById('tileType').value;
    const container = document.getElementById('tileFields');
    
    if (!type || !tileTypes[type]) {
        container.innerHTML = '<p class="hint">Wähle zuerst einen Typ aus.</p>';
        return;
    }
    
    const fields = tileTypes[type].fields || [];
    const typeInfo = tileTypes[type];
    
    let html = '';
    let i = 0;
    let processedFields = new Set();
    
    while (i < fields.length) {
        const field = fields[i];
        
        // Schon verarbeitet? Überspringen
        if (processedFields.has(field)) {
            i++;
            continue;
        }
        
        // Kombiniere title + showTitle in einer Zeile
        if (field === 'title' && fields.includes('showTitle')) {
            html += renderInlineFieldWithCheckbox('title', 'showTitle');
            processedFields.add('title');
            processedFields.add('showTitle');
            i++;
        }
        // Kombiniere caption + lightbox
        else if (field === 'caption' && fields.includes('lightbox')) {
            html += renderInlineFieldWithCheckbox('caption', 'lightbox');
            processedFields.add('caption');
            processedFields.add('lightbox');
            i++;
        }
        // Kombiniere link + external für ImageTile
        else if (field === 'link' && fields.includes('external')) {
            html += renderInlineFieldWithCheckbox('link', 'external');
            processedFields.add('link');
            processedFields.add('external');
            i++;
        }
        // Kombiniere url + external für LinkTile
        else if (field === 'url' && fields.includes('external') && type === 'link') {
            html += renderInlineFieldWithCheckbox('url', 'external');
            processedFields.add('url');
            processedFields.add('external');
            i++;
        }
        // Akkordeon-Sections spezielle Behandlung
        else if (field.startsWith('section1_heading') && type === 'accordion') {
            html += renderAccordionSections();
            // Alle section-Felder als verarbeitet markieren
            for (let s = 1; s <= 10; s++) {
                processedFields.add(`section${s}_heading`);
                processedFields.add(`section${s}_content`);
            }
            i++;
        }
        // Section-Felder überspringen (werden von renderAccordionSections behandelt)
        else if (field.match(/^section\d+_(heading|content)$/) && type === 'accordion') {
            processedFields.add(field);
            i++;
        }
        // Normale Felder rendern (wenn nicht schon verarbeitet)
        else if (!processedFields.has(field)) {
            html += renderField(field, typeInfo);
            processedFields.add(field);
            i++;
        }
        else {
            i++;
        }
    }
    
    container.innerHTML = html || '<p class="hint">Dieser Typ hat keine zusätzlichen Felder.</p>';
    
    // Accordion-Sections initialisieren
    if (type === 'accordion') {
        initAccordionEditor();
    }
}

// ===== Akkordeon-Editor Funktionen =====

function renderAccordionSections() {
    let html = '<div class="accordion-editor" id="accordionEditor">';
    html += '<label class="form-label">Bereiche:</label>';
    html += '<div class="accordion-sections" id="accordionSections">';
    
    // 10 Sections rendern (erste sichtbar, Rest hidden)
    for (let i = 1; i <= 10; i++) {
        const hidden = i > 1 ? ' hidden' : '';
        const required = i === 1 ? 'required' : '';
        
        html += `
            <div class="accordion-section${hidden}" id="accordionSection${i}" data-section="${i}">
                <div class="accordion-section-header">
                    <span class="accordion-section-number">Bereich ${i}</span>
                    <div class="accordion-section-actions">
                        <button type="button" class="btn btn-small btn-icon" onclick="moveAccordionSection(${i}, -1)" title="Nach oben" data-move="up">⬆️</button>
                        <button type="button" class="btn btn-small btn-icon" onclick="moveAccordionSection(${i}, 1)" title="Nach unten" data-move="down">⬇️</button>
                        ${i > 1 ? `<button type="button" class="btn btn-small btn-danger" onclick="removeAccordionSection(${i})" title="Bereich entfernen">🗑️</button>` : ''}
                    </div>
                </div>
                <div class="form-row-compact">
                    <label for="data_section${i}_heading">Überschrift${i === 1 ? ' *' : ''}:</label>
                    <input type="text" name="data[section${i}_heading]" id="data_section${i}_heading" 
                           placeholder="Überschrift..." ${required}>
                </div>
                <div class="form-row-compact">
                    <label for="data_section${i}_content">Inhalt${i === 1 ? ' *' : ''}:</label>
                    <textarea name="data[section${i}_content]" id="data_section${i}_content" 
                              placeholder="Inhalt..." rows="3" ${required}></textarea>
                </div>
            </div>
        `;
    }
    
    html += '</div>'; // accordion-sections
    
    // Add-Button
    html += `
        <button type="button" class="btn btn-secondary accordion-add-btn" id="addAccordionSectionBtn" onclick="addAccordionSection()">
            ➕ Weiteren Bereich hinzufügen
        </button>
    `;
    
    html += '</div>'; // accordion-editor
    
    return html;
}

function initAccordionEditor() {
    // Sichtbarkeit basierend auf gefüllten Daten aktualisieren
    setTimeout(() => {
        let lastFilledSection = 1;
        
        for (let i = 1; i <= 10; i++) {
            const heading = document.getElementById(`data_section${i}_heading`);
            const content = document.getElementById(`data_section${i}_content`);
            
            if (heading && content && (heading.value.trim() || content.value.trim())) {
                lastFilledSection = i;
            }
        }
        
        // Alle gefüllten Sections + 1 leere anzeigen
        for (let i = 1; i <= Math.min(lastFilledSection + 1, 10); i++) {
            showAccordionSection(i);
        }
        
        updateAddButtonVisibility();
        updateMoveButtonStates();
        updateFullRowHint();
    }, 100);
}

function showAccordionSection(index) {
    const section = document.getElementById(`accordionSection${index}`);
    if (section) {
        section.classList.remove('hidden');
    }
}

function addAccordionSection() {
    // Finde erste versteckte Section
    for (let i = 2; i <= 10; i++) {
        const section = document.getElementById(`accordionSection${i}`);
        if (section && section.classList.contains('hidden')) {
            section.classList.remove('hidden');
            section.querySelector('input')?.focus();
            updateAddButtonVisibility();
            updateMoveButtonStates();
            return;
        }
    }
}

function removeAccordionSection(index) {
    const section = document.getElementById(`accordionSection${index}`);
    if (!section) return;
    
    // Felder leeren
    const heading = document.getElementById(`data_section${index}_heading`);
    const content = document.getElementById(`data_section${index}_content`);
    if (heading) heading.value = '';
    if (content) content.value = '';
    
    // Verstecken
    section.classList.add('hidden');
    
    updateAddButtonVisibility();
    updateMoveButtonStates();
}

function moveAccordionSection(index, direction) {
    // Sichtbare Sections ermitteln
    const visibleSections = getVisibleAccordionSections();
    const currentPos = visibleSections.indexOf(index);
    
    if (currentPos === -1) return;
    
    const targetPos = currentPos + direction;
    if (targetPos < 0 || targetPos >= visibleSections.length) return;
    
    const targetIndex = visibleSections[targetPos];
    
    // Werte tauschen
    swapAccordionSectionValues(index, targetIndex);
    
    // Button-States aktualisieren
    updateMoveButtonStates();
}

function swapAccordionSectionValues(indexA, indexB) {
    const headingA = document.getElementById(`data_section${indexA}_heading`);
    const contentA = document.getElementById(`data_section${indexA}_content`);
    const headingB = document.getElementById(`data_section${indexB}_heading`);
    const contentB = document.getElementById(`data_section${indexB}_content`);
    
    if (!headingA || !contentA || !headingB || !contentB) return;
    
    // Temporär speichern
    const tempHeading = headingA.value;
    const tempContent = contentA.value;
    
    // Tauschen
    headingA.value = headingB.value;
    contentA.value = contentB.value;
    headingB.value = tempHeading;
    contentB.value = tempContent;
}

function getVisibleAccordionSections() {
    const visible = [];
    for (let i = 1; i <= 10; i++) {
        const section = document.getElementById(`accordionSection${i}`);
        if (section && !section.classList.contains('hidden')) {
            visible.push(i);
        }
    }
    return visible;
}

function updateMoveButtonStates() {
    const visibleSections = getVisibleAccordionSections();
    
    for (let i = 1; i <= 10; i++) {
        const section = document.getElementById(`accordionSection${i}`);
        if (!section) continue;
        
        const upBtn = section.querySelector('[data-move="up"]');
        const downBtn = section.querySelector('[data-move="down"]');
        
        if (!upBtn || !downBtn) continue;
        
        const posInVisible = visibleSections.indexOf(i);
        const isFirst = posInVisible === 0;
        const isLast = posInVisible === visibleSections.length - 1;
        
        // Disable/Enable Buttons
        upBtn.disabled = isFirst;
        downBtn.disabled = isLast;
        
        // Visuelles Feedback
        upBtn.style.opacity = isFirst ? '0.3' : '1';
        downBtn.style.opacity = isLast ? '0.3' : '1';
    }
}

function updateAddButtonVisibility() {
    const addBtn = document.getElementById('addAccordionSectionBtn');
    if (!addBtn) return;
    
    // Prüfen ob noch versteckte Sections vorhanden
    let hasHidden = false;
    for (let i = 2; i <= 10; i++) {
        const section = document.getElementById(`accordionSection${i}`);
        if (section && section.classList.contains('hidden')) {
            hasHidden = true;
            break;
        }
    }
    
    addBtn.style.display = hasHidden ? '' : 'none';
}

function updateFullRowHint() {
    const singleOpenCheckbox = document.getElementById('data_singleOpen');
    const fullRowCheckbox = document.getElementById('data_fullRow');
    
    if (!singleOpenCheckbox || !fullRowCheckbox) return;
    
    // Hinweis anzeigen wenn singleOpen=false und fullRow=false
    const showHint = !singleOpenCheckbox.checked && !fullRowCheckbox.checked;
    
    let hint = document.getElementById('fullRowHint');
    if (showHint && !hint) {
        hint = document.createElement('div');
        hint.id = 'fullRowHint';
        hint.className = 'field-hint warning';
        hint.textContent = '⚠️ Bei mehreren offenen Bereichen wird "Volle Zeile im Grid" empfohlen.';
        fullRowCheckbox.parentElement.appendChild(hint);
    } else if (!showHint && hint) {
        hint.remove();
    }
}

// ===== Ende Akkordeon-Editor Funktionen =====

// Akkordeon-Funktionen global verfügbar machen (für onclick-Handler)
window.addAccordionSection = addAccordionSection;
window.removeAccordionSection = removeAccordionSection;
window.moveAccordionSection = moveAccordionSection;

// Feld mit Checkbox in einer Zeile
function renderInlineFieldWithCheckbox(fieldName, checkboxName) {
    const fieldConfigs = getFieldConfigs();
    const fieldConfig = fieldConfigs[fieldName] || { type: 'text', label: fieldName };
    const checkboxConfig = fieldConfigs[checkboxName] || { label: checkboxName, default: false };
    
    const required = fieldConfig.required ? 'required' : '';
    const checked = checkboxConfig.default ? 'checked' : '';
    
    return `
        <div class="form-field-inline">
            <label for="data_${fieldName}">${fieldConfig.label}${fieldConfig.required ? ' *' : ''}:</label>
            <input type="${fieldConfig.type === 'url' ? 'url' : 'text'}" name="data[${fieldName}]" id="data_${fieldName}" 
                   ${required} placeholder="${fieldConfig.placeholder || ''}" value="${fieldConfig.default || ''}">
            <label class="checkbox-label">
                <input type="checkbox" name="data[${checkboxName}]" id="data_${checkboxName}" ${checked}>
                ${checkboxConfig.label}
            </label>
        </div>
    `;
}

function getFieldConfigs() {
    return {
        title: { type: 'text', label: 'Titel', required: true },
        showTitle: { type: 'checkbox', label: 'anzeigen', required: false, default: true },
        description: { type: 'textarea', label: 'Beschreibung', required: false },
        image: { type: 'image', label: 'Bild', required: true },
        file: { type: 'file', label: 'Datei', required: true },
        url: { type: 'url', label: 'URL', required: true },
        link: { type: 'url', label: 'Link', required: false, placeholder: 'https://...' },
        linkText: { type: 'text', label: 'Link-Text', required: false, default: 'Mehr erfahren' },
        buttonText: { type: 'text', label: 'Button-Text', required: false, default: 'Download' },
        caption: { type: 'text', label: 'Untertitel', required: false },
        lightbox: { type: 'checkbox', label: 'Lightbox', required: false, default: true },
        external: { type: 'checkbox', label: 'neuer Tab', required: false, default: true },
        name: { type: 'text', label: 'Name', required: true },
        email: { type: 'email', label: 'E-Mail', required: false },
        phone: { type: 'text', label: 'Telefon', required: false },
        displayMode: { 
            type: 'select', 
            label: 'Anzeigemodus', 
            required: false, 
            default: 'inline',
            options: {
                'inline': 'Inline (direkt eingebettet)',
                'modal': 'Modal (öffnet bei Klick)'
            }
        },
        aspectRatio: { 
            type: 'select', 
            label: 'Seitenverhältnis', 
            required: false, 
            default: '16:9',
            options: {
                '16:9': '16:9 (Breitbild)',
                '4:3': '4:3 (Standard)',
                '1:1': '1:1 (Quadrat)',
                'custom': 'Benutzerdefinierte Höhe'
            }
        },
        customHeight: { 
            type: 'number', 
            label: 'Höhe in Pixel', 
            required: false, 
            default: 500,
            placeholder: '500',
            hint: 'Nur bei benutzerdefinierter Höhe'
        },
        // Countdown-Felder
        targetDate: {
            type: 'date',
            label: 'Zieldatum',
            required: true
        },
        targetTime: {
            type: 'time',
            label: 'Uhrzeit',
            required: false,
            default: '00:00'
        },
        countMode: {
            type: 'select',
            label: 'Anzeigemodus',
            required: false,
            default: 'dynamic',
            options: {
                'dynamic': 'Dynamisch (passt sich an)',
                'days': 'Tage (noch X Tage)',
                'hours': 'Stunden (noch X Stunden)',
                'timer': 'Timer (DD:HH:MM:SS)'
            }
        },
        expiredText: {
            type: 'text',
            label: 'Text nach Ablauf',
            required: false,
            default: 'Abgelaufen',
            placeholder: 'z.B. Jetzt anmelden!'
        },
        hideOnExpiry: {
            type: 'checkbox',
            label: 'Nach Ablauf ausblenden',
            required: false,
            default: false
        },
        // Quote-Felder
        quote: {
            type: 'textarea',
            label: 'Zitat / Bibelvers',
            required: true,
            placeholder: 'Denn also hat Gott die Welt geliebt...'
        },
        source: {
            type: 'text',
            label: 'Quelle',
            required: false,
            placeholder: 'z.B. "Johannes 3,16" oder "Martin Luther"'
        },
        // Separator-Felder
        height: {
            type: 'number',
            label: 'Höhe (px)',
            required: false,
            default: 40,
            placeholder: '40'
        },
        showLine: {
            type: 'checkbox',
            label: 'Linie anzeigen',
            required: false,
            default: false
        },
        lineWidth: {
            type: 'select',
            label: 'Linienbreite',
            required: false,
            default: 'medium',
            options: {
                'small': 'Kurz (30%)',
                'medium': 'Mittel (60%)',
                'large': 'Voll (100%)'
            }
        },
        lineStyle: {
            type: 'select',
            label: 'Linienstil',
            required: false,
            default: 'solid',
            options: {
                'solid': 'Durchgezogen',
                'dashed': 'Gestrichelt',
                'dotted': 'Gepunktet'
            }
        },
        // Link-Tile Felder
        showDomain: {
            type: 'checkbox',
            label: 'Link-Vorschau anzeigen',
            required: false,
            default: true
        },
        // Accordion-Felder
        singleOpen: {
            type: 'checkbox',
            label: 'Nur ein Bereich gleichzeitig offen',
            required: false,
            default: true
        },
        autoScroll: {
            type: 'select',
            label: 'Auto-Scroll zum geöffneten Bereich',
            required: false,
            default: 'mobile',
            options: {
                'always': 'Immer',
                'mobile': 'Nur auf Mobilgeräten',
                'never': 'Nie'
            }
        },
        defaultOpen: {
            type: 'select',
            label: 'Initial geöffneter Bereich',
            required: false,
            default: '-1',
            options: {
                '-1': 'Alle geschlossen',
                '0': 'Bereich 1',
                '1': 'Bereich 2',
                '2': 'Bereich 3',
                '3': 'Bereich 4',
                '4': 'Bereich 5'
            }
        },
        fullRow: {
            type: 'checkbox',
            label: 'Volle Zeile im Grid',
            required: false,
            default: false,
            hint: 'Empfohlen wenn mehrere Bereiche gleichzeitig offen sein können'
        }
    };
}

function renderField(fieldName, typeInfo) {
    const fieldConfigs = getFieldConfigs();
    
    const config = fieldConfigs[fieldName] || { type: 'text', label: fieldName, required: false };
    const required = config.required ? 'required' : '';
    const defaultValue = config.default || '';
    const reqMark = config.required ? ' *' : '';
    
    switch (config.type) {
        case 'textarea':
            return `
                <div class="form-row-compact">
                    <label for="data_${fieldName}">${config.label}${reqMark}:</label>
                    <textarea name="data[${fieldName}]" id="data_${fieldName}" ${required}
                              placeholder="${config.placeholder || ''}">${defaultValue}</textarea>
                </div>
            `;
            
        case 'image':
            return `
                <div class="form-row-compact">
                    <label>${config.label}${reqMark}:</label>
                    <div class="input-with-button">
                        <input type="text" name="data[${fieldName}]" id="data_${fieldName}" 
                               ${required} readonly placeholder="Bild auswählen...">
                        <button type="button" class="btn btn-small" 
                                onclick="openFileBrowser('images', 'data_${fieldName}')">
                            Auswählen
                        </button>
                    </div>
                </div>
                <div class="image-preview" id="preview_${fieldName}"></div>
            `;
            
        case 'file':
            return `
                <div class="form-row-compact">
                    <label>${config.label}${reqMark}:</label>
                    <div class="input-with-button">
                        <input type="text" name="data[${fieldName}]" id="data_${fieldName}" 
                               ${required} readonly placeholder="Datei auswählen...">
                        <button type="button" class="btn btn-small" 
                                onclick="openFileBrowser('downloads', 'data_${fieldName}')">
                            Auswählen
                        </button>
                    </div>
                </div>
            `;
            
        case 'checkbox':
            const checked = defaultValue ? 'checked' : '';
            return `
                <div class="form-row-compact checkbox-only">
                    <label></label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="data[${fieldName}]" id="data_${fieldName}" ${checked}>
                        ${config.label}
                    </label>
                </div>
            `;
            
        case 'url':
            return `
                <div class="form-row-compact">
                    <label for="data_${fieldName}">${config.label}${reqMark}:</label>
                    <input type="url" name="data[${fieldName}]" id="data_${fieldName}" 
                           ${required} placeholder="https://example.com" value="${defaultValue}">
                </div>
            `;
            
        case 'select':
            const options = Object.entries(config.options || {})
                .map(([value, label]) => `<option value="${value}" ${value === defaultValue ? 'selected' : ''}>${label}</option>`)
                .join('');
            return `
                <div class="form-row-compact">
                    <label for="data_${fieldName}">${config.label}${reqMark}:</label>
                    <select name="data[${fieldName}]" id="data_${fieldName}" ${required}>
                        ${options}
                    </select>
                </div>
                ${config.hint ? `<small class="hint">${config.hint}</small>` : ''}
            `;
            
        case 'number':
            return `
                <div class="form-row-compact">
                    <label for="data_${fieldName}">${config.label}${reqMark}:</label>
                    <input type="number" name="data[${fieldName}]" id="data_${fieldName}" 
                           ${required} placeholder="${config.placeholder || ''}" value="${defaultValue}"
                           min="${config.min || ''}" max="${config.max || ''}">
                </div>
                ${config.hint ? `<small class="hint">${config.hint}</small>` : ''}
            `;
            
        case 'date':
            return `
                <div class="form-row-compact">
                    <label for="data_${fieldName}">${config.label}${reqMark}:</label>
                    <input type="date" name="data[${fieldName}]" id="data_${fieldName}" 
                           ${required} value="${defaultValue}">
                </div>
                ${config.hint ? `<small class="hint">${config.hint}</small>` : ''}
            `;
            
        case 'time':
            return `
                <div class="form-row-compact">
                    <label for="data_${fieldName}">${config.label}${reqMark}:</label>
                    <input type="time" name="data[${fieldName}]" id="data_${fieldName}" 
                           ${required} value="${defaultValue}">
                </div>
                ${config.hint ? `<small class="hint">${config.hint}</small>` : ''}
            `;
            
        default:
            return `
                <div class="form-row-compact">
                    <label for="data_${fieldName}">${config.label}${reqMark}:</label>
                    <input type="${config.type}" name="data[${fieldName}]" id="data_${fieldName}" 
                           ${required} placeholder="${config.placeholder || ''}" value="${defaultValue}">
                </div>
            `;
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
        visible: existingTile?.visible ?? true,  // Bestehendes visible-Feld behalten, neue Tiles sind sichtbar
        visibilitySchedule: existingTile?.visibilitySchedule || undefined,  // Zeitsteuerung beibehalten
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
        visible: originalTile.visible ?? true,  // Sichtbarkeit mitkopieren
        // visibilitySchedule bewusst NICHT kopieren - Duplikat startet ohne Zeitsteuerung
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

// ===== Settings Modal =====
function openSettingsModal() {
    document.getElementById('settingsModal').classList.add('active');
}

function closeSettingsModal() {
    document.getElementById('settingsModal').classList.remove('active');
}

async function saveSettings(event) {
    event.preventDefault();
    
    const form = document.getElementById('settingsForm');
    const formData = new FormData(form);
    
    // headerImage aus dem Hidden-Field lesen (nicht aus dem File-Input!)
    const headerImagePath = document.getElementById('headerImagePath')?.value || null;
    
    const newSettings = {
        site: {
            title: formData.get('title') || '',
            headerImage: headerImagePath || null,
            headerFocusPoint: formData.get('headerFocusPoint') || 'center center',
            footerText: formData.get('footerText') || ''
        },
        theme: {
            backgroundColor: formData.get('backgroundColor'),
            accentColor: formData.get('accentColor'),
            accentColor2: formData.get('accentColor2'),
            accentColor3: formData.get('accentColor3')
        }
    };
    
    try {
        const result = await apiPost('save_settings', { settings: newSettings });
        
        if (result.success) {
            settings = result.settings;
            closeSettingsModal();
            showToast('success', 'Einstellungen gespeichert!');
            
            // Site-Name im Header aktualisieren
            document.querySelector('.site-name').textContent = settings.site?.title || '';
            
            // Publish-Button-Farbe aktualisieren
            applyPublishButtonStyle();
            
            refreshPreview();
        } else {
            showToast('error', result.error || 'Speichern fehlgeschlagen');
        }
    } catch (error) {
        console.error('Settings save error:', error);
        showToast('error', 'Netzwerkfehler beim Speichern');
    }
}

async function uploadHeaderImage(input) {
    if (!input.files[0]) return;
    
    const formData = new FormData();
    formData.append('action', 'upload_header');
    formData.append('file', input.files[0]);
    
    showToast('info', 'Header-Bild wird hochgeladen...');
    
    try {
        const result = await apiPostFormData(formData);
        
        if (result.success) {
            // Pfad im Hidden-Field speichern
            document.getElementById('headerImagePath').value = result.path;
            
            // Preview aktualisieren
            document.getElementById('headerPreview').innerHTML = 
                `<img src="${result.path}" alt="Header Preview">
                 <button type="button" class="btn btn-small" onclick="removeHeaderImage()">Entfernen</button>`;
            
            showToast('success', 'Header-Bild hochgeladen');
            hideDiagnostics();
        } else {
            // Fehlerbehandlung mit Diagnostics
            console.error('Upload failed:', result);
            showToast('error', result.error || 'Upload fehlgeschlagen');
            
            // Wenn Details vorhanden sind (Permission-Probleme), zeige Diagnose
            if (result.details || (result.error && (
                result.error.includes('Schreibrechte') || 
                result.error.includes('Berechtigungen') ||
                result.error.includes('konnte nicht')
            ))) {
                showUploadDiagnostics(result);
            }
        }
    } catch (error) {
        console.error('Upload error:', error);
        showToast('error', 'Netzwerkfehler beim Upload');
    }
}

function removeHeaderImage() {
    document.getElementById('headerImagePath').value = '';
    document.getElementById('headerImageFile').value = '';
    document.getElementById('headerPreview').innerHTML = '<span class="no-image">Kein Header-Bild</span>';
}

// ===== File Browser =====
function openFileBrowser(type, targetInputId) {
    currentFileType = type;
    fileBrowserCallback = targetInputId;
    
    document.getElementById('fileBrowserModal').classList.add('active');
    
    // Accept-Attribut für Upload setzen
    updateFileUploadUI(type);
    
    // Tabs aktualisieren
    document.querySelectorAll('.file-browser-tabs .tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.type === type);
    });
    
    // Dropzone initialisieren
    initDropzone();
    
    loadFiles(type);
}

// Dropzone initialisieren
function initDropzone() {
    const dropzone = document.getElementById('fileDropzone');
    const uploadInput = document.getElementById('fileBrowserUpload');
    
    if (!dropzone || dropzone.dataset.initialized) return;
    dropzone.dataset.initialized = 'true';
    
    // Klick auf Dropzone öffnet Dateiauswahl
    dropzone.addEventListener('click', () => uploadInput.click());
    
    // Drag & Drop Events
    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.add('dragover');
        // Default ist kopieren, nicht verschieben
        e.dataTransfer.dropEffect = 'copy';
    });
    
    dropzone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.remove('dragover');
    });
    
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileUpload(files[0]);
        }
    });
}

// Aktualisiert Upload-Input basierend auf Dateityp
function updateFileUploadUI(type) {
    const uploadInput = document.getElementById('fileBrowserUpload');
    const dropzoneIcon = document.querySelector('.dropzone-icon');
    
    if (!uploadInput) return;
    
    if (type === 'images') {
        uploadInput.accept = 'image/jpeg,image/png,image/gif,image/webp';
        if (dropzoneIcon) dropzoneIcon.textContent = '🖼️';
    } else {
        uploadInput.accept = '.pdf,.docx,.xlsx,.zip,.pptx,.txt';
        if (dropzoneIcon) dropzoneIcon.textContent = '📄';
    }
}

function closeFileBrowser() {
    document.getElementById('fileBrowserModal').classList.remove('active');
    fileBrowserCallback = null;
}

async function loadFiles(type) {
    currentFileType = type;
    
    // Tabs aktualisieren
    document.querySelectorAll('.file-browser-tabs .tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.type === type);
    });
    
    // Upload-Button aktualisieren
    updateFileUploadUI(type);
    
    const container = document.getElementById('fileList');
    container.innerHTML = '<div class="spinner"></div>';
    
    try {
        const response = await fetch(`${API_URL}?action=list_files&type=${type}`);
        const result = await response.json();
        
        if (result.success) {
            if (result.files.length === 0) {
                container.innerHTML = '<p class="hint">Keine Dateien vorhanden</p>';
            } else {
                container.innerHTML = result.files.map(file => renderFileItem(file, type)).join('');
            }
        } else {
            container.innerHTML = '<p class="hint">Fehler beim Laden</p>';
        }
    } catch (error) {
        console.error('Load files error:', error);
        container.innerHTML = '<p class="hint">Netzwerkfehler</p>';
    }
}

function renderFileItem(file, type) {
    if (type === 'images') {
        return `
            <div class="file-item" onclick="selectFile('${file.path}')">
                <img src="${file.path}" alt="${file.filename}">
                <div class="filename">${file.filename}</div>
            </div>
        `;
    } else {
        const ext = file.filename.split('.').pop().toLowerCase();
        const icon = getFileIcon(ext);
        return `
            <div class="file-item" onclick="selectFile('${file.path}')">
                <div class="file-item-icon">${icon}</div>
                <div class="filename">${file.filename}</div>
            </div>
        `;
    }
}

function getFileIcon(ext) {
    const icons = {
        'pdf': '📄',
        'docx': '📝', 'doc': '📝',
        'xlsx': '📊', 'xls': '📊',
        'pptx': '📽️', 'ppt': '📽️',
        'zip': '📦', 'rar': '📦',
        'txt': '📃'
    };
    return icons[ext] || '📎';
}

function selectFile(path) {
    if (fileBrowserCallback) {
        const input = document.getElementById(fileBrowserCallback);
        if (input) {
            input.value = path;
            
            // Für Bilder: Preview aktualisieren
            const previewId = 'preview_' + fileBrowserCallback.replace('data_', '');
            const preview = document.getElementById(previewId);
            if (preview && currentFileType === 'images') {
                preview.innerHTML = `<img src="${path}" alt="Preview">`;
            }
        }
    }
    
    closeFileBrowser();
}

async function uploadFile(input) {
    if (!input || !input.files || !input.files[0]) return;
    handleFileUpload(input.files[0]);
    // Input zurücksetzen für erneuten Upload
    input.value = '';
}

// Gemeinsame Upload-Logik für Input und Dropzone
async function handleFileUpload(file) {
    if (!file) return;
    
    // Dateivalidierung
    const isImage = currentFileType === 'images';
    const allowedImages = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const allowedDocs = ['.pdf', '.docx', '.xlsx', '.zip', '.pptx', '.txt'];
    
    if (isImage && !allowedImages.includes(file.type)) {
        showToast('error', 'Nur Bilder erlaubt (JPG, PNG, GIF, WebP)');
        return;
    }
    
    const ext = '.' + file.name.split('.').pop().toLowerCase();
    if (!isImage && !allowedDocs.includes(ext)) {
        showToast('error', 'Dateityp nicht erlaubt');
        return;
    }
    
    const action = isImage ? 'upload_image' : 'upload_download';
    
    const formData = new FormData();
    formData.append('action', action);
    formData.append('file', file);
    
    // Upload-Indikator
    const dropzone = document.getElementById('fileDropzone');
    if (dropzone) dropzone.classList.add('uploading');
    
    try {
        const result = await apiPostFormData(formData);
        
        if (result.success) {
            // Dateiliste neu laden und dann Datei auswählen
            await loadFiles(currentFileType);
            selectFile(result.path);
            showToast('success', 'Datei hochgeladen');
        } else {
            showToast('error', result.error || 'Upload fehlgeschlagen');
        }
    } catch (error) {
        console.error('Upload error:', error);
        showToast('error', 'Netzwerkfehler beim Upload');
    } finally {
        if (dropzone) dropzone.classList.remove('uploading');
    }
}

// ===== Publish & Preview =====

// Publish-Button mit Akzentfarbe und Kontrast-Text
function applyPublishButtonStyle() {
    const btn = document.getElementById('publishBtn');
    if (!btn) return;
    
    // Akzentfarbe aus Settings holen (accentColor = Seitentitel-Farbe)
    const accentColor = settings?.theme?.accentColor || '#667eea';
    btn.style.background = accentColor;
    btn.style.borderColor = accentColor;
    
    // Kontrast berechnen (YIQ-Formel)
    const hex = accentColor.replace('#', '');
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);
    const yiq = (r * 299 + g * 587 + b * 114) / 1000;
    
    btn.style.color = yiq >= 128 ? '#000000' : '#ffffff';
}

async function publishSite() {
    if (!confirm('Seite jetzt veröffentlichen? Die aktuelle Version wird überschrieben.')) return;
    
    const btn = document.getElementById('publishBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner"></span> Wird veröffentlicht...';
    btn.disabled = true;
    
    try {
        const result = await apiPost('generate');
        
        if (result.success) {
            showToast('success', `Seite veröffentlicht! (${result.tilesCount} Kacheln)`);
            refreshPreview();
        } else {
            showToast('error', result.message || 'Veröffentlichen fehlgeschlagen');
        }
    } catch (error) {
        console.error('Publish error:', error);
        showToast('error', 'Netzwerkfehler beim Veröffentlichen');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

function openPreview() {
    // Wenn Preview bereits offen, fokussieren und refreshen
    if (previewWindow && !previewWindow.closed) {
        previewWindow.focus();
        previewWindow.location.reload();
        showToast('info', 'Preview aktualisiert');
    } else {
        // Neues Preview-Fenster öffnen
        previewWindow = window.open(`${API_URL}?action=preview`, 'InfoHubPreview');
        
        if (previewWindow) {
            showToast('info', 'Preview geöffnet - wird automatisch bei Änderungen aktualisiert');
        }
    }
}

// Refresh Preview-Window falls offen
function refreshPreview() {
    if (previewWindow && !previewWindow.closed) {
        try {
            previewWindow.location.reload();
        } catch (e) {
            // Cross-origin oder Window geschlossen
            previewWindow = null;
        }
    }
}

// Logout
function logout() {
    if (confirm('Möchtest du dich abmelden?')) {
        window.location.href = 'login.php?logout=1';
    }
}

// ===== Toast Notifications =====
function showToast(type, message, duration = 4000) {
    const container = document.getElementById('toastContainer');
    
    const icons = {
        success: '✅',
        error: '❌',
        info: 'ℹ️'
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

// ===== Session Timer mit Auto-Extend =====
let sessionWarningShown = false;
let sessionDialogActive = false;
let lastActivity = Date.now();

// Globale Session-Konfiguration (wird in startSessionTimer gesetzt)
let sessionConfig = {
    timeout: 3600,      // Gesamt-Timeout in Sekunden
    warningBefore: 300  // Warnung X Sekunden vor Ablauf
};

function startSessionTimer() {
    const timerEl = document.getElementById('sessionTimeDisplay');
    
    // Timeout-Werte aus CONFIG (bereits in Sekunden!)
    sessionConfig.timeout = CONFIG?.sessionTimeout || 3600;      // Standard: 3600s = 60 Minuten
    sessionConfig.warningBefore = CONFIG?.sessionWarning || 300; // Standard: 300s = 5 Minuten vorher
    const warningAt = sessionConfig.timeout - sessionConfig.warningBefore;
    
    // Debug-Info ausgeben, wenn Debug-Modus aktiv
    if (CONFIG?.debugMode) {
            console.log('Session Timer gestartet:', {
            timeout: sessionConfig.timeout + 's',
            warningAt: warningAt + 's',
            warningBefore: sessionConfig.warningBefore + 's'
        });
    }

    // Activity Tracking - bei jeder Aktivität Session-Timer zurücksetzen
    const activityEvents = ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'];
    activityEvents.forEach(event => {
        document.addEventListener(event, () => {
            if (!sessionDialogActive) {
                lastActivity = Date.now();
                // Session via API verlängern (max alle 5 Minuten)
                extendSessionIfNeeded();
            }
        }, { passive: true });
    });
    
    setInterval(() => {
        // Prüfen ob kürzlich Aktivität war
        const inactiveSeconds = Math.floor((Date.now() - lastActivity) / 1000);
        
        // Warndialog anzeigen wenn Inaktivität den Warning-Threshold erreicht
        if (inactiveSeconds >= warningAt && !sessionDialogActive && !sessionWarningShown) {
            showSessionExpiryDialog();
            sessionWarningShown = true;
        }
        
        // Timer-Anzeige aktualisieren (basierend auf Inaktivität)
        const remainingFromInactivity = sessionConfig.timeout - inactiveSeconds;
        const minutes = Math.max(0, Math.floor(remainingFromInactivity / 60));
        const seconds = Math.max(0, remainingFromInactivity % 60);
        
        if (timerEl) {
            // Unter 5 Minuten: auch Sekunden anzeigen
            if (remainingFromInactivity <= 300 && remainingFromInactivity > 0) {
                timerEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            } else {
                timerEl.textContent = `${minutes}min`;
            }
        }
    }, 1000);
}

let lastExtendCall = 0;
async function extendSessionIfNeeded() {
    // Max alle 5 Minuten
    if (Date.now() - lastExtendCall < 300000) return;
    lastExtendCall = Date.now();
    
    try {
        await apiPost('extend_session');
        sessionWarningShown = false;
    } catch (e) {
        console.warn('Session extend failed:', e);
    }
}

function showSessionExpiryDialog() {
    sessionDialogActive = true;
    
    // Countdown = konfigurierte Warnzeit (warningBefore)
    let countdown = sessionConfig.warningBefore;
    
    // Formatierung für Anzeige
    const formatTime = (secs) => {
        const m = Math.floor(secs / 60);
        const s = secs % 60;
        return m > 0 ? `${m}:${s.toString().padStart(2, '0')}` : `${secs}`;
    };
    
    // Dialog erstellen
    const dialog = document.createElement('div');
    dialog.className = 'session-dialog-overlay';
    dialog.innerHTML = `
        <div class="session-dialog">
            <h3>⏰ Session läuft ab</h3>
            <p>Deine Session läuft ab in</p>
            <p> <strong id="sessionCountdown">${formatTime(countdown)}</strong></p>
            <p>Möchtest du eingeloggt bleiben?</p>
            <div class="session-dialog-actions">
                <button class="btn btn-secondary" onclick="forceLogout()">Ausloggen</button>
                <button class="btn btn-primary" onclick="continueSession()">Eingeloggt bleiben</button>
            </div>
        </div>
    `;
    document.body.appendChild(dialog);
    
    // Countdown
    const countdownEl = document.getElementById('sessionCountdown');
    const countdownInterval = setInterval(() => {
        countdown--;
        countdownEl.textContent = formatTime(countdown);
        
        if (countdown <= 0) {
            clearInterval(countdownInterval);
            forceLogout();
        }
    }, 1000);
    
    dialog.dataset.interval = countdownInterval;
}

function continueSession() {
    const dialog = document.querySelector('.session-dialog-overlay');
    if (dialog) {
        clearInterval(Number(dialog.dataset.interval));
        dialog.remove();
    }
    sessionDialogActive = false;
    sessionWarningShown = false;
    lastActivity = Date.now();
    extendSessionIfNeeded();
    showToast('success', 'Session verlängert');
}

function forceLogout() {
    window.location.href = 'login.php?expired=1';
}

// ===== DIAGNOSTICS & PERMISSION CHECKS =====

/**
 * Zeigt Upload-Diagnose Panel
 */
async function showUploadDiagnostics(result) {
    const infoPanel = document.getElementById('diagnosticsInfo');
    const msgEl = document.getElementById('diagnosticsMessage');
    const detailsEl = document.getElementById('diagnosticsDetails');
    
    if (!infoPanel) return;
    
    msgEl.textContent = result.error || 'Unbekannter Upload-Fehler';
    
    // Detailstext mit Befehlen anzeigen
    let html = '<p><strong>Das Problem:</strong> Der Webserver hat keine Schreibrechte auf den Upload-Ordnern.</p>';
    
    // API-spezifische Suggestion nutzen, falls vorhanden
    if (result.details && result.details.suggestion) {
        html += '<p><strong>Lösung (über SSH/Terminal):</strong></p>';
        html += '<code style="display: block; padding: 8px; background: #222; color: #0f0; font-family: monospace; border-radius: 4px;">';
        html += result.details.suggestion.replace(/\n/g, '<br>');
        html += '</code>';
    } else {
        // Fallback zu Standard-Befehlen
        html += '<p><strong>Lösung (über SSH/Terminal):</strong></p>';
        html += '<code style="display: block; padding: 8px; background: #222; color: #0f0; font-family: monospace; border-radius: 4px;">';
        html += 'chmod 777 backend/media/images backend/media/downloads backend/media/header<br>';
        html += 'chmod 777 backend/data backend/logs backend/archive';
        html += '</code>';
    }
    
    html += '<p><strong>Alternative (wenn Server-Admin verfügbar):</strong></p>';
    html += '<code style="display: block; padding: 8px; background: #222; color: #0f0; font-family: monospace; border-radius: 4px;">';
    html += 'chown -R www-data:www-data backend/<br>';
    html += 'chmod 755 backend/media backend/data backend/logs backend/archive';
    html += '</code>';
    
    detailsEl.innerHTML = html;
    infoPanel.style.display = 'block';
}

/**
 * Versteckt Diagnose-Panel
 */
function hideDiagnostics() {
    const infoPanel = document.getElementById('diagnosticsInfo');
    if (infoPanel) {
        infoPanel.style.display = 'none';
    }
}

/**
 * Prüft Schreibrechte beim Page-Load
 */
async function checkPermissionsOnLoad() {
    try {
        const result = await apiPost('check_permissions');
        console.log('Permission check result:', result);
        
        if (!result.success && result.permissions) {
            showUploadDiagnostics({
                error: result.permissions?.issues?.length 
                    ? `${result.permissions.issues.length} Verzeichnis(se) mit Problemen` 
                    : 'Schreibrechte-Problem erkannt'
            });
        }
    } catch (err) {
        // Stille Exception - nicht kritisch
        console.log('Permission check failed:', err);
    }
}

// ===== Utilities =====
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
