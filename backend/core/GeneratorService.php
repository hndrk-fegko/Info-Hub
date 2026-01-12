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
            
            // 5. Neue Datei schreiben
            $result = file_put_contents($this->outputPath, $html);
            
            if ($result === false) {
                throw new Exception('Konnte index.html nicht schreiben');
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
     * Rendert alle Tiles
     */
    private function renderTiles(array $tiles): string {
        global $TILE_TYPES;
        
        $html = '';
        
        foreach ($tiles as $tile) {
            $type = $tile['type'] ?? '';
            
            if (!isset($TILE_TYPES[$type])) {
                LogService::warning('GeneratorService', 'Unknown tile type', ['type' => $type]);
                continue;
            }
            
            $class = $TILE_TYPES[$type];
            $instance = new $class();
            
            // Tile-Wrapper mit gemeinsamen Klassen
            $size = htmlspecialchars($tile['size'] ?? 'medium');
            $style = htmlspecialchars($tile['style'] ?? 'card');
            $colorScheme = htmlspecialchars($tile['colorScheme'] ?? 'default');
            $id = htmlspecialchars($tile['id'] ?? '');
            
            $html .= "<div class=\"tile tile-{$type} size-{$size} style-{$style} color-{$colorScheme}\" data-tile-id=\"{$id}\">\n";
            $html .= $instance->render($tile['data'] ?? []);
            $html .= "</div>\n";
        }
        
        return $html;
    }
    
    /**
     * Rendert die komplette Seite
     */
    private function renderPage(array $settings, string $tilesHtml): string {
        // Defaults
        $siteTitle = htmlspecialchars($settings['site']['title'] ?? '');
        $siteTitleRaw = $settings['site']['title'] ?? '';
        $headerImage = $settings['site']['headerImage'] ?? null;
        $footerText = nl2br(htmlspecialchars($settings['site']['footerText'] ?? ''));
        $bgColor = htmlspecialchars($settings['theme']['backgroundColor'] ?? '#f5f5f5');
        $primaryColor = htmlspecialchars($settings['theme']['primaryColor'] ?? '#667eea');
        $accentColor2 = htmlspecialchars($settings['theme']['accentColor2'] ?? '#48bb78');
        $accentColor3 = htmlspecialchars($settings['theme']['accentColor3'] ?? '#ed8936');
        
        // Title für <title>-Tag (Fallback wenn leer)
        $pageTitle = !empty($siteTitleRaw) ? $siteTitle : 'Info-Hub';
        
        // Header HTML - Titel nur wenn nicht leer
        $headerHtml = '';
        $titleHtml = !empty($siteTitleRaw) ? "<h1 class=\"site-title\">{$siteTitle}</h1>" : '';
        
        if ($headerImage) {
            $headerImage = htmlspecialchars($headerImage);
            $headerHtml = <<<HTML
    <header class="site-header">
        <div class="header-image">
            <img src="{$headerImage}" alt="">
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
    <style>
        :root {
            --bg-color: {$bgColor};
            --primary-color: {$primaryColor};
            --accent-color-2: {$accentColor2};
            --accent-color-3: {$accentColor3};
            --text-color: #333;
            --text-light: #666;
            --card-bg: #ffffff;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
            --spacing: 20px;
            --max-width: 1200px;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Header */
        .site-header {
            position: relative;
            text-align: center;
            margin-bottom: var(--spacing);
        }
        
        .site-header .header-image {
            width: 100%;
            height: 300px;
            overflow: hidden;
        }
        
        .site-header .header-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .site-header .site-title {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.7);
            font-size: 2.5rem;
            padding: 10px 30px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: var(--border-radius);
        }
        
        .site-header--minimal {
            padding: 40px 20px;
            background: var(--primary-color);
        }
        
        .site-header--minimal .site-title {
            position: static;
            transform: none;
            background: none;
            text-shadow: none;
        }
        
        /* Tile Grid */
        .tile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: var(--spacing);
            max-width: var(--max-width);
            margin: 0 auto;
            padding: var(--spacing);
            flex-grow: 1;
            align-content: start;
        }
        
        /* Tile Base */
        .tile {
            padding: var(--spacing);
            border-radius: var(--border-radius);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .tile:hover {
            transform: translateY(-2px);
        }
        
        /* Tile Sizes */
        .tile.size-small {
            grid-column: span 1;
        }
        
        .tile.size-medium {
            grid-column: span 1;
        }
        
        .tile.size-large {
            grid-column: span 2;
        }
        
        .tile.size-full {
            grid-column: 1 / -1;
        }
        
        @media (max-width: 600px) {
            .tile.size-large,
            .tile.size-full {
                grid-column: span 1;
            }
        }
        
        /* Tile Styles */
        .tile.style-flat {
            background: transparent;
            padding: var(--spacing) 0;
        }
        
        .tile.style-card {
            background: var(--card-bg);
            box-shadow: var(--card-shadow);
        }
        
        .tile.style-card:hover {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }
        
        /* Tile Color Schemes */
        .tile.color-default {
            background-color: transparent;
        }
        
        .tile.color-white {
            background-color: var(--card-bg);
        }
        
        .tile.color-accent1 {
            background-color: var(--primary-color);
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
            margin-bottom: 10px;
            font-size: 1.25rem;
        }
        
        .tile p {
            margin-bottom: 15px;
        }
        
        .tile img {
            max-width: 100%;
            border-radius: 8px;
        }
        
        .tile a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .tile a:hover {
            text-decoration: underline;
        }
        
        /* Download Button */
        .tile .download-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary-color);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s ease;
        }
        
        .tile .download-btn:hover {
            background: color-mix(in srgb, var(--primary-color) 85%, black);
            text-decoration: none;
        }
        
        /* Image Tile */
        .tile-image .tile-image-container {
            display: block;
        }
        
        .tile-image .tile-image-lightbox {
            cursor: pointer;
        }
        
        .tile-image .tile-image-link {
            cursor: pointer;
            display: block;
        }
        
        .tile-image .tile-image-link:hover img {
            opacity: 0.9;
        }
        
        .tile-image img {
            width: 100%;
            height: auto;
        }
        
        /* Footer - Sticky am unteren Rand */
        .site-footer {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
            margin-top: auto;
            font-size: 0.9rem;
            margin-top: 40px;
        }
        
        /* Lightbox */
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            cursor: pointer;
        }
        
        .lightbox.active {
            display: flex;
        }
        
        .lightbox img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }
        
        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            cursor: pointer;
        }
        
        /* Iframe Tile */
        .tile-iframe .tile-iframe-container {
            position: relative;
            width: 100%;
            overflow: hidden;
            border-radius: 8px;
        }
        
        .tile-iframe .tile-iframe-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .tile-iframe .iframe-modal-trigger {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 16px 24px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .tile-iframe .iframe-modal-trigger:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .tile-iframe .iframe-modal-icon {
            font-size: 1.2em;
        }
        
        .tile-iframe .tile-description {
            color: var(--text-light);
            margin-bottom: 16px;
        }
        
        .tile-iframe .tile-caption {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 8px;
        }
        
        /* Iframe Modal */
        .iframe-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 1001;
            justify-content: center;
            align-items: center;
        }
        
        .iframe-modal.active {
            display: flex;
        }
        
        .iframe-modal-content {
            position: relative;
            width: 90%;
            height: 85%;
            max-width: 1200px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .iframe-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            background: #f5f5f5;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .iframe-modal-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 500;
        }
        
        .iframe-modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #666;
            line-height: 1;
        }
        
        .iframe-modal-close:hover {
            color: #000;
        }
        
        .iframe-modal-body {
            width: 100%;
            height: calc(100% - 50px);
        }
        
        .iframe-modal-body iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
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
                const hex = color.replace('#', '');
                const r = parseInt(hex.substr(0, 2), 16);
                const g = parseInt(hex.substr(2, 2), 16);
                const b = parseInt(hex.substr(4, 2), 16);
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
                    bgColor = getCSSVar('--primary-color');
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
        
        // Lightbox
        function openLightbox(src) {
            document.getElementById('lightbox-img').src = src;
            document.getElementById('lightbox').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('active');
            document.body.style.overflow = '';
        }
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeLightbox();
                closeIframeModal();
            }
        });
        
        // Iframe Modal
        function openIframeModal(url, title) {
            const titleEl = document.getElementById('iframe-modal-title');
            if (title && title.trim() !== '') {
                titleEl.textContent = title;
                titleEl.style.display = '';
            } else {
                titleEl.textContent = '';
                titleEl.style.display = 'none';
            }
            document.getElementById('iframe-modal-frame').src = url;
            document.getElementById('iframe-modal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeIframeModal() {
            document.getElementById('iframe-modal').classList.remove('active');
            document.getElementById('iframe-modal-frame').src = ''; // Stoppt Laden
            document.body.style.overflow = '';
        }
        
        // Click outside to close
        document.getElementById('iframe-modal').addEventListener('click', (e) => {
            if (e.target.id === 'iframe-modal') {
                closeIframeModal();
            }
        });
        
        // Email Reveal (Anti-Spam)
        function revealEmail(user, domain) {
            const email = user + '@' + domain;
            window.location.href = 'mailto:' + email;
        }
    </script>
</body>
</html>
HTML;
        
        return $html;
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
