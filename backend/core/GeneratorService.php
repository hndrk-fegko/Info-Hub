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
     * Rendert alle Tiles
     */
    private function renderTiles(array $tiles): string {
        global $TILE_TYPES;
        
        $html = '';
        
        foreach ($tiles as $tile) {
            // Manuell versteckte Tiles komplett überspringen (nicht im HTML)
            if (isset($tile['visible']) && $tile['visible'] === false) {
                LogService::debug('GeneratorService', 'Skipping hidden tile', ['id' => $tile['id'] ?? 'unknown']);
                continue;
            }
            
            $type = $tile['type'] ?? '';
            
            if (!isset($TILE_TYPES[$type])) {
                LogService::warning('GeneratorService', 'Unknown tile type', ['type' => $type]);
                continue;
            }
            
            $class = $TILE_TYPES[$type];
            $instance = new $class();
            
            // Tile-Wrapper mit gemeinsamen Klassen
            // Separator-Tiles sind immer full-width für Umbruchschutz
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
            
            // Zeitsteuerungs-Attribute für clientseitige Steuerung
            $scheduleAttrs = $this->getScheduleAttributes($tile);
            $hiddenStyle = $scheduleAttrs ? ' style="display:none;"' : '';
            
            $html .= "<div class=\"tile tile-{$type} size-{$size} style-{$style} color-{$colorScheme}{$extraClasses}\"{$scheduleAttrs}{$hiddenStyle} data-tile-id=\"{$id}\">\n";
            $html .= $instance->render($tile['data'] ?? []);
            $html .= "</div>\n";
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
     * Rendert die komplette Seite
     */
    private function renderPage(array $settings, string $tilesHtml): string {
        // Tile-spezifisches CSS und JS sammeln
        $tileCSS = $this->collectTileCSS();
        $tileJS = $this->collectTileJS();
        $tileInitCalls = $this->collectTileInitCalls();
        
        // Defaults
        $siteTitle = htmlspecialchars($settings['site']['title'] ?? '');
        $siteTitleRaw = $settings['site']['title'] ?? '';
        $headerImage = $settings['site']['headerImage'] ?? null;
        $headerFocusPoint = htmlspecialchars($settings['site']['headerFocusPoint'] ?? 'center center');
        $footerText = nl2br(htmlspecialchars($settings['site']['footerText'] ?? ''));
        $bgColor = htmlspecialchars($settings['theme']['backgroundColor'] ?? '#f5f5f5');
        // primaryColor als Fallback für alte settings.json Dateien, accentColor ist der aktuelle Name
        $accentColor = htmlspecialchars($settings['theme']['accentColor'] ?? $settings['theme']['primaryColor'] ?? '#667eea');
        $accentColor2 = htmlspecialchars($settings['theme']['accentColor2'] ?? '#48bb78');
        $accentColor3 = htmlspecialchars($settings['theme']['accentColor3'] ?? '#ed8936');
        
        // Title für <title>-Tag (pageTitle hat Priorität, dann title, dann Fallback)
        $pageTitleRaw = $settings['site']['pageTitle'] ?? '';
        if (!empty($pageTitleRaw)) {
            $pageTitle = htmlspecialchars($pageTitleRaw);
        } elseif (!empty($siteTitleRaw)) {
            $pageTitle = $siteTitle;
        } else {
            $pageTitle = 'Info-Hub';
        }
        
        // Header HTML - Titel nur wenn nicht leer
        $headerHtml = '';
        $titleHtml = !empty($siteTitleRaw) ? "<h1 class=\"site-title\">{$siteTitle}</h1>" : '';
        
        if ($headerImage) {
            $headerImage = htmlspecialchars($headerImage);
            $headerHtml = <<<HTML
    <header class="site-header">
        <div class="header-image">
            <img src="{$headerImage}" alt="" style="object-position: {$headerFocusPoint};">
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
        
        // Komplette Seite
        $html = <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$pageTitle}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📌</text></svg>">
    <style>
        :root {
            --bg-color: {$bgColor};
            --accent-color: {$accentColor};
            --accent-color-2: {$accentColor2};
            --accent-color-3: {$accentColor3};
            --text-color: #2d3748;
            --text-light: #718096;
            --card-bg: #ffffff;
            --card-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 4px 14px rgba(0,0,0,0.06);
            --card-shadow-hover: 0 2px 6px rgba(0,0,0,0.06), 0 10px 24px rgba(0,0,0,0.1);
            --border-radius: 14px;
            --spacing: 24px;
            --max-width: 1200px;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        html {
            background-color: var(--bg-color);
            overscroll-behavior: none;
            scroll-behavior: smooth;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Header */
        .site-header {
            position: relative;
            text-align: center;
            margin-bottom: calc(var(--spacing) * 0.5);
        }
        
        .site-header .header-image {
            width: 100%;
            height: 320px;
            overflow: hidden;
        }
        
        .site-header .header-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .site-header .site-title {
            position: absolute;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            text-shadow: 0 2px 12px rgba(0, 0, 0, 0.35);
            font-size: clamp(1.5rem, 4vw, 2.5rem);
            font-weight: 700;
            padding: 12px 32px;
            background: rgba(0, 0, 0, 0.3);
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            max-width: 90%;
            letter-spacing: -0.01em;
        }
        
        .site-header--minimal {
            padding: 48px 24px;
            background: var(--accent-color);
        }
        
        .site-header--minimal .site-title {
            position: static;
            transform: none;
            background: none;
            text-shadow: none;
            font-size: clamp(1.5rem, 4vw, 2.2rem);
        }
        
        /* Tile Grid - 4 Spalten */
        .tile-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--spacing);
            width: 100%;
            max-width: var(--max-width);
            margin: 0 auto;
            padding: var(--spacing);
            flex-grow: 1;
            align-content: start;
        }
        
        /* Tile Base */
        .tile {
            display: flex;
            flex-direction: column;
            padding: var(--spacing);
            border-radius: var(--border-radius);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        /* Card: Inhalt vertikal zentrieren (bei Grid-Stretch sichtbar) */
        .tile.style-card {
            justify-content: center;
        }
        
        /* Flat: Inhalt oben (natürlicher Fluss) */
        .tile.style-flat {
            justify-content: flex-start;
        }
        
        .tile:hover {
            transform: translateY(-2px);
        }
        
        /* Flat tiles: kein Lift-Effekt */
        .tile.style-flat:hover {
            transform: none;
        }
        
        /* Tile Sizes - Desktop: 4 Spalten */
        .tile.size-small {
            grid-column: span 1;  /* 1/4 */
        }
        
        .tile.size-medium {
            grid-column: span 2;  /* 2/4 = 1/2 */
        }
        
        .tile.size-large {
            grid-column: span 3;  /* 3/4 */
        }
        
        .tile.size-full {
            grid-column: 1 / -1;  /* 4/4 = volle Breite */
        }
        
        /* Tablet: 2 Spalten */
        @media (max-width: 900px) {
            .tile-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .tile.size-small {
                grid-column: span 1;  /* 1/2 */
            }
            .tile.size-medium {
                grid-column: span 2;  /* 2/2 = voll */
            }
            .tile.size-large {
                grid-column: span 2;  /* 2/2 = voll */
            }
            .site-header .header-image {
                height: 260px;
            }
            .site-header .site-title {
                bottom: 20px;
            }
        }
        
        /* Mobile: 1 Spalte */
        @media (max-width: 600px) {
            :root {
                --spacing: 16px;
                --border-radius: 12px;
            }
            .tile-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .tile.size-small,
            .tile.size-medium,
            .tile.size-large,
            .tile.size-full {
                grid-column: span 1;
            }
            .tile:hover {
                transform: none;
            }
            .site-header .header-image {
                height: 200px;
            }
            .site-header .site-title {
                bottom: 16px;
                padding: 8px 20px;
            }
            .site-header--minimal {
                padding: 32px 16px;
            }
        }
        
        /* Tile Styles */
        .tile.style-flat {
            background: transparent;
            border-radius: 8px;
            box-shadow: none;
        }
        
        .tile.style-card {
            background: var(--card-bg);
            box-shadow: var(--card-shadow);
        }
        
        .tile.style-card:hover {
            box-shadow: var(--card-shadow-hover);
        }
        
        /* Tile Color Schemes */
        .tile.color-default {
            background-color: transparent;
        }
        
        .tile.color-white {
            background-color: var(--card-bg);
        }
        
        .tile.color-accent1 {
            background-color: var(--accent-color);
        }
        
        .tile.color-accent2 {
            background-color: var(--accent-color-2);
        }
        
        .tile.color-accent3 {
            background-color: var(--accent-color-3);
        }
        
        /* Card-Style überschreibt default transparent mit weiß */
        .tile.style-card.color-default {
            background-color: var(--card-bg);
        }
        
        /* Tile Content */
        .tile h3 {
            margin-bottom: 12px;
            font-size: 1.2rem;
            font-weight: 600;
            letter-spacing: -0.01em;
        }
        
        .tile p {
            margin-bottom: 12px;
        }
        
        .tile p:last-child {
            margin-bottom: 0;
        }
        
        .tile img {
            max-width: 100%;
            display: block;
            border-radius: 8px;
        }
        
        .tile a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .tile a:hover {
            text-decoration: underline;
        }
        
        /* Download Button */
        .tile-download {
            text-align: center;
        }
        
        .tile-download .download-content {
            text-align: left;
        }
        
        .tile-download .download-action {
            margin-top: auto;
            padding-top: 12px;
            text-align: center;
        }
        
        .tile-download.style-flat .download-action {
            margin-top: 12px;
        }
        
        .tile .download-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--accent-color);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: filter 0.2s ease, transform 0.2s ease;
        }
        
        .tile .download-btn:hover {
            filter: brightness(0.9);
            transform: translateY(-1px);
            text-decoration: none;
        }
        
        /* Footer */
        .site-footer {
            text-align: center;
            padding: 32px 24px;
            color: var(--text-light);
            margin-top: auto;
            padding-top: 32px;
            font-size: 0.875rem;
            border-top: 1px solid rgba(0,0,0,0.06);
        }
        
        /* Auswahl-Farbe */
        ::selection {
            background: var(--accent-color);
            color: white;
        }
        
        /* Reduzierte Bewegung */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
            html { scroll-behavior: auto; }
        }
{$tileCSS}
    </style>
</head>
<body>
{$headerHtml}

    <main class="tile-grid">
{$tilesHtml}
    </main>

{$footerHtml}

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
        // Automatischer Text-Kontrast (WCAG-konform)
        function adjustTextContrast() {
            // Helligkeits-Berechnung nach W3C
            function getLuminance(color) {
                let r, g, b;
                color = (color || '').trim();
                // rgb()/rgba() Format
                const rgbMatch = color.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
                if (rgbMatch) {
                    r = parseInt(rgbMatch[1]); g = parseInt(rgbMatch[2]); b = parseInt(rgbMatch[3]);
                    return (0.299 * r + 0.587 * g + 0.114 * b);
                }
                // Hex Format (3- oder 6-stellig)
                let hex = color.replace('#', '');
                if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
                if (hex.length !== 6) return 128; // Fallback: mittlere Helligkeit
                r = parseInt(hex.substr(0, 2), 16);
                g = parseInt(hex.substr(2, 2), 16);
                b = parseInt(hex.substr(4, 2), 16);
                if (isNaN(r) || isNaN(g) || isNaN(b)) return 128;
                return (0.299 * r + 0.587 * g + 0.114 * b);
            }
            
            // CSS-Variable lesen
            function getCSSVar(name) {
                return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            }
            
            // Tiles mit Farbschema finden (nur Akzentfarben brauchen Anpassung)
            document.querySelectorAll('.tile.color-accent1, .tile.color-accent2, .tile.color-accent3').forEach(tile => {
                let bgColor = '#ffffff';
                
                if (tile.classList.contains('color-accent1')) {
                    bgColor = getCSSVar('--accent-color');
                } else if (tile.classList.contains('color-accent2')) {
                    bgColor = getCSSVar('--accent-color-2');
                } else if (tile.classList.contains('color-accent3')) {
                    bgColor = getCSSVar('--accent-color-3');
                }
                
                // Luminanz prüfen
                const luminance = getLuminance(bgColor);
                
                // Dunkler Text für helle Hintergründe, heller Text für dunkle
                if (luminance > 150) {
                    tile.style.color = '#333333';
                    tile.querySelectorAll('h3, p').forEach(el => el.style.color = '#333333');
                } else {
                    tile.style.color = '#ffffff';
                    tile.querySelectorAll('h3, p').forEach(el => el.style.color = '#ffffff');
                }
            });
        }
        
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
        
        // Bei Seitenladung ausführen
        document.addEventListener('DOMContentLoaded', () => {
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
