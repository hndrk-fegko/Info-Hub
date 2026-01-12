<?php
/**
 * Editor - Visueller Content-Editor
 * 
 * Protected durch AuthService.
 * Ermöglicht:
 * - Tiles erstellen, bearbeiten, löschen
 * - Positionen ändern
 * - Settings verwalten
 * - HTML generieren (Publish)
 */

// Zentrale Konfiguration laden
require_once __DIR__ . '/config.php';

session_start();

require_once __DIR__ . '/core/AuthService.php';
require_once __DIR__ . '/core/TileService.php';
require_once __DIR__ . '/core/StorageService.php';
require_once __DIR__ . '/core/SecurityHelper.php';
require_once __DIR__ . '/tiles/_registry.php';

$auth = new AuthService();
$securityStatus = SecurityHelper::getSecurityStatus();

// Auth-Check
if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Daten für initiales Laden
$tileService = new TileService();
$tiles = $tileService->getTiles();
$tileTypes = $tileService->getAvailableTypes();

$settingsStorage = new StorageService('settings.json');
$settings = $settingsStorage->read();

$remainingTime = $auth->getRemainingSessionTime();

// Prüfen ob index.html existiert und wann zuletzt generiert
$indexPath = __DIR__ . '/../index.html';
$indexExists = file_exists($indexPath);
$lastGenerated = $indexExists ? filemtime($indexPath) : null;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor - <?= htmlspecialchars($settings['site']['title'] ?? 'Info-Hub') ?></title>
    <link rel="stylesheet" href="../assets/css/editor.css">
