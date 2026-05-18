<?php
/**
 * WYSIWYG Editor v2 - Visueller Tile-Editor
 * 
 * Zeigt Tiles im echten CSS Grid, gerendert durch PHP (Single Source of Truth).
 * Kein separater JS-Renderer pro Tile-Typ nötig.
 * 
 * Architektur:
 * - PHP rendert die Canvas-Abschnitte via GeneratorService::renderCanvasSections()
 * - JS platziert das HTML im Canvas und legt Editor-Chrome drumherum
 * - Shared CSS + Tile-CSS sorgen für pixelgenaue Vorschau
 */

// Config laden
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    header('Location: ../setup.php');
    exit;
}

require_once __DIR__ . '/../core/AuthService.php';
require_once __DIR__ . '/../core/TileService.php';
require_once __DIR__ . '/../core/StorageService.php';
require_once __DIR__ . '/../core/GeneratorService.php';
require_once __DIR__ . '/../core/ConfigService.php';
require_once __DIR__ . '/../core/SecurityHelper.php';

// Auth prüfen
$auth = new AuthService();
if (!$auth->isAuthenticated()) {
    header('Location: ../login.php');
    exit;
}

// CSRF Token
$csrfToken = $_SESSION['csrf_token'] ?? '';

// Daten laden
$tileService = new TileService();
$settingsStorage = new StorageService('settings.json');
$settings = $settingsStorage->read();
$settings['legal'] = SecurityHelper::normalizeLegalSettings($settings['legal'] ?? []);
$configService = new ConfigService(__DIR__ . '/../config.php');
$settings['system']['mailFromAddress'] = $configService->getMailFromAddress(
    $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '',
    $_SESSION['auth_email'] ?? ''
);
$generator = new GeneratorService();
$footerMarkup = $generator->renderFooterMarkup($settings);
$legalModalMarkup = $generator->renderLegalModalMarkup($settings);

// Alle Canvas-Abschnitte als HTML rendern (Server-Side Rendering für den Editor)
// PARALLEL RENDER CONTRACT:
// Erwartet die Struktur aus GeneratorService::renderCanvasSections().
// Änderungen an Wrappern, Section-Struktur oder Metadaten müssen auch in assets/js/v2/canvas.js geprüft werden.
$renderedSections = $generator->renderCanvasSections();

// Canvas CSS (shared + tile-spezifisch)
$canvasCSS = $generator->getCanvasCSS();

// Canvas JS (tile-spezifische Funktionen: lightbox, countdown, etc.)
$canvasJS = $generator->getCanvasJS();

// Tile-Typen mit Metadaten (für Add/Edit Modals)
$tileTypesWithMeta = $tileService->getAvailableTypesWithMeta();

// Session-Werte
$sessionTimeout = $auth->getSessionTimeout();
$sessionWarning = $auth->getSessionWarningBefore();
$remainingTime = $auth->getRemainingSessionTime();

// Dynamische CSS-Variablen aus Settings
$bgColor = htmlspecialchars($settings['theme']['backgroundColor'] ?? '#f5f5f5');
$accentColor = htmlspecialchars($settings['theme']['accentColor'] ?? $settings['theme']['primaryColor'] ?? '#667eea');
$accentColor2 = htmlspecialchars($settings['theme']['accentColor2'] ?? '#48bb78');
$accentColor3 = htmlspecialchars($settings['theme']['accentColor3'] ?? '#ed8936');

// Header Info
$siteTitle = htmlspecialchars($settings['site']['title'] ?? '');
$headerImage = $settings['site']['headerImage'] ?? null;
$headerFocusPoint = htmlspecialchars($settings['site']['headerFocusPoint'] ?? 'center center');

// Generierte Seite Info
$indexExists = file_exists(__DIR__ . '/../../index.html');
$lastGenerated = $indexExists ? filemtime(__DIR__ . '/../../index.html') : null;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WYSIWYG Editor - <?= htmlspecialchars($settings['site']['title'] ?? 'Info-Hub') ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>✏️</text></svg>">
    
    <!-- Canvas CSS (Shared + Tile-spezifisch) wird inline geladen für pixelgenaue Vorschau -->
    <style id="canvasStyles">
        /* Dynamische Theme-Variablen aus Settings */
        :root {
            --bg-color: <?= $bgColor ?>;
            --accent-color: <?= $accentColor ?>;
            --accent-color-2: <?= $accentColor2 ?>;
            --accent-color-3: <?= $accentColor3 ?>;
        }
        
        /* Shared CSS + Tile CSS - angewendet innerhalb des Canvas */
        <?= $canvasCSS ?>
    </style>
    
    <!-- Editor Chrome CSS - NACH Canvas CSS, damit es body/html überschreiben kann -->
    <link rel="stylesheet" href="../../assets/css/editor-v2.css">
