<?php
/**
 * GeneratorService - Statische HTML-Generierung
 * 
 * Generiert index.html aus Tiles und Settings.
 * Nutzt Templates für konsistentes Layout.
 */

require_once __DIR__ . '/LogService.php';
require_once __DIR__ . '/StorageService.php';
require_once __DIR__ . '/TileService.php';
require_once __DIR__ . '/../tiles/_registry.php';

class GeneratorService {
    
    private StorageService $settingsStorage;
    private TileService $tileService;
    private string $outputPath;
    
    public function __construct() {
        $this->settingsStorage = new StorageService('settings.json');
        $this->tileService = new TileService();
        $this->outputPath = __DIR__ . '/../../index.html';
    }
    
    /**
     * Generiert die statische index.html
     * 
     * @return array ['success' => bool, 'message' => string]
     */
    public function generate(): array {
        global $TILE_TYPES;
        
        LogService::info('GeneratorService', 'Starting HTML generation');
        
        try {
            // 1. Daten laden
            $settings = $this->settingsStorage->read();
            $tiles = $this->tileService->getTiles();
            
            // 2. Tiles rendern
            $tilesHtml = $this->renderTiles($tiles);
            
            // 3. Template laden und füllen
            $html = $this->renderPage($settings, $tilesHtml);
            
            // 4. Backup der alten Datei
            if (file_exists($this->outputPath)) {
                $backupPath = __DIR__ . '/../archive/index_' . date('Y-m-d_H-i-s') . '.html';
                copy($this->outputPath, $backupPath);
            }
            
            // 5. Neue Datei atomar schreiben (temp + rename)
            $tmpPath = $this->outputPath . '.tmp.' . getmypid();
            $result = file_put_contents($tmpPath, $html);
            
            if ($result === false) {
                @unlink($tmpPath);
                throw new Exception('Konnte index.html nicht schreiben');
            }
            
            if (!rename($tmpPath, $this->outputPath)) {
                @unlink($tmpPath);
                throw new Exception('Konnte index.html nicht finalisieren');
            }
            
            LogService::success('GeneratorService', 'HTML generated', [
                'tiles' => count($tiles),
                'bytes' => $result
            ]);
            
            return [
                'success' => true,
                'message' => 'Seite erfolgreich generiert',
                'tilesCount' => count($tiles)
            ];
            
        } catch (Exception $e) {
            LogService::error('GeneratorService', 'Generation failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Generierung fehlgeschlagen: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Sammelt CSS von allen registrierten Tile-Typen
     */
    private function collectTileCSS(): string {
        global $TILE_TYPES;
        
        $css = "\n        /* ===== TILE-SPEZIFISCHE STYLES ===== */\n";
        
        foreach ($TILE_TYPES as $type => $class) {
            $instance = new $class();
            $tileCSS = $instance->getCSS();
            if (!empty($tileCSS)) {
                $css .= $tileCSS;
            }
        }
        
        return $css;
    }
    
    /**
     * Sammelt JavaScript von allen registrierten Tile-Typen
     */
    private function collectTileJS(): string {
        global $TILE_TYPES;
        
        $js = "\n        // ===== TILE-SPEZIFISCHES JAVASCRIPT =====\n";
        
        foreach ($TILE_TYPES as $type => $class) {
            $instance = new $class();
            $tileJS = $instance->getJS();
            if (!empty($tileJS)) {
                $js .= $tileJS;
            }
        }
        
        return $js;
    }
    
    /**
     * Sammelt Init-Funktionen von allen Tile-Typen für DOMContentLoaded
     */
    private function collectTileInitCalls(): string {
        global $TILE_TYPES;
        
        $calls = [];
        
        foreach ($TILE_TYPES as $type => $class) {
            $instance = new $class();
            $initFn = $instance->getInitFunction();
            if (!empty($initFn)) {
                $calls[] = $initFn . '();';
            }
        }
        
        if (empty($calls)) {
            return '';
        }
        
        return "\n        // Tile-Init-Funktionen\n        document.addEventListener('DOMContentLoaded', function() {\n            " . 
               implode("\n            ", $calls) . 
               "\n        });\n";
    }
    
    /**
     * Rendert eine einzelne Tile als HTML (inkl. Wrapper-Div)
     * 
     * Wird sowohl intern von renderTiles() als auch extern vom
     * WYSIWYG-Editor genutzt (Single Source of Truth für HTML-Output).
     * 
     * @param array $tile Komplettes Tile-Objekt (type, size, style, colorScheme, data, ...)
     * @param bool $applyScheduledVisibility Wenn false, werden zeitgesteuerte Tiles im Editor nicht versteckt
     * @return string|null HTML-String oder null bei unbekanntem Typ
     */
    public function renderSingleTile(array $tile, bool $applyScheduledVisibility = true): ?string {
        global $TILE_TYPES;
        
        $type = $tile['type'] ?? '';
        
        if (!isset($TILE_TYPES[$type])) {
            LogService::warning('GeneratorService', 'Unknown tile type', ['type' => $type]);
            return null;
        }
        
        $class = $TILE_TYPES[$type];
        $instance = new $class();
        
        // Tile-Wrapper mit gemeinsamen Klassen
        $size = ($type === 'separator') ? 'full' : htmlspecialchars($tile['size'] ?? 'medium');
        $style = htmlspecialchars($tile['style'] ?? 'card');
        $colorScheme = htmlspecialchars($tile['colorScheme'] ?? 'default');
        $id = htmlspecialchars($tile['id'] ?? '');
        
        // Zusätzliche Klassen vom Tile (z.B. fullRow bei Akkordeon)
        $extraClasses = '';
        if (method_exists($instance, 'getWrapperClasses')) {
            $wrapperClasses = $instance->getWrapperClasses($tile['data'] ?? []);
            if (!empty($wrapperClasses)) {
                $extraClasses = ' ' . implode(' ', array_map('htmlspecialchars', $wrapperClasses));
            }
        }
        
        // Zeitsteuerungs-Attribute
        $scheduleAttrs = $this->getScheduleAttributes($tile);
        $hiddenStyle = ($applyScheduledVisibility && $scheduleAttrs !== '' && !$this->isTileVisibleBySchedule($tile))
            ? ' style="display:none;"'
            : '';
        
        $html = "<div class=\"tile tile-{$type} size-{$size} style-{$style} color-{$colorScheme}{$extraClasses}\"{$scheduleAttrs}{$hiddenStyle} data-tile-id=\"{$id}\">\n";
        $html .= $instance->render($tile['data'] ?? []);
        $html .= "</div>\n";
        
        return $html;
    }
    
    /**
     * Rendert alle Tiles als Array mit ID → HTML Mapping
     * 
     * Für den WYSIWYG-Editor: liefert das gerenderte HTML jeder Tile,
     * sodass der Editor es direkt in den Canvas platzieren kann.
     * 
     * @return array [{id, type, html, size, style, colorScheme}, ...]
     */
    public function renderAllTilesHtml(): array {
        $tiles = $this->tileService->getTiles();
        $result = [];
        
        foreach ($tiles as $tile) {
            $html = $this->renderSingleTile($tile, false);
            if ($html !== null) {
                $result[] = [
                    'id' => $tile['id'],
                    'type' => $tile['type'],
                    'html' => $html,
                    'size' => $tile['size'] ?? 'medium',
                    'style' => $tile['style'] ?? 'card',
                    'colorScheme' => $tile['colorScheme'] ?? 'default',
                    'position' => $tile['position'] ?? 0,
                    'visible' => $tile['visible'] ?? true
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Gibt CSS zurück, das der WYSIWYG-Editor braucht (shared + tile-spezifisch)
     * 
     * @return string Komplettes CSS für den Canvas
     */
    public function getCanvasCSS(): string {
        $sharedCSS = $this->loadSharedCSS();
        $tileCSS = $this->collectTileCSS();
        return $sharedCSS . $tileCSS;
    }
    
    /**
     * Gibt JavaScript zurück, das für tile-spezifische Funktionalität nötig ist
     * 
     * @return string JS-Code für Tile-Funktionen (Lightbox, Countdown, etc.)
     */
    public function getCanvasJS(): string {
        $tileJS = $this->collectTileJS();
        $initCalls = $this->collectTileInitCalls();
        $contrastJS = $this->getContrastJS();
        return $contrastJS . $tileJS . $initCalls;
    }

    /**
     * Gibt das adjustTextContrast()-JS zurück (WCAG-konformer Kontrast für Akzentfarben).
     * Wird sowohl in der generierten Seite als auch im WYSIWYG-Editor verwendet.
     */
    public function getContrastJS(): string {
        return <<<'JS'
        // Automatischer Text-Kontrast (WCAG-konform)
        function adjustTextContrast() {
            function getLuminance(color) {
                let r, g, b;
                color = (color || '').trim();
                const rgbMatch = color.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
                if (rgbMatch) {
                    r = parseInt(rgbMatch[1]); g = parseInt(rgbMatch[2]); b = parseInt(rgbMatch[3]);
                    return (0.299 * r + 0.587 * g + 0.114 * b);
                }
                let hex = color.replace('#', '');
                if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
                if (hex.length !== 6) return 128;
                r = parseInt(hex.substr(0, 2), 16);
                g = parseInt(hex.substr(2, 2), 16);
                b = parseInt(hex.substr(4, 2), 16);
                if (isNaN(r) || isNaN(g) || isNaN(b)) return 128;
                return (0.299 * r + 0.587 * g + 0.114 * b);
            }
            function getCSSVar(name) {
                return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            }
            document.querySelectorAll('.tile.color-accent1, .tile.color-accent2, .tile.color-accent3').forEach(tile => {
                let bgColor = '#ffffff';
                if (tile.classList.contains('color-accent1')) {
                    bgColor = getCSSVar('--accent-color');
                } else if (tile.classList.contains('color-accent2')) {
                    bgColor = getCSSVar('--accent-color-2');
                } else if (tile.classList.contains('color-accent3')) {
                    bgColor = getCSSVar('--accent-color-3');
                }
                const luminance = getLuminance(bgColor);
                if (luminance > 150) {
                    tile.style.color = '#333333';
                    tile.querySelectorAll('h3, p').forEach(el => el.style.color = '#333333');
                } else {
                    tile.style.color = '#ffffff';
                    tile.querySelectorAll('h3, p').forEach(el => el.style.color = '#ffffff');
                }
            });
        }

JS;
    }
    
    /**
     * Rendert alle Tiles
     */
    private function renderTiles(array $tiles): string {
        $html = '';
        
        foreach ($tiles as $tile) {
            // Manuell versteckte Tiles komplett überspringen (nicht im HTML)
            if (isset($tile['visible']) && $tile['visible'] === false) {
                LogService::debug('GeneratorService', 'Skipping hidden tile', ['id' => $tile['id'] ?? 'unknown']);
                continue;
            }
            
            $tileHtml = $this->renderSingleTile($tile);
            if ($tileHtml !== null) {
                $html .= $tileHtml;
            }
        }
        
        return $html;
    }
    
    /**
     * Generiert data-Attribute für Zeitsteuerung
     */
    private function getScheduleAttributes(array $tile): string {
        if (!isset($tile['visibilitySchedule']) || empty($tile['visibilitySchedule'])) {
            return '';
        }
        
        $schedule = $tile['visibilitySchedule'];
        $attrs = '';
        
        if (!empty($schedule['showFrom'])) {
            $attrs .= ' data-show-from="' . htmlspecialchars($schedule['showFrom']) . '"';
        }
        
        if (!empty($schedule['showUntil'])) {
            $attrs .= ' data-show-until="' . htmlspecialchars($schedule['showUntil']) . '"';
        }
        
        return $attrs;
    }
    
    /**
     * Lädt und kombiniert alle shared CSS-Dateien
     */
    private function loadSharedCSS(): string {
        $sharedDir = __DIR__ . '/../../assets/css/shared/';
        $files = ['variables.css', 'base.css', 'grid.css', 'tiles.css', 'header.css', 'footer.css', 'components.css'];
        
        $css = '';
        foreach ($files as $file) {
            $path = $sharedDir . $file;
            if (file_exists($path)) {
                $css .= "/* {$file} */\n" . file_get_contents($path) . "\n";
            } else {
                LogService::warning('GeneratorService', 'Shared CSS file missing', ['file' => $path]);
            }
        }
        
        return $css;
    }

    /**
     * Baut die Narrow-Layout-Konfiguration mit defensiven Defaults.
     */
    private function getNarrowConfig(array $theme): array {
        $isValidHexColor = static function($value, string $fallback): string {
            return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? $value : $fallback;
        };

        $mode = in_array(($theme['narrowBackgroundMode'] ?? ''), ['solid', 'gradient', 'image'], true)
            ? $theme['narrowBackgroundMode']
            : 'solid';
        $display = in_array(($theme['narrowBackgroundImageDisplay'] ?? ''), ['cover', 'tile'], true)
            ? $theme['narrowBackgroundImageDisplay']
            : 'cover';
        $motion = in_array(($theme['narrowBackgroundImageMotion'] ?? ''), ['fixed', 'parallax'], true)
            ? $theme['narrowBackgroundImageMotion']
            : 'fixed';
        $angle = (int)($theme['narrowGradientAngle'] ?? 180);
        if ($angle < 0 || $angle > 360) {
            $angle = 180;
        }
        $overlayOpacity = (int)($theme['narrowBackgroundOverlayOpacity'] ?? 35);
        if ($overlayOpacity < 0 || $overlayOpacity > 100) {
            $overlayOpacity = 35;
        }

        $image = is_string($theme['narrowBackgroundImage'] ?? null) ? trim($theme['narrowBackgroundImage']) : '';
        if (!preg_match('#^/backend/media/[a-z0-9/_\-.]+$#i', $image)) {
            $image = '';
        }

        return [
            'mode' => $mode,
            'color' => $isValidHexColor($theme['narrowBackgroundColor'] ?? null, '#1a1a2e'),
            'gradientColor1' => $isValidHexColor($theme['narrowGradientColor1'] ?? null, '#1a1a2e'),
            'gradientColor2' => $isValidHexColor($theme['narrowGradientColor2'] ?? null, '#16213e'),
            'gradientAngle' => $angle,
            'image' => $image,
            'imageDisplay' => $display,
            'imageMotion' => $motion,
            'overlayEnabled' => !empty($theme['narrowBackgroundOverlayEnabled']),
            'overlayColor' => $isValidHexColor($theme['narrowBackgroundOverlayColor'] ?? null, '#000000'),
            'overlayOpacity' => $overlayOpacity / 100,
            'contentShadow' => !array_key_exists('narrowContentShadow', $theme) || !empty($theme['narrowContentShadow'])
        ];
    }

    /**
     * Erstellt den CSS-Background für den Narrow-Backdrop.
     */
    private function buildNarrowBackdropCSS(array $narrowConfig): string {
        if ($narrowConfig['mode'] === 'gradient') {
            return 'linear-gradient(' . $narrowConfig['gradientAngle'] . 'deg, ' . $narrowConfig['gradientColor1'] . ', ' . $narrowConfig['gradientColor2'] . ')';
        }

        if ($narrowConfig['mode'] === 'image' && $narrowConfig['image'] !== '') {
            return "url('" . $narrowConfig['image'] . "')";
        }

        return $narrowConfig['color'];
    }
    
    /**
     * Rendert die komplette Seite
     */
    private function renderPage(array $settings, string $tilesHtml): string {
        // Tile-spezifisches CSS und JS sammeln
        $tileCSS = $this->collectTileCSS();
        $tileJS = $this->collectTileJS();
        $tileInitCalls = $this->collectTileInitCalls();
        $contrastJS = $this->getContrastJS();
        
        // Shared CSS laden
        $sharedCSS = $this->loadSharedCSS();
        
        // Defaults
        $site = $settings['site'] ?? [];
        $theme = $settings['theme'] ?? [];
        $siteTitle = htmlspecialchars($site['title'] ?? '');
        $siteTitleRaw = $site['title'] ?? '';
        $headerImage = $site['headerImage'] ?? null;
        $headerImagePlaceholder = $site['headerImagePlaceholder'] ?? null;
        $headerImageWidth = (int)($site['headerImageWidth'] ?? 0);
        $headerImageHeight = (int)($site['headerImageHeight'] ?? 0);
        $headerFocusPoint = htmlspecialchars($site['headerFocusPoint'] ?? 'center center');
        $footerText = nl2br(htmlspecialchars($site['footerText'] ?? ''));
        $bgColor = htmlspecialchars($theme['backgroundColor'] ?? '#f5f5f5');
        // primaryColor als Fallback für alte settings.json Dateien, accentColor ist der aktuelle Name
        $accentColor = htmlspecialchars($theme['accentColor'] ?? $theme['primaryColor'] ?? '#667eea');
        $accentColor2 = htmlspecialchars($theme['accentColor2'] ?? '#48bb78');
        $accentColor3 = htmlspecialchars($theme['accentColor3'] ?? '#ed8936');
        $narrowLayout = !empty($theme['narrowLayout']);
        $narrowWidth = intval($theme['narrowWidth'] ?? 960);
        if ($narrowWidth < 600 || $narrowWidth > 1400) $narrowWidth = 960;
        $narrowConfig = $this->getNarrowConfig($theme);
        $narrowBackdrop = $this->buildNarrowBackdropCSS($narrowConfig);
        $narrowOverlayDisplay = $narrowConfig['overlayEnabled'] ? 'block' : 'none';
        $narrowShadow = $narrowConfig['contentShadow'] ? '0 0 60px rgba(0,0,0,0.4)' : 'none';
        $narrowImageSize = $narrowConfig['imageDisplay'] === 'tile' ? 'auto' : 'cover';
        $narrowImageRepeat = $narrowConfig['imageDisplay'] === 'tile' ? 'repeat' : 'no-repeat';
        $narrowImageAttachment = $narrowConfig['imageMotion'] === 'fixed' ? 'fixed' : 'scroll';
        $narrowMotionClass = $narrowConfig['mode'] === 'image' && $narrowConfig['imageMotion'] === 'parallax' ? ' has-parallax' : '';
        
        // Title für <title>-Tag (pageTitle hat Priorität, dann title, dann Fallback)
        $pageTitleRaw = $site['pageTitle'] ?? '';
        if (!empty($pageTitleRaw)) {
            $pageTitle = htmlspecialchars($pageTitleRaw);
        } elseif (!empty($siteTitleRaw)) {
            $pageTitle = $siteTitle;
        } else {
            $pageTitle = 'Info-Hub';
        }
        
        // Header HTML - Titel nur wenn nicht leer
        $headerHtml = '';
        $headerPreloadHtml = '';
        $titleHtml = !empty($siteTitleRaw) ? "<h1 class=\"site-title\">{$siteTitle}</h1>" : '';
        
        if ($headerImage) {
            $headerImage = htmlspecialchars($headerImage);
            $headerImagePlaceholder = !empty($headerImagePlaceholder) ? htmlspecialchars($headerImagePlaceholder) : null;
            $headerImageClasses = 'header-image' . ($headerImagePlaceholder ? ' has-placeholder' : '');
            $headerDimensionAttrs = '';
            if ($headerImageWidth > 0 && $headerImageHeight > 0) {
                $headerDimensionAttrs = " width=\"{$headerImageWidth}\" height=\"{$headerImageHeight}\"";
            }

            $headerPreloadHtml = "    <link rel=\"preload\" as=\"image\" href=\"{$headerImage}\">\n";
            $placeholderHtml = $headerImagePlaceholder
                ? "            <img src=\"{$headerImagePlaceholder}\" alt=\"\" class=\"header-image-placeholder\" aria-hidden=\"true\">\n"
                : '';

            $headerHtml = <<<HTML
    <header class="site-header">
        <div class="{$headerImageClasses}">
{$placeholderHtml}            <img src="{$headerImage}" alt="" class="header-image-main" fetchpriority="high" decoding="async" loading="eager"{$headerDimensionAttrs} style="object-position: {$headerFocusPoint};">
        </div>
        {$titleHtml}
    </header>
HTML;
        } elseif (!empty($siteTitleRaw)) {
            // Minimaler Header nur wenn Titel vorhanden
            $headerHtml = <<<HTML
    <header class="site-header site-header--minimal">
        <h1 class="site-title">{$siteTitle}</h1>
    </header>
HTML;
        }
        
        // Footer HTML
        $footerHtml = '';
        if ($footerText) {
            $footerHtml = "<footer class=\"site-footer\">{$footerText}</footer>";
        }
        
        // Narrow Layout (optional: zentrierter Container mit begrenzter Breite)
        $narrowCSS = '';
        $narrowOpen = '';
        $narrowClose = '';
        $bodyClassAttr = '';
        if ($narrowLayout) {
            $narrowCSS = <<<CSS
        /* Narrow Layout */
        body.narrow-layout {
            background-color: transparent;
        }
        .page-shell {
            display: flex;
            flex: 1 0 auto;
            min-height: 100vh;
        }
        .page-shell__backdrop {
            position: relative;
            flex: 1 1 auto;
            display: flex;
            justify-content: center;
            overflow: hidden;
        }
        .page-shell__backdrop-media {
            position: absolute;
            inset: 0;
            background: {$narrowBackdrop};
            background-position: center center;
            background-repeat: {$narrowImageRepeat};
            background-size: {$narrowImageSize};
            background-attachment: {$narrowImageAttachment};
        }
        .page-shell__backdrop.has-parallax .page-shell__backdrop-media {
            inset: -10%;
            background-attachment: scroll;
            transform: scale(1.08) translateY(var(--narrow-parallax-offset, 0px));
            will-change: transform;
        }
        .page-shell__backdrop::after {
            content: '';
            position: absolute;
            inset: 0;
            background: {$narrowConfig['overlayColor']};
            opacity: {$narrowConfig['overlayOpacity']};
            display: {$narrowOverlayDisplay};
            pointer-events: none;
        }
        .page-shell__surface {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: {$narrowWidth}px;
            min-height: 100vh;
            margin: 0 auto;
            background: var(--bg-color);
            box-shadow: {$narrowShadow};
        }
        @media (prefers-reduced-motion: reduce) {
            .page-shell__backdrop.has-parallax .page-shell__backdrop-media {
                transform: none;
            }
        }
        @media (max-width: 900px) {
            .page-shell__backdrop-media {
                background-attachment: scroll;
            }
        }
CSS;

            $narrowOpen = '<div class="page-shell"><div class="page-shell__backdrop' . $narrowMotionClass . '"><div class="page-shell__backdrop-media" aria-hidden="true"></div><div class="page-shell__surface">';
            $narrowClose = '</div></div></div>';
            $bodyClassAttr = ' class="narrow-layout"';
        }
        
        // Komplette Seite
        $html = <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$pageTitle}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📌</text></svg>">
{$headerPreloadHtml}    <script>document.documentElement.classList.add('js','page-enter');</script>
    <style>
        :root {
            --bg-color: {$bgColor};
            --accent-color: {$accentColor};
            --accent-color-2: {$accentColor2};
            --accent-color-3: {$accentColor3};
        }
{$sharedCSS}
{$tileCSS}
{$narrowCSS}
    </style>
</head>
<body{$bodyClassAttr}>
{$narrowOpen}
{$headerHtml}

    <main class="tile-grid">
{$tilesHtml}
    </main>

{$footerHtml}
{$narrowClose}

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <span class="lightbox-close">&times;</span>
        <img src="" alt="" id="lightbox-img">
    </div>

    <!-- Iframe Modal -->
    <div class="iframe-modal" id="iframe-modal">
        <div class="iframe-modal-content">
            <div class="iframe-modal-header">
                <h3 class="iframe-modal-title" id="iframe-modal-title">Formular</h3>
                <button class="iframe-modal-close" onclick="closeIframeModal()">&times;</button>
            </div>
            <div class="iframe-modal-body">
                <iframe src="" id="iframe-modal-frame"></iframe>
            </div>
        </div>
    </div>

    <script>
{$contrastJS}
        // URL-Parameter auswerten für Embedding und Styles
        function applyUrlParams() {
            const params = new URLSearchParams(window.location.search);
            
            // ?embedded=true - Header und Footer ausblenden
            if (params.get('embedded') === 'true') {
                const header = document.querySelector('.site-header');
                const footer = document.querySelector('.site-footer');
                if (header) header.style.display = 'none';
                if (footer) footer.style.display = 'none';
                document.body.style.minHeight = 'auto';
            }
            
            // ?style=clean - Transparenter Hintergrund
            if (params.get('style')?.includes('clean')) {
                document.body.style.backgroundColor = 'transparent';
                document.documentElement.style.backgroundColor = 'transparent';
            }
            
            // ?style=minimalbox - Tiles mit weißem Hintergrund und dunklem Text
            if (params.get('style')?.includes('minimalbox')) {
                document.querySelectorAll('.tile').forEach(tile => {
                    tile.style.backgroundColor = 'white';
                    tile.style.color = '#333333';
                    // ALLE Text-Elemente auf dunkel setzen
                    tile.querySelectorAll('h3, h4, p, span, a, label, .tile-description, .tile-caption').forEach(el => {
                        el.style.color = '#333333';
                    });
                });
            }
        }

        function initHeaderReveal() {
            document.querySelectorAll('.header-image.has-placeholder').forEach(container => {
                const mainImage = container.querySelector('.header-image-main');
                if (!mainImage) return;

                const markLoaded = () => container.classList.add('is-loaded');

                if (mainImage.complete && mainImage.naturalWidth > 0) {
                    markLoaded();
                    return;
                }

                mainImage.addEventListener('load', markLoaded, { once: true });
                mainImage.addEventListener('error', markLoaded, { once: true });
            });
        }

        function initEntranceMotion() {
            const root = document.documentElement;

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                root.classList.remove('page-enter');
                root.classList.add('page-ready');
                return;
            }

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    root.classList.remove('page-enter');
                    root.classList.add('page-ready');
                });
            });
        }

        function initNarrowParallax() {
            const backdrop = document.querySelector('.page-shell__backdrop.has-parallax');
            if (!backdrop || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            let frameRequested = false;

            const update = () => {
                frameRequested = false;
                const rect = backdrop.getBoundingClientRect();
                const viewportCenter = window.innerHeight / 2;
                const backdropCenter = rect.top + rect.height / 2;
                const offset = Math.round((viewportCenter - backdropCenter) * 0.08);
                backdrop.style.setProperty('--narrow-parallax-offset', String(offset) + 'px');
            };

            const requestUpdate = () => {
                if (frameRequested) {
                    return;
                }
                frameRequested = true;
                requestAnimationFrame(update);
            };

            requestUpdate();
            window.addEventListener('scroll', requestUpdate, { passive: true });
            window.addEventListener('resize', requestUpdate);
        }
        
        // Bei Seitenladung ausführen
        document.addEventListener('DOMContentLoaded', () => {
            initHeaderReveal();
            initEntranceMotion();
            initNarrowParallax();
            applyUrlParams();
            // Kontrast NUR berechnen wenn NICHT minimalbox
            const params = new URLSearchParams(window.location.search);
            if (!params.get('style')?.includes('minimalbox')) {
                adjustTextContrast();
            }
        });
{$tileJS}
        // ===== ZEITGESTEUERTE SICHTBARKEIT =====
        function initScheduledVisibility() {
            const scheduledTiles = document.querySelectorAll('[data-show-from], [data-show-until]');
            
            if (scheduledTiles.length === 0) return;
            
            function updateVisibility() {
                const now = new Date();
                
                scheduledTiles.forEach(tile => {
                    const showFrom = tile.dataset.showFrom ? new Date(tile.dataset.showFrom) : null;
                    const showUntil = tile.dataset.showUntil ? new Date(tile.dataset.showUntil) : null;
                    
                    // Prüfen ob sichtbar sein sollte
                    const afterStart = !showFrom || now >= showFrom;
                    const beforeEnd = !showUntil || now <= showUntil;
                    const shouldShow = afterStart && beforeEnd;
                    
                    tile.style.display = shouldShow ? '' : 'none';
                });
            }
            
            // Initial ausführen
            updateVisibility();
            
            // Jede Minute aktualisieren (reicht für Minuten-genaue Steuerung)
            setInterval(updateVisibility, 60000);
        }
        
        // Zeitsteuerung beim Laden initialisieren
        document.addEventListener('DOMContentLoaded', initScheduledVisibility);
{$tileInitCalls}
    </script>
</body>
</html>
HTML;
        
        return $html;
    }
    
    /**
     * Prüft ob eine Tile nach Zeitplan sichtbar ist
     * 
     * @param array $tile Tile-Daten
     * @return bool True wenn sichtbar
     */
    private function isTileVisibleBySchedule(array $tile): bool {
        // Keine Zeitsteuerung = immer sichtbar
        if (!isset($tile['visibilitySchedule']) || empty($tile['visibilitySchedule'])) {
            return true;
        }
        
        $schedule = $tile['visibilitySchedule'];
        $now = time();
        
        // showFrom prüfen
        if (!empty($schedule['showFrom'])) {
            $showFrom = strtotime($schedule['showFrom']);
            if ($showFrom !== false && $now < $showFrom) {
                return false; // Noch nicht sichtbar
            }
        }
        
        // showUntil prüfen
        if (!empty($schedule['showUntil'])) {
            $showUntil = strtotime($schedule['showUntil']);
            if ($showUntil !== false && $now > $showUntil) {
                return false; // Nicht mehr sichtbar
            }
        }
        
        return true;
    }
    
    /**
     * Gibt eine Preview zurück (ohne zu speichern)
     */
    public function preview(): string {
        $settings = $this->settingsStorage->read();
        $tiles = $this->tileService->getTiles();
        $tilesHtml = $this->renderTiles($tiles);
        
        return $this->renderPage($settings, $tilesHtml);
    }
}
