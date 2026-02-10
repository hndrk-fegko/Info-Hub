<?php
/**
 * Editor - Hauptverwaltung für Tiles
 * 
 * Geschützter Bereich - erfordert Authentifizierung
 */

// WICHTIG: Config ZUERST laden, bevor andere Services!
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    // Config fehlt - zurück zu Setup
    header('Location: setup.php');
    exit;
}

// Error Reporting basierend auf DEBUG_MODE (bereits in config.php gesetzt)

require_once __DIR__ . '/core/AuthService.php';
require_once __DIR__ . '/core/TileService.php';
require_once __DIR__ . '/core/StorageService.php';
require_once __DIR__ . '/core/SecurityHelper.php';

// Auth prüfen
$auth = new AuthService();
if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// CSRF Token
$csrfToken = $_SESSION['csrf_token'] ?? '';

// Services
$tileService = new TileService();
$tiles = $tileService->getTiles();
$settingsStorage = new StorageService('settings.json');
$settings = $settingsStorage->read();

// Daten-Bereinigung: headerImage muss String oder null sein
if (isset($settings['site']['headerImage']) && !is_string($settings['site']['headerImage'])) {
    $settings['site']['headerImage'] = null;
    // Korrigierte Settings speichern
    $settingsStorage->write($settings);
}

// Session-Werte für JavaScript - DIREKT aus AuthService holen
$sessionTimeout = $auth->getSessionTimeout();
$sessionWarning = $auth->getSessionWarningBefore();
$remainingTime = $auth->getRemainingSessionTime();

// Debug: Werte prüfen
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_log("Editor Debug - SESSION_TIMEOUT constant: " . (defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 'not defined'));
    error_log("Editor Debug - sessionTimeout from AuthService: " . $sessionTimeout);
    error_log("Editor Debug - sessionWarning from AuthService: " . $sessionWarning);
    error_log("Editor Debug - remainingTime: " . $remainingTime);
}

// Generierte Seite Info
$indexExists = file_exists(__DIR__ . '/../index.html');
$lastGenerated = $indexExists ? filemtime(__DIR__ . '/../index.html') : null;

// Security Status
$securityWarnings = SecurityHelper::getSecurityStatus();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-src 'none';">
    <title>Editor - <?= htmlspecialchars($settings['site']['title'] ?? 'Info-Hub') ?></title>
    <link rel="stylesheet" href="../assets/css/editor.css">
