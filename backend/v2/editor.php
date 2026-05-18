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

$bootstrapMode = 'page';
$bootstrapServices = [
    'AuthService',
    'TileService',
    'StorageService',
    'GeneratorService',
    'ConfigService',
    'SecurityHelper',
    'BackupService',
];
$bootstrapMissingConfigRedirect = '../setup.php';
$bootstrap = require __DIR__ . '/../bootstrap.php';

$container = $bootstrap['container'];

// Auth prüfen
$auth = $container->authService();
if (!$auth->isAuthenticated()) {
    header('Location: ../login.php');
    exit;
}

// CSRF Token
$csrfToken = $_SESSION['csrf_token'] ?? '';

// Daten laden
$tileService = $container->tileService();
$settingsStorage = $container->storage('settings.json');
$settings = $settingsStorage->read();
$configService = $container->configService();
$settings['legal'] = SecurityHelper::normalizeLegalSettings($settings['legal'] ?? []);
$settings['system']['mailFromAddress'] = $configService->getMailFromAddress(
    $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '',
    $_SESSION['auth_email'] ?? ''
);
$generator = $container->generatorService();
$backupService = $container->backupService();
$backupCount = count($backupService->listBackups());
$backupCountLabel = $backupCount === 1 ? '1 Sicherung' : $backupCount . ' Sicherungen';
$quickRestoreView = $backupService->getQuickRestoreViewData();
$quickRestoreAvailable = !empty($quickRestoreView['available']);
$quickRestorePublishedLabel = (string)($quickRestoreView['publishedLabel'] ?? '');
$quickRestoreTargetLabel = (string)($quickRestoreView['targetLabel'] ?? '');
$footerMarkup = $generator->renderFooterMarkup($settings);
$legalModalMarkup = $generator->renderLegalModalMarkup($settings);

// Alle Canvas-Abschnitte als HTML rendern (Server-Side Rendering für den Editor)
// PARALLEL RENDER CONTRACT:
// Erwartet die Struktur aus GeneratorService::renderCanvasSections()
// gemaess RenderContract::CANVAS_SECTION_KEYS.
// Änderungen an Wrappern, Section-Struktur oder Metadaten müssen auch in assets/js/v2/canvas.js geprüft werden.
// Der ausführbare Contract-Anker dafür liegt in tests/test_section_layout.php.
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

$v2Modules = ['state', 'api-client', 'media-picker', 'edit-modal', 'settings', 'context-menu', 'canvas', 'drag-drop', 'insert'];
$v2LegacyModules = [];
$v2ScriptUrls = [];

