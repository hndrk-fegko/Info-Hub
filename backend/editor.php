<?php
/**
 * LEGACY_CLASSIC_EDITOR
 * Classic Editor - Legacy-Hauptverwaltung für Tiles.
 *
 * Geschützter Bereich - erfordert Authentifizierung.
 * V2 ist die kanonische Weiterentwicklung; Classic bleibt nur im Wartungsmodus.
 */
$bootstrapMode = 'page';
$bootstrapServices = [
    'AuthService',
    'TileService',
    'StorageService',
    'SecurityHelper',
    'ConfigService',
];
$bootstrapMissingConfigRedirect = 'setup.php';
$bootstrap = require __DIR__ . '/bootstrap.php';

$container = $bootstrap['container'];

// Auth prüfen
$auth = $container->authService();
if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// CSRF Token
$csrfToken = $_SESSION['csrf_token'] ?? '';

// Services
$tileService = $container->tileService();
$tiles = $tileService->getTiles();
$settingsStorage = $container->storage('settings.json');
$settings = $settingsStorage->read();
$configService = $container->configService();
$settings['system']['mailFromAddress'] = $configService->getMailFromAddress(
    $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '',
    $_SESSION['auth_email'] ?? ''
);

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
    <title>Classic Editor (Legacy) - <?= htmlspecialchars($settings['site']['title'] ?? 'Info-Hub') ?></title>
    <link rel="stylesheet" href="../assets/css/editor.css">