</head>
<body>
    <!-- ===== Editor Toolbar ===== -->
    <header class="v2-toolbar">
        <div class="v2-toolbar-left">
            <h1 class="v2-logo">✏️ WYSIWYG</h1>
            <span class="v2-site-name"><?= $siteTitle ?></span>
            <?php if ($indexExists): ?>
                <a href="../../index.html" target="_blank" class="v2-published-link" title="Veröffentlichte Seite anzeigen">
                    🌐 Seite
                </a>
                <span class="v2-last-generated">
                    Zuletzt: <?= date('d.m. H:i', $lastGenerated) ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="v2-toolbar-right">
            <div class="v2-session-timer" id="sessionTimer" title="Verbleibende Session-Zeit">
                🕐 <span id="sessionTimeDisplay">--</span>
            </div>
            <button type="button" class="v2-btn v2-btn-icon" onclick="V2.openSettings()" title="Einstellungen">
                ⚙️
            </button>
            <a href="../editor.php" class="v2-btn v2-btn-secondary" title="Zum klassischen Editor">
                📝 Classic
            </a>
            <button type="button" class="v2-btn v2-btn-secondary" onclick="V2.openPreview()" title="Vorschau">
                👁️ Vorschau
            </button>
            <button type="button" class="v2-btn v2-btn-primary" onclick="V2.publish()" title="Veröffentlichen">
                🚀 Veröffentlichen
            </button>
            <button type="button" class="v2-btn v2-btn-icon" onclick="V2.logout()" title="Abmelden">
                🚪
            </button>
        </div>
    </header>
    
    <!-- ===== WYSIWYG Canvas ===== -->
    <main class="v2-canvas-wrapper">
        <!-- Canvas simuliert die echte Seite -->
        <div id="wysiwyg-canvas" class="v2-canvas">
            
            <!-- Header (aus Settings) -->
            <div class="v2-canvas-header v2-editable-region" id="canvasHeader" data-editor-region="header" onclick="V2.openSettings()" title="Klicken um Header zu bearbeiten">
                <?php if ($headerImage): ?>
                    <header class="site-header">
                        <div class="header-image">
                            <img src="<?= htmlspecialchars($headerImage) ?>" alt="" style="object-position: <?= $headerFocusPoint ?>;">
                        </div>
                        <?php if (!empty($siteTitle)): ?>
                            <h1 class="site-title"><?= $siteTitle ?></h1>
                        <?php endif; ?>
                    </header>
                    <div class="v2-region-edit-hint">✏️ Header bearbeiten</div>
                <?php elseif (!empty($siteTitle)): ?>
                    <header class="site-header site-header--minimal">
                        <h1 class="site-title"><?= $siteTitle ?></h1>
                    </header>
                    <div class="v2-region-edit-hint">✏️ Header bearbeiten</div>
                <?php else: ?>
                    <div class="v2-empty-header">
                        <button class="v2-add-header-btn" onclick="V2.openSettings()">+ Header hinzufügen</button>
                    </div>
                <?php endif; ?>
            </div>
            
              <!-- Section-Layout (hier werden die server-gerenderten Sections platziert) -->
            <!-- PARALLEL DOM CONTRACT:
                 canvas.js positioniert Editor-Chrome relativ zu dieser Render-Zone.
                  Die publizierte Section-Struktur bleibt hier erhalten; nur Marker/Selection kommen editor-seitig dazu. -->
              <div class="page-sections" id="tileGrid">
                <!-- Wird von canvas.js befüllt -->
            </div>
            
            <!-- Add Tile Button (im Grid-Context) -->
            <div class="v2-add-tile-area" id="addTileArea">
                <button class="v2-add-tile-btn" onclick="V2.addTile()">
                    <span class="v2-add-icon">+</span>
                    <span>Neue Kachel</span>
                </button>
            </div>
            
            <!-- Footer (aus Settings) -->
            <div class="v2-canvas-footer v2-editable-region" id="canvasFooter" data-editor-region="footer" onclick="V2.openSettings()" title="Klicken um Footer zu bearbeiten">
                <?php if ($footerMarkup !== ''): ?>
                    <?= $footerMarkup ?>
                    <div class="v2-region-edit-hint">✏️ Footer bearbeiten</div>
                <?php else: ?>
                    <div class="v2-empty-footer">
                        <button class="v2-add-footer-btn" onclick="V2.openSettings()">+ Footer hinzufügen</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?= $legalModalMarkup ?>
    
    <!-- ===== Tile Selection Toolbar (floating) ===== -->
    <div id="tileToolbar" class="v2-tile-toolbar" style="display: none;">
        <button class="v2-tb-btn" onclick="V2.editSelectedTile()" title="Bearbeiten">✏️</button>
        <div class="v2-tb-separator" data-tb-group="color"></div>
        <select id="tbSize" class="v2-tb-select" data-tb-group="layout" onchange="V2.changeSize(this.value)" title="Größe">
            <option value="small">Klein</option>
            <option value="medium">Mittel</option>
            <option value="large">Groß</option>
            <option value="full">Voll</option>
        </select>
        <select id="tbStyle" class="v2-tb-select" data-tb-group="layout" onchange="V2.changeStyle(this.value)" title="Stil">
            <option value="card">Card</option>
            <option value="flat">Flat</option>
        </select>
        <select id="tbColor" class="v2-tb-select" data-tb-group="color" onchange="V2.changeColor(this.value)" title="Farbe">
            <option value="default">Standard</option>
            <option value="white">Weiß</option>
            <option value="accent1">Akzent 1</option>
            <option value="accent2">Akzent 2</option>
            <option value="accent3">Akzent 3</option>
        </select>
        <div class="v2-tb-separator"></div>
        <button class="v2-tb-btn" id="tbMoreBtn" onclick="V2.openContextMenu()" title="Mehr (Sichtbarkeit, Zeitsteuerung ...)">&#8943;</button>
    </div>
    
    <!-- ===== Toast Container ===== -->
    <div id="toastContainer" class="v2-toast-container"></div>
    
    <!-- ===== Configuration from PHP ===== -->
    <script>
        window.V2_CONFIG = {
            csrfToken: '<?= $csrfToken ?>',
            apiUrl: '../api/endpoints.php',
            sessionTimeout: <?= $sessionTimeout ?>,
            sessionWarning: <?= $sessionWarning ?>,
            sessionRemaining: <?= $remainingTime ?>,
            debugMode: <?= (defined('DEBUG_MODE') && DEBUG_MODE) ? 'true' : 'false' ?>,
            // Pre-rendered section HTML from server
            renderedSections: <?= json_encode($renderedSections, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            // Tile type metadata for add/edit
            tileTypes: <?= json_encode($tileTypesWithMeta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            // Raw tile data (for editing)
            tiles: <?= json_encode($tileService->getTiles(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            // Settings
            settings: <?= json_encode($settings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
        };
        
        if (window.V2_CONFIG.debugMode) {
            console.log('V2 WYSIWYG Editor loaded');
            console.log('Config:', window.V2_CONFIG);
            console.log('Rendered sections:', window.V2_CONFIG.renderedSections.length);
        }
    </script>
    
    <!-- Tile-spezifisches JS (Lightbox, Countdown, Accordion, etc.) -->
    <script id="canvasScripts">
        <?= $canvasJS ?>
    </script>
    
    <!-- V2 Editor Module -->
    <?php
    $v2Modules = ['state', 'api-client', 'media-picker', 'edit-modal', 'settings', 'context-menu', 'canvas', 'drag-drop', 'insert'];
    foreach ($v2Modules as $module):
        $filePath = __DIR__ . "/../../assets/js/v2/{$module}.js";
        $version = file_exists($filePath) ? filemtime($filePath) : time();
    ?>
    <script src="../../assets/js/v2/<?= $module ?>.js?v=<?= $version ?>"></script>
    <?php endforeach; ?>
</body>
</html>