foreach (array_merge(['boot'], $v2Modules) as $module) {
    $filePath = __DIR__ . "/../../assets/js/v2/{$module}.js";
    $version = file_exists($filePath) ? filemtime($filePath) : time();
    $v2ScriptUrls[$module] = "../../assets/js/v2/{$module}.js?v={$version}";
}

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
    <title>Editor - <?= htmlspecialchars($settings['site']['title'] ?? 'Info-Hub') ?></title>
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
            <h1 class="v2-logo">✏️ Editor</h1>
            <span class="v2-site-name"><?= $siteTitle ?></span>
            <?php if ($indexExists): ?>
                <div class="v2-page-menu">
                    <a href="../../index.html" target="_blank" class="v2-btn v2-btn-secondary v2-page-menu__link" title="Veröffentlichte Seite anzeigen">
                        <span class="v2-btn-glyph">🌐</span>
                        <span class="v2-btn-label">Seite</span>
                    </a>
                    <details class="v2-page-menu__dropdown">
                        <summary class="v2-btn v2-btn-secondary v2-btn-icon v2-page-menu__toggle" title="Seiten-Menü" aria-label="Seiten-Menü öffnen">
                            ▾
                        </summary>
                        <div class="v2-dropdown-menu v2-page-menu__menu">
                            <div class="v2-dropdown-item v2-dropdown-item--static">
                                <span class="v2-dropdown-label">Zuletzt generiert</span>
                                <span class="v2-dropdown-value" id="v2PublishedGeneratedValue"><?= date('d.m.Y H:i', $lastGenerated) ?></span>
                            </div>
                            <a href="../backup.php" class="v2-dropdown-item">
                                <span class="v2-dropdown-label">Backup-Verwaltung</span>
                                <span class="v2-dropdown-note v2-dropdown-note--muted" id="v2BackupCountNote"><?= htmlspecialchars($backupCountLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            </a>
                            <button
                                type="button"
                                class="v2-dropdown-item v2-dropdown-item--button v2-dropdown-item--warning"
                                id="v2QuickRestoreItem"
                                data-v2-action="quickRestoreLastPublish"
                                <?= $quickRestoreAvailable ? '' : 'hidden disabled' ?>
                            >
                                <span class="v2-dropdown-label">Letzte Veröffentlichung zurücknehmen</span>
                                <span class="v2-dropdown-note v2-dropdown-note--warning" id="v2QuickRestoreNote"><?= htmlspecialchars($quickRestoreTargetLabel !== '' ? ('Rollback auf Stand ' . $quickRestoreTargetLabel . ' · nur für die letzte Veröffentlichung dieser Session') : '', ENT_QUOTES, 'UTF-8') ?></span>
                            </button>
                        </div>
                    </details>
                </div>
            <?php endif; ?>
            <?php if (!$indexExists): ?>
                <span class="v2-toolbar-note">⚠️ Noch nicht veröffentlicht</span>
            <?php endif; ?>
        </div>
        <div class="v2-toolbar-right">
            <?= SecurityHelper::renderSecurityBadge() ?>
            <div class="v2-session-timer" id="sessionTimer" title="Verbleibende Session-Zeit">
                🕐 <span id="sessionTimeDisplay">--</span>
            </div>
            <button type="button" class="v2-btn v2-btn-secondary v2-btn-icon" data-v2-action="openSettings" title="Einstellungen">
                ⚙️
            </button>
            <button type="button" class="v2-btn v2-btn-secondary" data-v2-action="openPreview" title="Vorschau">
                <span class="v2-btn-glyph">👁️</span>
                <span class="v2-btn-label">Vorschau</span>
            </button>
            <button type="button" class="v2-btn v2-btn-primary" data-v2-action="publish" title="Veröffentlichen">
                <span class="v2-btn-glyph">🚀</span>
                <span class="v2-btn-label">Veröffentlichen</span>
            </button>
            <button type="button" class="v2-btn v2-btn-secondary v2-btn-icon" data-v2-action="logout" title="Abmelden">
                🚪
            </button>
        </div>
    </header>
    
    <!-- ===== WYSIWYG Canvas ===== -->
    <main class="v2-canvas-wrapper">
        <!-- Canvas simuliert die echte Seite -->
        <div id="wysiwyg-canvas" class="v2-canvas">
            
            <!-- Header (aus Settings) -->
            <div class="v2-canvas-header v2-editable-region" id="canvasHeader" data-editor-region="header" data-v2-action="openSettings" role="button" tabindex="0" title="Klicken um Header zu bearbeiten">
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
                        <button type="button" class="v2-add-header-btn" data-v2-action="openSettings">+ Header hinzufügen</button>
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
                <button type="button" class="v2-add-tile-btn" data-v2-action="addTile">
                    <span class="v2-add-icon">+</span>
                    <span>Neue Kachel</span>
                </button>
            </div>
            
            <!-- Footer (aus Settings) -->
            <div class="v2-canvas-footer v2-editable-region" id="canvasFooter" data-editor-region="footer" data-v2-action="openSettings" role="button" tabindex="0" title="Klicken um Footer zu bearbeiten">
                <?php if ($footerMarkup !== ''): ?>
                    <?= $footerMarkup ?>
                    <div class="v2-region-edit-hint">✏️ Footer bearbeiten</div>
                <?php else: ?>
                    <div class="v2-empty-footer">
                        <button type="button" class="v2-add-footer-btn" data-v2-action="openSettings">+ Footer hinzufügen</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?= $legalModalMarkup ?>
    
    <!-- ===== Tile Selection Toolbar (floating) ===== -->
    <div id="tileToolbar" class="v2-tile-toolbar" style="display: none;">
        <button type="button" class="v2-tb-btn" data-v2-action="editSelectedTile" title="Bearbeiten">✏️</button>
        <div class="v2-tb-separator" data-tb-group="color"></div>
        <select id="tbSize" class="v2-tb-select" data-tb-group="layout" data-v2-change="size" title="Größe">
            <option value="small">Klein</option>
            <option value="medium">Mittel</option>
            <option value="large">Groß</option>
            <option value="full">Voll</option>
        </select>
        <select id="tbStyle" class="v2-tb-select" data-tb-group="layout" data-v2-change="style" title="Stil">
            <option value="card">Card</option>
            <option value="flat">Flat</option>
        </select>
        <select id="tbColor" class="v2-tb-select" data-tb-group="color" data-v2-change="color" title="Farbe">
            <option value="default">Standard</option>
            <option value="white">Weiß</option>
            <option value="accent1">Akzent 1</option>
            <option value="accent2">Akzent 2</option>
            <option value="accent3">Akzent 3</option>
        </select>
        <div class="v2-tb-separator"></div>
        <button type="button" class="v2-tb-btn" id="tbMoreBtn" data-v2-action="openContextMenu" title="Mehr (Sichtbarkeit, Zeitsteuerung ...)">&#8943;</button>
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
            backupCount: <?= $backupCount ?>,
            quickRestore: <?= json_encode([
                'available' => $quickRestoreAvailable,
                'publishedLabel' => $quickRestorePublishedLabel,
                'targetLabel' => $quickRestoreTargetLabel
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            debugMode: <?= (defined('DEBUG_MODE') && DEBUG_MODE) ? 'true' : 'false' ?>,
            // Pre-rendered section HTML from server
            renderContractVersion: <?= json_encode(RenderContract::CANVAS_SECTION_VERSION, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            renderedSections: <?= json_encode($renderedSections, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            // Tile type metadata for add/edit
            tileTypes: <?= json_encode($tileTypesWithMeta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            // Raw tile data (for editing)
            tiles: <?= json_encode($tileService->getTiles(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            // Settings
            settings: <?= json_encode($settings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
            ,
            v2LegacyModules: <?= json_encode($v2LegacyModules, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            v2ScriptUrls: <?= json_encode($v2ScriptUrls, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
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
    
    <!-- V2 Editor Bootstrap -->
    <script type="module" data-v2-entry="module-boot" src="<?= htmlspecialchars($v2ScriptUrls['boot'], ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