</head>
<body>
    <div class="editor-layout">
        <!-- Header -->
        <header class="editor-header">
            <div class="header-left">
                <h1>📝 Info-Hub Editor</h1>
                <span class="site-name"><?= htmlspecialchars($settings['site']['title'] ?? '') ?></span>
                <?php if ($indexExists): ?>
                    <a href="../index.html" target="_blank" class="published-link" title="Zuletzt generiert: <?= date('d.m.Y H:i', $lastGenerated) ?>">
                        🌐 Veröffentlicht <small>(<?= date('d.m. H:i', $lastGenerated) ?>)</small>
                    </a>
                <?php else: ?>
                    <span class="not-published">⚠️ Noch nicht veröffentlicht</span>
                <?php endif; ?>
            </div>
            <div class="header-right">
                <?php if ($securityStatus['hasWarnings']): ?>
                    <?= SecurityHelper::renderSecurityBadge() ?>
                <?php endif; ?>
                <span class="session-info" title="Session läuft ab in">
                    ⏱️ <span id="sessionTimer"><?= floor($remainingTime / 60) ?>min</span>
                </span>
                <button class="btn btn-icon" onclick="openSettingsModal()" title="Einstellungen">
                    ⚙️
                </button>
                <button class="btn btn-icon" onclick="openPreview()" title="Vorschau">
                    👁️
                </button>
                <button class="btn btn-primary" onclick="publishSite()">
                    🚀 Veröffentlichen
                </button>
                <a href="login.php?logout=1" class="btn btn-icon" title="Ausloggen">
                    🚪
                </a>
            </div>
        </header>
        
        <!-- Main Content -->
        <main class="editor-main">
            <div class="tiles-container">
                <div class="tiles-header">
                    <h2>Kacheln</h2>
                    <span class="tile-count" id="tileCount"><?= count($tiles) ?> Kacheln</span>
                </div>
                
                <div class="tiles-list" id="tilesList">
                    <!-- Tiles werden per JS gerendert -->
                </div>
                
                <button class="add-tile-btn" onclick="openTileModal()">
                    <span class="btn-icon">+</span>
                    <span>Neue Kachel hinzufügen</span>
                </button>
            </div>
        </main>
    </div>
    
    <!-- Tile Modal -->
    <div class="modal" id="tileModal">
        <div class="modal-backdrop" onclick="closeTileModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="tileModalTitle">Neue Kachel</h2>
                <button class="modal-close" onclick="closeTileModal()">&times;</button>
            </div>
            <form id="tileForm" onsubmit="saveTile(event)">
                <input type="hidden" name="id" id="tileId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="tileType">Typ</label>
                        <select name="type" id="tileType" onchange="updateTileFields()" required>
                            <option value="">-- Typ wählen --</option>
                            <?php foreach ($tileTypes as $type => $info): ?>
                                <option value="<?= $type ?>" data-fields='<?= json_encode($info['fields']) ?>'>
                                    <?= htmlspecialchars($info['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row form-row-2">
                    <div class="form-group">
                        <label for="tilePosition">Position</label>
                        <input type="number" name="position" id="tilePosition" value="10" min="0" step="10">
                    </div>
                    <div class="form-group">
                        <label for="tileSize">Größe</label>
                        <select name="size" id="tileSize">
                            <option value="small">Klein (1 Spalte)</option>
                            <option value="medium" selected>Mittel (1 Spalte)</option>
                            <option value="large">Groß (2 Spalten)</option>
                            <option value="full">Volle Breite</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row form-row-2">
                    <div class="form-group">
                        <label for="tileStyle">Stil</label>
                        <select name="style" id="tileStyle" onchange="updateColorSchemeOptions()">
                            <option value="card">Card (mit Schatten)</option>
                            <option value="flat">Flat (transparent)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tileColorScheme">Hintergrundfarbe</label>
                        <select name="colorScheme" id="tileColorScheme">
                            <option value="default">Standard (Hintergrund)</option>
                            <option value="white" class="card-only">Weiß</option>
                            <option value="accent1">Akzent 1 (Seitentitel)</option>
                            <option value="accent2">Akzent 2</option>
                            <option value="accent3">Akzent 3</option>
                        </select>
                    </div>
                </div>
                
                <hr>
                
                <!-- Dynamische Felder je nach Tile-Typ -->
                <div id="tileFields">
                    <p class="hint">Wähle zuerst einen Typ aus.</p>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeTileModal()">
                        Abbrechen
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Settings Modal -->
    <div class="modal" id="settingsModal">
        <div class="modal-backdrop" onclick="closeSettingsModal()"></div>
        <div class="modal-content modal-wide">
            <div class="modal-header">
                <h2>⚙️ Einstellungen</h2>
                <button class="modal-close" onclick="closeSettingsModal()">&times;</button>
            </div>
            <form id="settingsForm" onsubmit="saveSettings(event)">
                <div class="settings-section">
                    <h3>Seite</h3>
                    
                    <div class="form-group">
                        <label for="siteTitle">Seiten-Titel</label>
                        <input type="text" name="title" id="siteTitle" 
                               value="<?= htmlspecialchars($settings['site']['title'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="headerImage">Header-Bild</label>
                        <div class="file-input-group">
                            <input type="text" name="headerImage" id="headerImage" 
                                   value="<?= htmlspecialchars($settings['site']['headerImage'] ?? '') ?>"
                                   placeholder="Kein Header-Bild" readonly>
                            <input type="file" id="headerImageFile" accept="image/*" style="display:none"
                                   onchange="uploadHeaderImage(this)">
                            <button type="button" class="btn btn-small" 
                                    onclick="document.getElementById('headerImageFile').click()">
                                📷 Hochladen
                            </button>
                            <button type="button" class="btn btn-small btn-danger" onclick="removeHeaderImage()">
                                🗑️
                            </button>
                        </div>
                        <div id="headerPreview" class="image-preview">
                            <?php if (!empty($settings['site']['headerImage'])): ?>
                                <img src="<?= htmlspecialchars($settings['site']['headerImage']) ?>" alt="Header Preview">
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="footerText">Footer-Text</label>
                        <textarea name="footerText" id="footerText" rows="3" 
                                  placeholder="z.B. © 2026 Gemeinde..."><?= htmlspecialchars($settings['site']['footerText'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3>Design</h3>
                    
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <label for="bgColor">Hintergrundfarbe</label>
                            <input type="color" name="backgroundColor" id="bgColor" 
                                   value="<?= htmlspecialchars($settings['theme']['backgroundColor'] ?? '#f5f5f5') ?>">
                        </div>
                        <div class="form-group">
                            <label for="primaryColor">Akzent 1 (Seitentitel)</label>
                            <input type="color" name="primaryColor" id="primaryColor" 
                                   value="<?= htmlspecialchars($settings['theme']['primaryColor'] ?? '#667eea') ?>">
                        </div>
                    </div>
                    
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <label for="accentColor2">Akzent 2</label>
                            <input type="color" name="accentColor2" id="accentColor2" 
                                   value="<?= htmlspecialchars($settings['theme']['accentColor2'] ?? '#48bb78') ?>">
                        </div>
                        <div class="form-group">
                            <label for="accentColor3">Akzent 3</label>
                            <input type="color" name="accentColor3" id="accentColor3" 
                                   value="<?= htmlspecialchars($settings['theme']['accentColor3'] ?? '#ed8936') ?>">
                        </div>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeSettingsModal()">
                        Abbrechen
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- File Browser Modal -->
    <div class="modal" id="fileBrowserModal">
        <div class="modal-backdrop" onclick="closeFileBrowser()"></div>
        <div class="modal-content modal-wide">
            <div class="modal-header">
                <h2>📁 Datei auswählen</h2>
                <button class="modal-close" onclick="closeFileBrowser()">&times;</button>
            </div>
            <div class="file-browser">
                <div class="file-browser-tabs">
                    <button class="tab active" data-type="images" onclick="loadFiles('images')">
                        🖼️ Bilder
                    </button>
                    <button class="tab" data-type="downloads" onclick="loadFiles('downloads')">
                        📄 Downloads
                    </button>
                </div>
                <div class="file-browser-list" id="fileList">
                    <!-- Dateien werden per JS geladen -->
                </div>
                <div class="file-browser-upload">
                    <input type="file" id="fileBrowserUpload" style="display:none" onchange="uploadFile(this)">
                    <button class="btn btn-primary btn-upload" id="uploadBtn" onclick="document.getElementById('fileBrowserUpload').click()">
                        + Neues Bild hochladen
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Notifications -->
    <div class="toast-container" id="toastContainer"></div>
    
    <!-- Initial Data -->
    <script>
        window.INITIAL_DATA = {
            tiles: <?= json_encode($tiles) ?>,
            tileTypes: <?= json_encode($tileTypes) ?>,
            settings: <?= json_encode($settings) ?>,
            sessionExpiry: <?= $remainingTime ?>,
            apiUrl: 'api/endpoints.php',
            csrfToken: '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>'
        };
    </script>
    <script src="../assets/js/editor.js"></script>
</body>
</html>