</head>
<body>
    <div class="editor">
        <header class="editor-header">
            <div class="header-top">
                <div class="header-left">
                    <h1>📝 Editor</h1>
                    <span class="site-name"><?= htmlspecialchars($settings['site']['title'] ?? 'Info-Hub') ?></span>
                    <?php if ($indexExists): ?>
                        <a href="../index.html" target="_blank" class="published-link" title="Veröffentlichte Seite öffnen">
                            🌐 Seite anzeigen
                        </a>
                        <span class="last-generated">
                            Zuletzt: <?= date('d.m. H:i', $lastGenerated) ?>
                        </span>
                    <?php else: ?>
                        <span class="not-published">⚠️ Noch nicht veröffentlicht</span>
                    <?php endif; ?>
                </div>
                <div class="header-actions">
                    <?= SecurityHelper::renderSecurityBadge() ?>
                    <div class="session-timer" id="sessionTimer" title="Verbleibende Session-Zeit">
                        🕐 <span id="sessionTimeDisplay">--</span>
                    </div>
                    <button type="button" class="btn btn-icon" onclick="openSettingsModal()" title="Einstellungen (S)">
                        ⚙️
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="openPreview()" title="Vorschau öffnen (P)">
                        👁️ Vorschau
                    </button>
                    <button type="button" class="btn btn-primary" id="publishBtn" onclick="publishSite()" title="Seite veröffentlichen (V)">
                        🚀 Veröffentlichen
                    </button>
                    <button type="button" class="btn btn-icon" onclick="logout()" title="Abmelden">
                        🚪
                    </button>
                </div>
            </div>
            <div class="diagnostics-info" id="diagnosticsInfo" style="display: none;">
                <div class="diag-banner diag-warning">
                    <strong>⚠️ Upload-Problem erkannt:</strong> 
                    <span id="diagnosticsMessage"></span>
                    <details style="margin-top: 8px;">
                        <summary>Lösung anzeigen</summary>
                        <div id="diagnosticsDetails" style="margin-top: 8px; padding: 8px; background: rgba(0,0,0,0.1); border-radius: 4px; font-size: 0.9em;"></div>
                    </details>
                </div>
            </div>
        </header>
        
        <main class="editor-main">
            <div class="tiles-header">
                <h2>Kacheln</h2>
                <span class="tile-count" id="tileCount"><?= count($tiles) ?> Kacheln</span>
            </div>
            
            <div class="tiles-list" id="tilesList">
                <!-- Wird per JavaScript gerendert -->
            </div>
            
            <button type="button" class="add-tile-btn" onclick="openTileModal()" title="Neue Kachel hinzufügen (N)">
                + Neue Kachel hinzufügen
            </button>
            
            <!-- Tipps & Tricks Panel -->
            <details class="tips-panel">
                <summary class="tips-toggle">
                    💡 Tipps & Tricks
                </summary>
                <div class="tips-content">
                    <div class="tip">
                        <strong>📐 Unsichtbare Platzhalter</strong>
                        <p>Nutze <em>Infobox</em>-Kacheln mit Style "Flat", ohne Titel und Inhalt, um leere Bereiche zu erzeugen.</p>
                    </div>
                    <div class="tip">
                        <strong>📏 Zeilenumbruch erzwingen</strong>
                        <p>Ein <em>Trenner</em> mit Höhe 0 erzwingt einen sauberen Umbruch - perfekt für Abschnitte.</p>
                    </div>
                    <div class="tip">
                        <strong>🎨 Visuelle Hierarchie</strong>
                        <p>Verwende die Akzentfarben für wichtige Infoboxen (z.B. Überschriften), um Bereiche zu gliedern.</p>
                    </div>
                    <div class="tip">
                        <strong>📱 Responsive Design</strong>
                        <p>Kleine Kacheln werden auf dem Handy übereinander angezeigt - teste mit der Vorschau!</p>
                    </div>
                    <div class="tip">
                        <strong>⏰ Zeitsteuerung</strong>
                        <p>Nutze "Ab/Bis"-Zeiten für saisonale Inhalte. Die Kacheln werden automatisch ein-/ausgeblendet.</p>
                    </div>
                </div>
            </details>
        </main>
    </div>
    
    <!-- Tile Modal -->
    <div id="tileModal" class="modal">
        <div class="modal-content modal-compact">
            <div class="modal-header">
                <h2 id="tileModalTitle">Neue Kachel</h2>
                <button type="button" class="modal-close" onclick="closeTileModal()">×</button>
            </div>
            <form id="tileForm" onsubmit="saveTile(event)">
                <input type="hidden" name="id" id="tileId">
                <input type="hidden" name="position" id="tilePosition" value="10">
                <input type="hidden" name="size" id="tileSize" value="medium">
                <input type="hidden" name="style" id="tileStyle" value="card">
                <input type="hidden" name="colorScheme" id="tileColorScheme" value="default">
                
                <div class="tile-type-selector">
                    <label for="tileType">Kachel-Typ</label>
                    <select name="type" id="tileType" onchange="updateTileFields()" required>
                        <option value="">-- Typ wählen --</option>
                        <?php foreach ($tileService->getAvailableTypes() as $type => $info): ?>
                            <option value="<?= htmlspecialchars($type) ?>">
                                <?= htmlspecialchars($info['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-divider">Inhalt</div>
                
                <!-- Dynamische Felder je nach Typ -->
                <div id="tileFields"></div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeTileModal()">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">💾 Speichern</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Context Menu für Quick-Edit -->
    <div id="contextMenu" class="context-menu" style="display: none;">
        <div class="context-menu-content"></div>
    </div>
    
    <!-- Settings Modal -->
    <div id="settingsModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>⚙️ Einstellungen</h2>
                <button type="button" class="modal-close" onclick="closeSettingsModal()">×</button>
            </div>
            <form id="settingsForm" onsubmit="saveSettings(event)">
                <div class="settings-section">
                    <h3>Seite</h3>
                    <div class="form-group">
                        <label for="siteTitle">Seitentitel</label>
                        <input type="text" name="title" id="siteTitle" value="<?= htmlspecialchars($settings['site']['title'] ?? '') ?>">
                        <small>Wird im Header angezeigt. Leer lassen für nur Header-Bild.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="pageTitle">Browser-Tab Titel</label>
                        <input type="text" name="pageTitle" id="pageTitle" value="<?= htmlspecialchars($settings['site']['pageTitle'] ?? '') ?>" placeholder="<?= htmlspecialchars($settings['site']['title'] ?? 'Info-Hub') ?>">
                        <small>Titel im Browser-Tab. Leer = Seitentitel wird verwendet.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="footerText">Footer-Text</label>
                        <textarea name="footerText" id="footerText" rows="3" placeholder="© 2026 ..."><?= htmlspecialchars($settings['site']['footerText'] ?? '') ?></textarea>
                        <small>Mehrzeilig möglich. Leer = kein Footer</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Header-Bild</label>
                        <div class="header-image-preview" id="headerPreview">
                            <?php 
                            $headerImg = $settings['site']['headerImage'] ?? null;
                            // Sicherstellen dass es ein String ist (nicht Array oder Object)
                            if (!is_string($headerImg)) $headerImg = null;
                            ?>
                            <?php if (!empty($headerImg)): ?>
                                <img src="<?= htmlspecialchars($headerImg) ?>" alt="Header">
                                <button type="button" class="btn btn-small" onclick="removeHeaderImage()">Entfernen</button>
                            <?php else: ?>
                                <span class="no-image">Kein Header-Bild</span>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="headerImage" id="headerImagePath" value="<?= htmlspecialchars($headerImg ?? '') ?>">
                        <input type="file" name="headerImageFile" id="headerImageFile" accept="image/*" onchange="uploadHeaderImage(this)">
                    </div>
                    
                    <div class="form-group">
                        <label for="headerFocusPoint">Bild-Fokuspunkt</label>
                        <select name="headerFocusPoint" id="headerFocusPoint">
                            <option value="center center" <?= ($settings['site']['headerFocusPoint'] ?? 'center center') === 'center center' ? 'selected' : '' ?>>Mitte</option>
                            <option value="center top" <?= ($settings['site']['headerFocusPoint'] ?? '') === 'center top' ? 'selected' : '' ?>>Oben</option>
                            <option value="center bottom" <?= ($settings['site']['headerFocusPoint'] ?? '') === 'center bottom' ? 'selected' : '' ?>>Unten</option>
                            <option value="left center" <?= ($settings['site']['headerFocusPoint'] ?? '') === 'left center' ? 'selected' : '' ?>>Links</option>
                            <option value="right center" <?= ($settings['site']['headerFocusPoint'] ?? '') === 'right center' ? 'selected' : '' ?>>Rechts</option>
                            <option value="left top" <?= ($settings['site']['headerFocusPoint'] ?? '') === 'left top' ? 'selected' : '' ?>>Oben Links</option>
                            <option value="right top" <?= ($settings['site']['headerFocusPoint'] ?? '') === 'right top' ? 'selected' : '' ?>>Oben Rechts</option>
                            <option value="left bottom" <?= ($settings['site']['headerFocusPoint'] ?? '') === 'left bottom' ? 'selected' : '' ?>>Unten Links</option>
                            <option value="right bottom" <?= ($settings['site']['headerFocusPoint'] ?? '') === 'right bottom' ? 'selected' : '' ?>>Unten Rechts</option>
                        </select>
                        <small>Bestimmt, welcher Bildbereich beim Zuschneiden sichtbar bleibt</small>
                    </div>
                </div>

                <div class="settings-section">
                    <h3>Admin-Benutzer</h3>
                    <div id="adminEmailList" class="admin-email-list"></div>
                    <div class="admin-actions">
                        <button type="button" class="btn btn-secondary" onclick="openInviteAdminModal()">
                            Neuen Admin einladen
                        </button>
                    </div>
                    <small>Ausstehende Einladungen laufen nach 60 Minuten ab. Die letzte Admin-Adresse kann nicht gelöscht werden.</small>
                </div>
                
                <div class="settings-section">
                    <h3>Farben</h3>
                    <div class="form-row color-row">
                        <div class="form-group">
                            <label for="backgroundColor">Hintergrund:</label>
                            <input type="color" name="backgroundColor" id="backgroundColor" value="<?= htmlspecialchars($settings['theme']['backgroundColor'] ?? '#f5f5f5') ?>">
                        </div>
                        <div class="form-group">
                            <label for="accentColor">Akzent 1:</label>
                            <input type="color" name="accentColor" id="accentColor" value="<?= htmlspecialchars($settings['theme']['accentColor'] ?? '#667eea') ?>">
                        </div>
                        <div class="form-group">
                            <label for="accentColor2">Akzent 2:</label>
                            <input type="color" name="accentColor2" id="accentColor2" value="<?= htmlspecialchars($settings['theme']['accentColor2'] ?? '#48bb78') ?>">
                        </div>
                        <div class="form-group">
                            <label for="accentColor3">Akzent 3:</label>
                            <input type="color" name="accentColor3" id="accentColor3" value="<?= htmlspecialchars($settings['theme']['accentColor3'] ?? '#ed8936') ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeSettingsModal()">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Invite Admin Modal -->
    <div id="inviteAdminModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Neuen Admin einladen</h2>
                <button type="button" class="modal-close" onclick="closeInviteAdminModal()">×</button>
            </div>
            <form id="inviteAdminForm" onsubmit="submitInviteAdmin(event)">
                <div class="form-group">
                    <label for="inviteEmail">Email-Adresse</label>
                    <input type="email" id="inviteEmail" name="inviteEmail" placeholder="name@example.com" required>
                    <small>Der eingeladene Admin muss sich innerhalb von 60 Minuten anmelden.</small>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeInviteAdminModal()">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Einladung senden</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- File Browser Modal -->
    <div id="fileBrowserModal" class="modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>📁 Datei auswählen</h2>
                <button type="button" class="modal-close" onclick="closeFileBrowser()">×</button>
            </div>
            <div class="file-browser">
                <div class="file-browser-tabs">
                    <button type="button" class="tab active" data-type="images" onclick="loadFiles('images')">🖼️ Bilder</button>
                    <button type="button" class="tab" data-type="downloads" onclick="loadFiles('downloads')">📄 Dateien</button>
                </div>
                <div class="file-browser-content">
                    <div class="file-list" id="fileList">
                        <!-- Dateien werden per JS geladen -->
                    </div>
                    <div class="file-upload-dropzone" id="fileDropzone">
                        <input type="file" id="fileBrowserUpload" onchange="uploadFile(this)" style="display: none;">
                        <div class="dropzone-content">
                            <span class="dropzone-icon">📁</span>
                            <p>Dateien hierher ziehen<br><small>oder klicken zum Auswählen</small></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Session Timeout Dialog wird dynamisch von JS erstellt mit korrekten Werten aus CONFIG -->
    
    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>
    
    <script>
        // Konfiguration aus PHP - als window.CONFIG für editor.js
        window.CONFIG = {
            csrfToken: '<?= $csrfToken ?>',
            apiUrl: 'api/endpoints.php',
            sessionTimeout: <?= $sessionTimeout ?>,
            sessionWarning: <?= $sessionWarning ?>,
            sessionRemaining: <?= $remainingTime ?>,
            debugMode: <?= (defined('DEBUG_MODE') && DEBUG_MODE) ? 'true' : 'false' ?>,
            // Daten für Editor
            tiles: <?= json_encode($tiles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            tileTypes: <?= json_encode($tileService->getAvailableTypes(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            settings: <?= json_encode($settings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
        };
        
        // Debug output nur wenn DEBUG_MODE aktiv ist
        if (window.CONFIG.debugMode) {
            console.log('DEBUG_MODE is active');
            console.log('CONFIG loaded:', window.CONFIG);
            console.log('Session timeout:', window.CONFIG.sessionTimeout, 'seconds');
            console.log('Session warning:', window.CONFIG.sessionWarning, 'seconds before');
            console.log('Tiles:', window.CONFIG.tiles?.length || 0);
        }
        
    </script>
    <!-- Editor Module (Reihenfolge wichtig: core → tiles → modals → settings → session → init) -->
    <?php
    $editorModules = ['editor-core', 'editor-tiles', 'editor-modals', 'editor-settings', 'editor-session', 'editor-init'];
    foreach ($editorModules as $module):
        $filePath = __DIR__ . "/../assets/js/{$module}.js";
        $version = file_exists($filePath) ? filemtime($filePath) : time();
    ?>
    <script src="../assets/js/<?= $module ?>.js?v=<?= $version ?>"></script>
    <?php endforeach; ?>
</body>
</html>
