/**
 * Editor Session - Session-Timer, Auto-Extend, Ablauf-Dialog, Diagnostics
 * 
 * Abhängigkeiten: editor-core.js (apiPost, showToast)
 */

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

// ===== Diagnostics & Permission Checks =====

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
