import V2State from './state.js';
import V2Api from './api-client.js';
import { V2Canvas } from './canvas.js';
import { showToast } from './toast.js';

/**
 * V2 Context Menu - Rechtsklickmenue fuer tile-bezogene Schnellaktionen
 *
 * Fokus: Sichtbarkeit und Zeitsteuerung ohne zusaetzliche Toolbar-Unruhe.
 */

const V2ContextMenu = (function() {
    'use strict';

    let _menu = null;
    let _currentTileId = null;
    let _lastPointerX = 0;
    let _lastPointerY = 0;
    let _scheduleWarningShown = false;

    function init() {
        ensureMenu();
        document.addEventListener('mousedown', handleOutsideClick);
        document.addEventListener('keydown', handleKeyDown);
        window.addEventListener('resize', hide);
        window.addEventListener('scroll', hide, true);
    }

    function ensureMenu() {
        if (_menu) return;

        _menu = document.createElement('div');
        _menu.id = 'v2ContextMenu';
        _menu.className = 'v2-context-menu';
        _menu.style.display = 'none';
        _menu.addEventListener('click', handleMenuClick);
        _menu.addEventListener('contextmenu', (event) => event.preventDefault());
        document.body.appendChild(_menu);
    }

    function isOpen() {
        return !!_menu && _menu.style.display !== 'none';
    }

    function showTileMenu(tileId, clientX, clientY) {
        ensureMenu();
        _currentTileId = tileId;
        _lastPointerX = clientX;
        _lastPointerY = clientY;
        renderRootMenu();
        showAndPosition();
    }

    function hide() {
        if (!_menu) return;
        _menu.style.display = 'none';
        _menu.style.visibility = 'hidden';
        _currentTileId = null;
    }

    function handleOutsideClick(event) {
        if (!isOpen()) return;
        if (event.target.closest('.v2-context-menu')) return;
        hide();
    }

    function handleKeyDown(event) {
        if (!isOpen()) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            hide();
        }
    }

    function handleMenuClick(event) {
        const actionButton = event.target.closest('[data-action]');
        if (!actionButton) return;

        event.preventDefault();
        event.stopPropagation();

        const action = actionButton.dataset.action;

        switch (action) {
            case 'toggle-visibility':
                void toggleVisibility();
                break;
            case 'edit-schedule':
                renderScheduleMenu();
                showAndPosition();
                break;
            case 'back':
                renderRootMenu();
                showAndPosition();
                break;
            case 'save-schedule':
                void saveSchedule();
                break;
            case 'clear-schedule':
                void clearSchedule();
                break;
            case 'clear-schedule-field':
                clearScheduleField(actionButton.dataset.field);
                break;
            case 'preset-week':
                setSchedulePreset('week');
                break;
            case 'preset-month':
                setSchedulePreset('month');
                break;
            case 'duplicate':
                void duplicateTile();
                break;
            case 'delete':
                void deleteTile();
                break;
        }
    }

    function renderRootMenu() {
        const tile = getCurrentTile();
        if (!tile) {
            hide();
            return;
        }

        const status = getVisibilityStatus(tile);
        const scheduleSummary = getScheduleSummary(tile.visibilitySchedule || {});
        const visibilityAction = tile.visible === false ? '👁️ Einblenden (wieder exportieren)' : '⛔ Ausblenden (nicht exportieren)';
        const scheduleAction = status.hasSchedule ? '🕐 Zeitsteuerung bearbeiten' : '🕐 Zeitsteuerung hinzufügen';

        _menu.innerHTML = `
            <div class="v2-context-status ${status.visualClass || ''}">
                <div class="v2-context-status-title">Sichtbarkeit</div>
                <div class="v2-context-status-text">${escapeHtml(status.longLabel)}</div>
                ${scheduleSummary ? `<div class="v2-context-meta">${escapeHtml(scheduleSummary)}</div>` : ''}
            </div>
            <div class="v2-context-menu-list">
                <button type="button" class="v2-context-menu-item" data-action="toggle-visibility">${visibilityAction}</button>
                <button type="button" class="v2-context-menu-item" data-action="edit-schedule">${scheduleAction}</button>
                <button type="button" class="v2-context-menu-item" data-action="duplicate">📋 Duplizieren</button>
                <button type="button" class="v2-context-menu-item v2-context-menu-item-danger" data-action="delete">🗑️ Löschen</button>
            </div>
        `;
    }

    function renderScheduleMenu() {
        const tile = getCurrentTile();
        if (!tile) {
            hide();
            return;
        }

        const schedule = tile.visibilitySchedule || {};
        const showFrom = schedule.showFrom ? schedule.showFrom.substring(0, 16) : '';
        const showUntil = schedule.showUntil ? schedule.showUntil.substring(0, 16) : '';

        _menu.innerHTML = `
            <div class="v2-context-menu-list">
                <button type="button" class="v2-context-menu-item v2-context-menu-item-back" data-action="back">← Zurück</button>
            </div>
            <div class="v2-context-schedule">
                <label for="v2ScheduleShowFrom">Einblenden ab</label>
                <div class="v2-context-schedule-row">
                    <input type="datetime-local" id="v2ScheduleShowFrom" value="${showFrom}">
                    <button type="button" class="v2-context-icon-btn" data-action="clear-schedule-field" data-field="showFrom" title="Feld leeren">×</button>
                </div>

                <label for="v2ScheduleShowUntil">Ausblenden ab</label>
                <div class="v2-context-schedule-row">
                    <input type="datetime-local" id="v2ScheduleShowUntil" value="${showUntil}">
                    <button type="button" class="v2-context-icon-btn" data-action="clear-schedule-field" data-field="showUntil" title="Feld leeren">×</button>
                </div>

                <div class="v2-context-preset-row">
                    <button type="button" class="v2-context-chip" data-action="preset-week">+1 Woche</button>
                    <button type="button" class="v2-context-chip" data-action="preset-month">+1 Monat</button>
                </div>

                <div class="v2-context-preset-row v2-context-preset-row-actions">
                    <button type="button" class="v2-context-chip v2-context-chip-muted" data-action="clear-schedule">Zeitplan löschen</button>
                    <button type="button" class="v2-context-chip v2-context-chip-primary" data-action="save-schedule">Speichern</button>
                </div>

                <div class="v2-context-hint">Zeitgesteuerte Inhalte bleiben im Quelltext sichtbar.</div>
            </div>
        `;
    }

    async function toggleVisibility() {
        const tile = getCurrentTile();
        if (!tile) return;

        const updatedTile = cloneTile(tile);
        updatedTile.visible = tile.visible === false ? true : false;

        const successMessage = updatedTile.visible === false
            ? 'Kachel wird nicht mehr exportiert'
            : 'Kachel ist wieder sichtbar';

        await persistTile(updatedTile, successMessage);
    }

    async function saveSchedule() {
        const tile = getCurrentTile();
        if (!tile) return;

        const showFrom = _menu.querySelector('#v2ScheduleShowFrom')?.value || null;
        const showUntil = _menu.querySelector('#v2ScheduleShowUntil')?.value || null;

        if (showFrom && showUntil && new Date(showFrom) >= new Date(showUntil)) {
            showToast('Einblende-Datum muss vor Ausblende-Datum liegen', 'warning');
            return;
        }

        const updatedTile = cloneTile(tile);
        const hadScheduleBefore = !!(tile.visibilitySchedule && (tile.visibilitySchedule.showFrom || tile.visibilitySchedule.showUntil));
        const hasScheduleNow = !!(showFrom || showUntil);

        if (hasScheduleNow) {
            updatedTile.visibilitySchedule = {};
            if (showFrom) updatedTile.visibilitySchedule.showFrom = showFrom;
            if (showUntil) updatedTile.visibilitySchedule.showUntil = showUntil;
        } else {
            delete updatedTile.visibilitySchedule;
        }

        const saved = await persistTile(updatedTile, hasScheduleNow ? 'Zeitplan gespeichert' : 'Zeitplan gelöscht');
        if (saved && !hadScheduleBefore && hasScheduleNow) {
            showScheduleSecurityWarning();
        }
    }

    async function clearSchedule() {
        const tile = getCurrentTile();
        if (!tile) return;

        const updatedTile = cloneTile(tile);
        delete updatedTile.visibilitySchedule;
        await persistTile(updatedTile, 'Zeitplan gelöscht');
    }

    async function duplicateTile() {
        const tile = getCurrentTile();
        if (!tile) return;

        const clone = cloneTile(tile);
        delete clone.id;
        clone.position = (tile.position || 0) + 5;
        if (clone.data?.title) {
            clone.data.title += ' (Kopie)';
        }
        await persistTile(clone, 'Kachel dupliziert');
    }

    async function deleteTile() {
        const tile = getCurrentTile();
        if (!tile) return;

        const name = tile.data?.title || tile.type || 'diese Kachel';
        hide();

        if (!confirm(`"${name}" wirklich löschen?`)) return;

        try {
            const result = await V2Api.deleteTile(tile.id);
            if (!result.success) {
                showToast(result.error || 'Löschen fehlgeschlagen', 'error');
                return;
            }
            V2State.deselectAll();
            V2State.setDirty(true);
            await V2Canvas.reloadAll();
            showToast('Kachel gelöscht', 'success');
        } catch (error) {
            console.error('[V2ContextMenu] deleteTile failed:', error);
            showToast(error.message || 'Löschen fehlgeschlagen', 'error');
        }
    }

    function clearScheduleField(field) {
        const inputId = field === 'showFrom' ? 'v2ScheduleShowFrom' : 'v2ScheduleShowUntil';
        const input = _menu.querySelector(`#${inputId}`);
        if (input) {
            input.value = '';
        }
    }

    function setSchedulePreset(preset) {
        const input = _menu.querySelector('#v2ScheduleShowUntil');
        if (!input) return;

        const now = new Date();
        let showUntil = null;

        switch (preset) {
            case 'week':
                showUntil = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
                break;
            case 'month':
                showUntil = new Date(now.getFullYear(), now.getMonth() + 1, now.getDate(), now.getHours(), now.getMinutes());
                break;
        }

        if (!showUntil) return;
        input.value = showUntil.toISOString().substring(0, 16);
    }

    async function persistTile(updatedTile, successMessage) {
        try {
            const result = await V2Api.saveTile(updatedTile);
            if (!result.success) {
                showToast(result.error || 'Speichern fehlgeschlagen', 'error');
                return false;
            }

            V2State.setDirty(true);
            hide();
            await V2Canvas.reloadAll();
            requestAnimationFrame(() => {
                V2State.selectTile(updatedTile.id);
            });
            showToast(successMessage, 'success');
            return true;
        } catch (error) {
            console.error('[V2ContextMenu] persistTile failed:', error);
            showToast(error.message || 'Speichern fehlgeschlagen', 'error');
            return false;
        }
    }

    function getCurrentTile() {
        return _currentTileId ? V2State.getTileById(_currentTileId) : null;
    }

    function cloneTile(tile) {
        return JSON.parse(JSON.stringify(tile));
    }

    function showAndPosition() {
        if (!_menu) return;

        _menu.style.visibility = 'hidden';
        _menu.style.display = 'block';

        const rect = _menu.getBoundingClientRect();
        let left = _lastPointerX;
        let top = _lastPointerY;

        if (left + rect.width > window.innerWidth - 8) {
            left = window.innerWidth - rect.width - 8;
        }
        if (top + rect.height > window.innerHeight - 8) {
            top = window.innerHeight - rect.height - 8;
        }

        _menu.style.left = `${Math.max(8, left)}px`;
        _menu.style.top = `${Math.max(8, top)}px`;
        _menu.style.visibility = 'visible';
    }

    function getScheduleSummary(schedule) {
        const parts = [];

        if (schedule.showFrom) {
            parts.push(`ab ${formatDateTime(new Date(schedule.showFrom))}`);
        }
        if (schedule.showUntil) {
            parts.push(`bis ${formatDateTime(new Date(schedule.showUntil))}`);
        }

        return parts.join(' · ');
    }

    function showScheduleSecurityWarning() {
        if (_scheduleWarningShown) return;
        _scheduleWarningShown = true;
        showToast('Zeitgesteuerte Inhalte bleiben fuer versierte Nutzer im Quelltext auffindbar.', 'warning');
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

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function getVisibilityStatus(tile) {
        const now = new Date();
        const schedule = tile?.visibilitySchedule || {};
        const showFrom = schedule.showFrom ? new Date(schedule.showFrom) : null;
        const showUntil = schedule.showUntil ? new Date(schedule.showUntil) : null;
        const hasSchedule = !!(showFrom || showUntil);

        if (tile?.visible === false) {
            return {
                effectivelyHidden: true,
                hasSchedule,
                badgeLabel: 'Nicht im Export',
                longLabel: 'Manuell versteckt - wird beim Veröffentlichen nicht exportiert.',
                visualClass: 'v2-visibility-hidden'
            };
        }

        if (showFrom && now < showFrom) {
            return {
                effectivelyHidden: true,
                hasSchedule: true,
                badgeLabel: `Ab ${formatDateShort(showFrom)}`,
                longLabel: `Wird ab ${formatDateTime(showFrom)} sichtbar.`,
                visualClass: 'v2-visibility-scheduled'
            };
        }

        if (showUntil && now > showUntil) {
            return {
                effectivelyHidden: true,
                hasSchedule: true,
                badgeLabel: 'Abgelaufen',
                longLabel: `War sichtbar bis ${formatDateTime(showUntil)}.`,
                visualClass: 'v2-visibility-expired'
            };
        }

        if (showUntil && now <= showUntil) {
            return {
                effectivelyHidden: false,
                hasSchedule: true,
                badgeLabel: `Bis ${formatDateShort(showUntil)}`,
                longLabel: `Sichtbar bis ${formatDateTime(showUntil)}.`,
                visualClass: 'v2-visibility-until'
            };
        }

        if (showFrom) {
            return {
                effectivelyHidden: false,
                hasSchedule: true,
                badgeLabel: 'Zeitplan aktiv',
                longLabel: `Zeitsteuerung aktiv seit ${formatDateTime(showFrom)}.`,
                visualClass: 'v2-visibility-until'
            };
        }

        return {
            effectivelyHidden: false,
            hasSchedule: false,
            badgeLabel: '',
            longLabel: 'Sichtbar und im Export enthalten.',
            visualClass: ''
        };
    }

    return {
        init,
        showTileMenu,
        hide,
        isOpen,
        getVisibilityStatus
    };
})();

export { V2ContextMenu };
export default V2ContextMenu;