</head>
<body data-legacy-classic="LEGACY_CLASSIC_EDITOR">
    <!-- LEGACY_CLASSIC_EDITOR: Classic bleibt vorerst erreichbar, wird aber nicht mehr weiterentwickelt. -->
    <div class="editor">
        <header class="editor-header">
            <div class="header-top">
                <div class="header-left">
                    <div class="header-title-group">
                        <h1>📝 Classic Editor</h1>
                        <span class="legacy-classic-tag">Legacy</span>
                        <span class="site-name"><?= htmlspecialchars($settings['site']['title'] ?? 'Info-Hub') ?></span>
                    </div>
                    <div class="header-meta">
                        <?php if ($indexExists): ?>
                            <a href="../index.html" target="_blank" class="published-link" title="Veröffentlichte Seite öffnen">
                                <span class="published-link-icon">🌐</span>
                                <span class="published-link-label">Seite anzeigen</span>
                            </a>
                            <span class="last-generated">
                                Zuletzt: <?= date('d.m. H:i', $lastGenerated) ?>
                            </span>
                        <?php else: ?>
                            <span class="not-published">⚠️ Noch nicht veröffentlicht</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="header-actions">
                    <?= SecurityHelper::renderSecurityBadge() ?>
                    <div class="session-timer" id="sessionTimer" title="Verbleibende Session-Zeit">
                        🕐 <span id="sessionTimeDisplay">--</span>
                    </div>
                    <a href="v2/editor.php" class="btn btn-secondary" title="Zum V2-Editor">
                        <span class="btn-glyph">✏️</span>
                        <span class="btn-label">V2 Editor</span>
                    </a>
                    <button type="button" class="btn btn-icon" onclick="openSettingsModal()" title="Einstellungen (S)">
                        ⚙️
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="openPreview()" title="Vorschau öffnen (P)">
                        <span class="btn-glyph">👁️</span>
                        <span class="btn-label">Vorschau</span>
                    </button>
                    <button type="button" class="btn btn-primary" id="publishBtn" onclick="publishSite()" title="Seite veröffentlichen (V)">
                        <span class="btn-glyph">🚀</span>
                        <span class="btn-label">Veröffentlichen</span>
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

        <div class="legacy-classic-note diag-banner diag-warning" data-legacy-classic="LEGACY_CLASSIC_EDITOR">
            <strong>Legacy-Modus:</strong>
            Classic wird nicht mehr weiterentwickelt. Breaking Changes werden hier ggf. nicht mehr beruecksichtigt.
            Nutzung auf eigene Gefahr. Vor groesseren Aenderungen zuerst ein Backup anlegen.
            <span class="legacy-classic-note__actions">
                <a href="backup.php">Backup-Verwaltung</a>
                <a href="v2/editor.php">Zum V2-Editor</a>
            </span>
        </div>
        
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
                <div class="settings-tabs" role="tablist" aria-label="Einstellungsbereiche">
                    <button type="button" class="settings-tab active" data-settings-tab="design" onclick="switchSettingsTab('design')">Design</button>
                    <button type="button" class="settings-tab" data-settings-tab="system" onclick="switchSettingsTab('system')">System</button>
                </div>

                <div class="settings-tab-panel active" data-settings-panel="design">
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

                <div class="settings-section">
                    <h3>Layout</h3>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="narrowLayout" id="narrowLayout" <?= !empty($settings['theme']['narrowLayout']) ? 'checked' : '' ?>>
                            Schmales Layout (begrenzte Breite mit dunklem Hintergrund)
                        </label>
                        <small>Rendert die statische Seite zentriert mit begrenzter Breite</small>
                    </div>
                    <div class="form-group" id="narrowWidthGroup" style="<?= empty($settings['theme']['narrowLayout']) ? 'display:none' : '' ?>">
                        <label for="narrowWidth">Maximale Breite: <span id="narrowWidthValue"><?= intval($settings['theme']['narrowWidth'] ?? 960) ?></span>px</label>
                        <input type="range" name="narrowWidth" id="narrowWidth"
                               min="600" max="1400" step="20"
                               value="<?= intval($settings['theme']['narrowWidth'] ?? 960) ?>">
                        <div class="range-labels"><span>600px</span><span>1400px</span></div>
                    </div>
                    <small class="hint">Erweiterte Hintergründe und Schatten für den schmalen Modus werden nur im WYSIWYG-Editor gepflegt.</small>
                </div>
                </div>

                <div class="settings-tab-panel" data-settings-panel="system">
                <div class="settings-section">
                    <h3>System-Mail</h3>
                    <div class="form-group">
                        <label for="mailFromAddress">Absender-Adresse</label>
                        <input type="email" name="mailFromAddress" id="mailFromAddress" value="<?= htmlspecialchars($settings['system']['mailFromAddress'] ?? '') ?>" required>
                        <small>Für Login-Codes und Einladungen. Empfehlung: Hauptdomain ohne Subdomain, z.B. noreply@sv-wolken.de.</small>
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
            currentEmail: '<?= htmlspecialchars($_SESSION['auth_email'] ?? '', ENT_QUOTES) ?>',
            sessionTimeout: <?= $sessionTimeout ?>,
            sessionWarning: <?= $sessionWarning ?>,
            sessionRemaining: <?= $remainingTime ?>,
            debugMode: <?= (defined('DEBUG_MODE') && DEBUG_MODE) ? 'true' : 'false' ?>,
            legacyClassicTag: 'LEGACY_CLASSIC_EDITOR',
            legacyClassicMode: true,
            legacyClassicWarning: 'Hinweis: Classic wird nicht mehr entwickelt und ggf. werden Breaking Changes hier nicht mehr beruecksichtigt. Nutzung auf eigene Gefahr. Bitte zuerst ein Backup anlegen.',
            // Daten für Editor
            tiles: <?= json_encode($tiles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            tileTypes: <?= json_encode($tileService->getAvailableTypesWithMeta(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
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

        // LEGACY_CLASSIC_EDITOR: Classic bleibt nur als Wartungspfad erreichbar.
        (function enforceClassicLegacyAcknowledgement() {
            const storageKey = 'infoHubClassicLegacyConfirmed';
            let alreadyConfirmed = false;

            try {
                alreadyConfirmed = window.sessionStorage.getItem(storageKey) === '1';
            } catch (error) {
                alreadyConfirmed = false;
            }

            if (alreadyConfirmed) {
                return;
            }

            const accepted = window.confirm(window.CONFIG.legacyClassicWarning);
            if (!accepted) {
                window.location.replace('v2/editor.php');
                return;
            }

            try {
                window.sessionStorage.setItem(storageKey, '1');
            } catch (error) {
                // Kein Storage verfuegbar: Hinweis dann beim naechsten Aufruf erneut zeigen.
            }
        })();
        
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
