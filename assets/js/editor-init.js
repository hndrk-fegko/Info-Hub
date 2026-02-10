/**
 * Editor Init - Initialisierung, Keyboard-Shortcuts, Event-Listener
 * 
 * Abhängigkeiten: Alle anderen editor-*.js Module müssen vorher geladen sein.
 * Wird als LETZTES geladen.
 */

// ===== Initialization =====
document.addEventListener('DOMContentLoaded', () => {
    renderTiles();
    startSessionTimer();
    initKeyboardShortcuts();
    applyPublishButtonStyle();
    checkPermissionsOnLoad();
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
        const submitForm = (form) => {
            if (!form) return;
            // requestSubmit() löst Validation + submit-Event aus,
            // existiert aber nicht in Safari < 16
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                // Fallback: manuell validieren, dann submit-Event feuern
                if (form.reportValidity()) {
                    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                }
            }
        };
        if (tileModalOpen) {
            submitForm(document.getElementById('tileForm'));
        } else if (settingsModalOpen) {
            submitForm(document.getElementById('settingsForm'));
        } else {
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
