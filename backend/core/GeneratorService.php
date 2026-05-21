<?php
/**
 * GeneratorService - Statische HTML-Generierung
 * 
 * Generiert index.html aus Tiles und Settings.
 * Nutzt Templates für konsistentes Layout.
 */

require_once __DIR__ . '/LogService.php';
require_once __DIR__ . '/RenderContract.php';
require_once __DIR__ . '/SecurityHelper.php';
require_once __DIR__ . '/StorageService.php';
require_once __DIR__ . '/TileRegistry.php';
require_once __DIR__ . '/TileService.php';

class GeneratorService {
    
    private StorageService $settingsStorage;
    private TileService $tileService;
    private TileRegistry $tileRegistry;
    private string $outputPath;
    
    public function __construct(
        ?TileRegistry $tileRegistry = null,
        ?TileService $tileService = null,
        ?StorageService $settingsStorage = null
    ) {
        $this->settingsStorage = $settingsStorage ?? new StorageService('settings.json');
        $this->tileRegistry = $tileRegistry ?? TileRegistry::shared();
        $this->tileService = $tileService ?? new TileService($this->tileRegistry);
        $this->outputPath = __DIR__ . '/../../index.html';
    }
    
    /**
     * Generiert die statische index.html
     *
     * Backup-Erzeugung wird zentral über BackupService orchestriert.
     * 
     * @return array ['success' => bool, 'message' => string]
     */
    public function generate(): array {
        LogService::info('GeneratorService', 'Starting HTML generation');
        
        try {
            // 1. Daten laden
            $settings = $this->settingsStorage->read();
            $tiles = $this->tileService->getTiles();
            
            // 2. Seiten-Abschnitte rendern
            $tilesHtml = $this->renderPageSections($tiles);
            
            // 3. Template laden und füllen
            $html = $this->renderPage($settings, $tilesHtml);
            
            // 4. Neue Datei atomar schreiben (temp + rename)
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
        $css = "\n        /* ===== TILE-SPEZIFISCHE STYLES ===== */\n";
        
        foreach ($this->tileRegistry->all() as $type => $class) {
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
        $js = "\n        // ===== TILE-SPEZIFISCHES JAVASCRIPT =====\n";
        
        foreach ($this->tileRegistry->all() as $type => $class) {
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
        $calls = [];
        
        foreach ($this->tileRegistry->all() as $type => $class) {
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
        $type = $tile['type'] ?? '';
        
        if (!$this->tileRegistry->has($type)) {
            LogService::warning('GeneratorService', 'Unknown tile type', ['type' => $type]);
            return null;
        }
        
        $instance = $this->tileRegistry->create($type);
        if ($instance === null) {
            LogService::warning('GeneratorService', 'Unknown tile type', ['type' => $type]);
            return null;
        }
        
        // Tile-Wrapper mit gemeinsamen Klassen
        $size = $this->isFullWidthType($type) ? 'full' : htmlspecialchars($tile['size'] ?? 'medium');
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
     * Rendert die Abschnittsstruktur für den WYSIWYG-Canvas.
     *
     * PARALLEL RENDER CONTRACT:
      * Diese Rückgabe wird direkt von backend/v2/editor.php und assets/js/v2/canvas.js konsumiert.
      * RenderContract::CANVAS_SECTION_KEYS ist die kanonische Shape-Definition.
      * Änderungen an Struktur oder Metadaten hier müssen dort mitgepflegt werden.
      * tests/test_section_layout.php dient als ausführbarer Contract-Test für diese Struktur.
     *
      * @return array<int, array<string, mixed>>
     */
    public function renderCanvasSections(): array {
        $tiles = $this->tileService->getTiles();
        $sections = $this->buildSectionLayout($tiles, true, true);
        $result = [];

        foreach ($sections as $section) {
            $markerTile = $section['markerTile'];
            $html = $this->renderSectionHtml($section, false);
            if ($html === '') {
                continue;
            }

            $result[] = RenderContract::canvasSection([
                'id' => $section['id'],
                'html' => $html,
                'markerTileId' => $markerTile['id'] ?? null,
                'markerTitle' => (string) ($section['config']['title'] ?? ''),
                'backgroundMode' => (string) ($section['config']['backgroundMode'] ?? 'none'),
                'backgroundAttachment' => (string) ($section['config']['backgroundAttachment'] ?? 'content'),
                'backgroundDisplay' => (string) ($section['config']['backgroundDisplay'] ?? 'cover'),
                'overlayEnabled' => (bool) (($section['config']['overlayColorEnabled'] ?? false) || ($section['config']['overlayBlurEnabled'] ?? false)),
                'overlayOpacity' => (int) ($section['config']['overlayOpacity'] ?? 0),
                'visible' => (bool) ($markerTile['visible'] ?? true),
                'tileIds' => array_values(array_map(static function($tile) {
                    return $tile['id'] ?? '';
                }, $section['tiles'])),
                'isImplicit' => $markerTile === null
            ]);
        }

        return $result;
    }
    
    /**
     * Rendert alle Tiles als Array mit ID → HTML Mapping
     * 
     * Für den WYSIWYG-Editor: liefert das gerenderte HTML jeder Tile,
     * sodass der Editor es direkt in den Canvas platzieren kann.
     * 
        * RenderContract::RENDERED_TILE_KEYS ist die kanonische Shape-Definition.
        *
        * @return array<int, array<string, mixed>>
     */
    public function renderAllTilesHtml(): array {
        $tiles = $this->tileService->getTiles();
        $result = [];
        
        foreach ($tiles as $tile) {
            $html = $this->renderSingleTile($tile, false);
            if ($html !== null) {
                $result[] = RenderContract::renderedTile([
                    'id' => $tile['id'],
                    'type' => $tile['type'],
                    'html' => $html,
                    'size' => (string) ($tile['size'] ?? 'medium'),
                    'style' => (string) ($tile['style'] ?? 'card'),
                    'colorScheme' => (string) ($tile['colorScheme'] ?? 'default'),
                    'position' => (int) ($tile['position'] ?? 0),
                    'visible' => (bool) ($tile['visible'] ?? true)
                ]);
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
        $legalJS = $this->getLegalModalJS();
        return $contrastJS . $legalJS . $tileJS . $initCalls;
    }

    /**
     * Rendert den Footer inklusive optionaler Rechts-Buttons.
     */
    public function renderFooterMarkup(array $settings): string {
        $site = is_array($settings['site'] ?? null) ? $settings['site'] : [];
        $legal = SecurityHelper::normalizeLegalSettings($settings['legal'] ?? []);

        $footerText = $this->renderFooterText($site['footerText'] ?? '');
        $legalActions = $this->renderLegalActions($legal);

        if ($footerText === '' && $legalActions === '') {
            return '';
        }

        $footerTextHtml = $footerText !== ''
            ? "<div class=\"site-footer__text\">{$footerText}</div>"
            : '';
        $legalActionsHtml = $legalActions !== ''
            ? "<div class=\"site-footer__legal-actions\" aria-label=\"Rechtliche Hinweise\">{$legalActions}</div>"
            : '';

        return "<footer class=\"site-footer\">{$footerTextHtml}{$legalActionsHtml}</footer>";
    }

    /**
     * Rendert das Modal fuer Impressum/Datenschutz, falls Text-Inhalte konfiguriert sind.
     */
    public function renderLegalModalMarkup(array $settings): string {
        $legal = SecurityHelper::normalizeLegalSettings($settings['legal'] ?? []);
        if (empty($legal['enabled'])) {
            return '';
        }

        $labels = [
            'imprint' => 'Impressum',
            'privacy' => 'Datenschutz'
        ];

        $templates = [];
        foreach ($labels as $key => $label) {
            $entry = $legal[$key] ?? [];
            if (($entry['mode'] ?? 'off') !== 'text' || !SecurityHelper::isLegalEntryConfigured($entry)) {
                continue;
            }

            $templates[] = "        <template id=\"legal-template-{$key}\" data-legal-title=\"" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "\">{$entry['text']}</template>";
        }

        if (empty($templates)) {
            return '';
        }

        $templatesHtml = implode("\n", $templates);

        return <<<HTML
    <div class="legal-modal" id="legal-modal" aria-hidden="true">
        <div class="legal-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="legal-modal-title">
            <div class="legal-modal__header">
                <h2 class="legal-modal__title" id="legal-modal-title">Rechtliche Hinweise</h2>
                <button type="button" class="legal-modal__close" data-legal-modal-close aria-label="Dialog schließen">&times;</button>
            </div>
            <div class="legal-modal__body" id="legal-modal-body"></div>
        </div>
{$templatesHtml}
    </div>
HTML;
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
     * Steuert das Frontend-Modal fuer Rechtstexte.
     */
    private function getLegalModalJS(): string {
        return <<<'JS'
        let legalModalTrigger = null;

        function getLegalModalFocusable(modal) {
            return Array.from(modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'))
                .filter((element) => !element.hasAttribute('disabled') && element.getAttribute('aria-hidden') !== 'true');
        }

        function trapLegalModalFocus(event, modal) {
            const focusable = getLegalModalFocusable(modal);
            if (focusable.length === 0) {
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                last.focus();
                event.preventDefault();
                return;
            }

            if (!event.shiftKey && document.activeElement === last) {
                first.focus();
                event.preventDefault();
            }
        }

        function initLegalModal() {
            const modal = document.getElementById('legal-modal');
            if (!modal || modal.dataset.initialized === 'true') {
                return;
            }

            modal.dataset.initialized = 'true';
            modal.addEventListener('click', (event) => {
                const closeTrigger = event.target.closest('[data-legal-modal-close]');
                if (closeTrigger) {
                    event.preventDefault();
                    closeLegalModal();
                    return;
                }

                if (event.target === modal) {
                    closeLegalModal();
                }
            });

            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('.site-footer__legal-button[data-legal-key]');
                if (!trigger) {
                    return;
                }

                handleLegalRouteClick(event, trigger.dataset.legalKey || '', trigger);
            });

            document.addEventListener('keydown', (event) => {
                if (!modal.classList.contains('active')) {
                    return;
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeLegalModal();
                    return;
                }

                if (event.key === 'Tab') {
                    trapLegalModalFocus(event, modal);
                }
            });

            window.addEventListener('popstate', syncLegalRouteFromLocation);
        }

        function normalizeLegalRoutePath(pathname) {
            const normalized = String(pathname || '/').replace(/\/+$/, '') || '/';
            return normalized.toLowerCase();
        }

        function getLegalRouteTypeFromLocation(pathname) {
            const normalized = normalizeLegalRoutePath(pathname);
            if (normalized === '/impressum') {
                return 'imprint';
            }

            if (normalized === '/datenschutz') {
                return 'privacy';
            }

            return null;
        }

        function getLegalRoutePath(type) {
            return type === 'privacy' ? '/datenschutz' : '/impressum';
        }

        function getLegalHomePath() {
            return '/' + window.location.search + window.location.hash;
        }

        function getLegalRouteUrl(type) {
            return getLegalRoutePath(type) + window.location.search + window.location.hash;
        }

        function getLegalRouteEntry(type) {
            return document.querySelector('.site-footer__legal-button[data-legal-key="' + type + '"]');
        }

        function handleLegalRouteClick(event, type, trigger) {
            const entry = getLegalRouteEntry(type) || trigger;
            if (!entry) {
                return true;
            }

            if (entry.dataset.legalMode !== 'text') {
                return true;
            }

            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            const targetUrl = getLegalRouteUrl(type);
            if (normalizeLegalRoutePath(window.location.pathname) !== getLegalRoutePath(type)) {
                window.history.pushState({}, '', targetUrl);
            }

            openLegalModal(type, trigger || entry);
            return false;
        }

        function syncLegalRouteFromLocation() {
            const routeType = getLegalRouteTypeFromLocation(window.location.pathname);
            const modal = document.getElementById('legal-modal');

            if (!routeType) {
                closeLegalModal({ preserveRoute: true });
                return;
            }

            const entry = getLegalRouteEntry(routeType);
            if (!entry) {
                window.history.replaceState({}, '', getLegalHomePath());
                if (modal && modal.classList.contains('active')) {
                    closeLegalModal({ preserveRoute: true });
                }
                return;
            }

            if (entry.dataset.legalMode === 'link') {
                const target = entry.dataset.legalTarget || '';
                if (target) {
                    window.location.assign(target);
                    return;
                }

                window.history.replaceState({}, '', getLegalHomePath());
                return;
            }

            if (entry.dataset.legalMode === 'text') {
                openLegalModal(routeType, entry);
            }
        }

        function openLegalModal(type, trigger) {
            const modal = document.getElementById('legal-modal');
            const body = document.getElementById('legal-modal-body');
            const title = document.getElementById('legal-modal-title');
            const template = document.getElementById('legal-template-' + type);
            if (!modal || !body || !title || !template) {
                return;
            }

            legalModalTrigger = trigger || document.activeElement;
            title.textContent = template.dataset.legalTitle || (type === 'privacy' ? 'Datenschutz' : 'Impressum');
            body.innerHTML = template.innerHTML;

            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('legal-modal-open');

            const closeButton = modal.querySelector('.legal-modal__close');
            if (closeButton) {
                closeButton.focus();
            }
        }

        function closeLegalModal(options) {
            const modal = document.getElementById('legal-modal');
            const body = document.getElementById('legal-modal-body');
            if (!modal || !modal.classList.contains('active')) {
                return;
            }

            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('legal-modal-open');

            if (body) {
                body.innerHTML = '';
            }

            if (!options || options.preserveRoute !== true) {
                const routeType = getLegalRouteTypeFromLocation(window.location.pathname);
                if (routeType) {
                    window.history.replaceState({}, '', getLegalHomePath());
                }
            }

            if (legalModalTrigger && typeof legalModalTrigger.focus === 'function') {
                legalModalTrigger.focus();
            }

            legalModalTrigger = null;
        }

        (function() {
            const onReady = () => {
                initLegalModal();
                syncLegalRouteFromLocation();
            };
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', onReady);
                return;
            }

            onReady();
        })();
JS;
    }

    /**
     * Styling fuer den mobilen Pull-to-Refresh-Indikator.
     */
    private function getPullToRefreshCSS(): string {
        return <<<'CSS'
        .page-root {
            transform: translateY(var(--pull-refresh-surface-offset, 0px));
            transition: transform 0.22s ease;
            will-change: transform;
        }

        .pull-refresh-indicator {
            position: fixed;
            top: 0;
            left: 50%;
            z-index: 1200;
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 190px;
            max-width: calc(100vw - 24px);
            padding: calc(env(safe-area-inset-top, 0px) + 10px) 16px 12px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-top: 0;
            border-radius: 0 0 16px 16px;
            background: rgba(15, 23, 42, 0.92);
            color: #f8fafc;
            box-shadow: 0 14px 34px rgba(2, 6, 23, 0.3);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            pointer-events: none;
            opacity: 0;
            transform: translate(-50%, calc(-100% + var(--pull-refresh-offset, 0px)));
            transition: transform 0.22s ease, opacity 0.22s ease, box-shadow 0.22s ease;
        }

        .pull-refresh-indicator.is-visible {
            opacity: 1;
        }

        .pull-refresh-indicator.is-ready {
            box-shadow: 0 18px 38px rgba(14, 165, 233, 0.22);
        }

        .pull-refresh-indicator__glyph {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            flex: 0 0 28px;
            border-radius: 999px;
            background: rgba(148, 163, 184, 0.16);
            color: #bae6fd;
            font-size: 16px;
            line-height: 1;
            transition: transform 0.22s ease, background 0.22s ease, color 0.22s ease;
        }

        .pull-refresh-indicator.is-ready .pull-refresh-indicator__glyph {
            transform: rotate(180deg);
            background: rgba(14, 165, 233, 0.22);
            color: #f8fafc;
        }

        .pull-refresh-indicator.is-loading .pull-refresh-indicator__glyph {
            color: transparent;
            background: rgba(14, 165, 233, 0.22);
        }

        .pull-refresh-indicator.is-loading .pull-refresh-indicator__glyph::after {
            content: '';
            position: absolute;
            inset: 6px;
            border: 2px solid rgba(255, 255, 255, 0.28);
            border-top-color: #ffffff;
            border-radius: 999px;
            animation: pull-refresh-spin 0.8s linear infinite;
        }

        .pull-refresh-indicator__label {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.01em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @keyframes pull-refresh-spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .page-root,
            .pull-refresh-indicator,
            .pull-refresh-indicator__glyph {
                transition: none;
            }
        }
CSS;
    }

    /**
     * Aktiviert einen einfachen Pull-to-Refresh-Flow auf Touch-Geräten.
     */
    private function getPullToRefreshJS(): string {
        return <<<'JS'
        function initPullToRefresh() {
            const hasCoarsePointer = window.matchMedia('(pointer: coarse)').matches || (navigator.maxTouchPoints || 0) > 0;
            if (!hasCoarsePointer || window.self !== window.top) {
                return;
            }

            const params = new URLSearchParams(window.location.search);
            if (params.get('embedded') === 'true') {
                return;
            }

            const indicator = document.getElementById('pullRefreshIndicator');
            const pageRoot = document.getElementById('pageRoot');
            const label = indicator?.querySelector('.pull-refresh-indicator__label');
            if (!indicator || !pageRoot || !label) {
                return;
            }

            const threshold = 88;
            const maxPull = 132;
            const resistance = 0.58;
            const surfaceRatio = 0.42;
            let tracking = false;
            let startY = 0;
            let ready = false;
            let loading = false;

            const getScrollTop = () => Math.max(
                window.scrollY || 0,
                document.documentElement.scrollTop || 0,
                document.body.scrollTop || 0
            );

            const hasBlockingOverlay = () => document.querySelector('.lightbox.active, .iframe-modal.active, #legal-modal.active') !== null;

            const resetVisualState = () => {
                ready = false;
                indicator.classList.remove('is-visible', 'is-ready');
                if (!loading) {
                    indicator.classList.remove('is-loading');
                    label.textContent = 'Zum Neuladen ziehen';
                }
                indicator.style.setProperty('--pull-refresh-offset', '0px');
                pageRoot.style.setProperty('--pull-refresh-surface-offset', '0px');
            };

            const updateVisualState = (deltaY) => {
                const pulled = Math.min(maxPull, Math.max(0, deltaY * resistance));
                ready = pulled >= threshold;
                indicator.classList.add('is-visible');
                indicator.classList.toggle('is-ready', ready);
                indicator.style.setProperty('--pull-refresh-offset', `${pulled}px`);
                pageRoot.style.setProperty('--pull-refresh-surface-offset', `${Math.round(pulled * surfaceRatio)}px`);
                label.textContent = ready ? 'Loslassen zum Neuladen' : 'Zum Neuladen ziehen';
                return pulled;
            };

            const finishGesture = () => {
                if (!tracking) {
                    return;
                }

                tracking = false;

                if (!ready || loading) {
                    resetVisualState();
                    return;
                }

                loading = true;
                indicator.classList.add('is-visible', 'is-loading');
                indicator.classList.remove('is-ready');
                indicator.style.setProperty('--pull-refresh-offset', '72px');
                pageRoot.style.setProperty('--pull-refresh-surface-offset', '24px');
                label.textContent = 'Lädt neu...';

                window.setTimeout(() => {
                    window.location.reload();
                }, 120);
            };

            document.addEventListener('touchstart', (event) => {
                if (loading || hasBlockingOverlay()) {
                    return;
                }

                if (event.touches.length !== 1 || getScrollTop() > 0) {
                    return;
                }

                const target = event.target;
                if (target instanceof Element && target.closest('input, textarea, select, button, [contenteditable="true"]')) {
                    return;
                }

                startY = event.touches[0].clientY;
                tracking = startY <= Math.max(120, window.innerHeight * 0.18);
                ready = false;

                if (tracking) {
                    label.textContent = 'Zum Neuladen ziehen';
                }
            }, { passive: true });

            document.addEventListener('touchmove', (event) => {
                if (!tracking || loading) {
                    return;
                }

                if (event.touches.length !== 1 || getScrollTop() > 0) {
                    tracking = false;
                    resetVisualState();
                    return;
                }

                const deltaY = event.touches[0].clientY - startY;
                if (deltaY <= 0) {
                    resetVisualState();
                    return;
                }

                const pulled = updateVisualState(deltaY);
                if (pulled > 2) {
                    event.preventDefault();
                }
            }, { passive: false });

            document.addEventListener('touchend', finishGesture, { passive: true });
            document.addEventListener('touchcancel', () => {
                tracking = false;
                if (!loading) {
                    resetVisualState();
                }
            }, { passive: true });
        }
JS;
    }
    
    /**
     * Rendert die veröffentlichte Abschnittsstruktur.
     */
    private function renderPageSections(array $tiles): string {
        $sections = $this->buildSectionLayout($tiles, false, false);
        $html = '';
        foreach ($sections as $section) {
            if (!$this->shouldExportSection($section)) {
                $markerId = $section['markerTile']['id'] ?? 'implicit';
                LogService::debug('GeneratorService', 'Skipping hidden section', ['id' => $markerId]);
                continue;
            }

            $sectionHtml = $this->renderSectionHtml($section, true);
            if ($sectionHtml !== '') {
                $html .= $sectionHtml;
            }
        }

        return $html;
    }

    /**
     * Baut aus der linearen Tile-Liste eine Abschnittsstruktur.
     */
    private function buildSectionLayout(array $tiles, bool $includeHiddenTiles = false, bool $keepEmptySections = false): array {
        $sections = [];
        $sectionIndex = 0;
        $currentSection = $this->createSectionDescriptor(null, $sectionIndex);

        foreach ($tiles as $tile) {
            if ($this->isSectionTile($tile)) {
                if ($keepEmptySections || $currentSection['markerTile'] !== null || !empty($currentSection['tiles'])) {
                    $sections[] = $currentSection;
                }

                $sectionIndex++;
                $currentSection = $this->createSectionDescriptor($tile, $sectionIndex);
                continue;
            }

            if (!$includeHiddenTiles && isset($tile['visible']) && $tile['visible'] === false) {
                continue;
            }

            $currentSection['tiles'][] = $tile;
        }

        if ($keepEmptySections || $currentSection['markerTile'] !== null || !empty($currentSection['tiles'])) {
            $sections[] = $currentSection;
        }

        return array_values(array_filter($sections, static function($section) use ($keepEmptySections) {
            return $keepEmptySections || $section['markerTile'] !== null || !empty($section['tiles']);
        }));
    }

    /**
     * Erstellt die interne Abschnittsbeschreibung.
     */
    private function createSectionDescriptor(?array $markerTile, int $index): array {
        $idSeed = $markerTile['id'] ?? ('implicit_' . $index);
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$idSeed);

        return [
            'id' => 'section_' . $safeId,
            'index' => $index,
            'markerTile' => $markerTile,
            'config' => $this->normalizeSectionConfig($markerTile['data'] ?? []),
            'tiles' => []
        ];
    }

    /**
     * Normalisiert die Daten eines Abschnittsmarkers.
     */
    private function normalizeSectionConfig(array $data): array {
        $backgroundMode = in_array(($data['backgroundMode'] ?? ''), ['default', 'accent1', 'accent2', 'accent3', 'image'], true)
            ? $data['backgroundMode']
            : 'default';

        $legacyAttachmentRaw = (string)($data['backgroundAttachment'] ?? 'content');
        $legacyMotionPercent = match ($legacyAttachmentRaw) {
            'viewport', 'fixed' => 100,
            'parallax' => 60,
            default => 0
        };
        $backgroundMotionPercent = array_key_exists('backgroundMotionPercent', $data)
            ? (int)$data['backgroundMotionPercent']
            : $legacyMotionPercent;
        if ($backgroundMotionPercent < 0 || $backgroundMotionPercent > 100) {
            $backgroundMotionPercent = $legacyMotionPercent;
        }
        $backgroundAttachment = $backgroundMotionPercent >= 100
            ? 'viewport'
            : ($backgroundMotionPercent > 0 ? 'parallax' : 'content');

        $backgroundDisplay = in_array(($data['backgroundDisplay'] ?? ''), ['cover', 'tile'], true)
            ? $data['backgroundDisplay']
            : 'cover';

        $backgroundImage = is_string($data['backgroundImage'] ?? null) ? trim($data['backgroundImage']) : '';
        if (!$this->isValidMediaPath($backgroundImage)) {
            $backgroundImage = '';
        }

        $overlayEnabled = $backgroundMode === 'image' && !empty($data['overlayEnabled']);
        $overlayColorEnabled = $overlayEnabled
            && (!array_key_exists('overlayColorEnabled', $data) || !empty($data['overlayColorEnabled']));
        $overlayBlurEnabled = $overlayEnabled && !empty($data['overlayBlurEnabled']);

        $overlayColor = is_string($data['overlayColor'] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $data['overlayColor']) === 1
            ? $data['overlayColor']
            : '#000000';

        $overlayOpacity = (int)($data['overlayOpacity'] ?? 35);
        if ($overlayOpacity < 0 || $overlayOpacity > 100) {
            $overlayOpacity = 35;
        }

        $overlayBlurStrength = (int)($data['overlayBlurStrength'] ?? 24);
        if ($overlayBlurStrength < 0 || $overlayBlurStrength > 100) {
            $overlayBlurStrength = 24;
        }

        return [
            'title' => trim((string)($data['title'] ?? '')),
            'backgroundMode' => $backgroundMode,
            'backgroundImage' => $backgroundImage,
            'backgroundAttachment' => $backgroundAttachment,
            'backgroundMotionPercent' => $backgroundMotionPercent,
            'backgroundDisplay' => $backgroundDisplay,
            'overlayEnabled' => $overlayEnabled,
            'overlayColorEnabled' => $overlayColorEnabled,
            'overlayBlurEnabled' => $overlayBlurEnabled,
            'overlayColor' => $overlayColor,
            'overlayOpacity' => $overlayOpacity,
            'overlayBlurStrength' => $overlayBlurStrength
        ];
    }

    /**
     * Rendert einen Abschnitts-Wrapper.
     */
    private function renderSectionHtml(array $section, bool $applyScheduledVisibility): string {
        $sectionTileHtml = '';
        foreach ($section['tiles'] as $tile) {
            $tileHtml = $this->renderSingleTile($tile, $applyScheduledVisibility);
            if ($tileHtml !== null) {
                $sectionTileHtml .= $tileHtml;
            }
        }

        if ($sectionTileHtml === '' && $section['markerTile'] === null) {
            return '';
        }

        $config = $section['config'];
        $markerTile = $section['markerTile'];
        $sectionClasses = ['page-section', 'page-section--' . $config['backgroundMode']];

        if ($markerTile !== null) {
            $sectionClasses[] = 'page-section--marked';
        } else {
            $sectionClasses[] = 'page-section--implicit';
        }

        if ($config['backgroundMode'] === 'image') {
            if (($config['backgroundMotionPercent'] ?? 0) > 0) {
                $sectionClasses[] = 'page-section--bg-motion';
            } else {
                $sectionClasses[] = 'page-section--bg-stretch';
            }
        }

        $scheduleAttrs = $markerTile ? $this->getScheduleAttributes($markerTile) : '';
        $styleDeclarations = $this->buildSectionStyleDeclarations($config);

        if ($applyScheduledVisibility && $markerTile && $scheduleAttrs !== '' && !$this->isTileVisibleBySchedule($markerTile)) {
            $styleDeclarations[] = 'display:none';
        }

        $sectionClassAttr = htmlspecialchars(implode(' ', $sectionClasses), ENT_QUOTES, 'UTF-8');
        $sectionIdAttr = htmlspecialchars($section['id'], ENT_QUOTES, 'UTF-8');
        $styleAttr = $this->buildStyleAttribute($styleDeclarations);
        $markerIdAttr = $markerTile
            ? ' data-section-marker-id="' . htmlspecialchars((string)$markerTile['id'], ENT_QUOTES, 'UTF-8') . '"'
            : '';

        return "<section class=\"{$sectionClassAttr}\" data-section-id=\"{$sectionIdAttr}\"{$markerIdAttr}{$scheduleAttrs}{$styleAttr}>\n"
            . "    <div class=\"page-section__background\" aria-hidden=\"true\"></div>\n"
            . "    <div class=\"page-section__surface\">\n"
            . "        <div class=\"tile-grid\">\n"
            . $sectionTileHtml
            . "        </div>\n"
            . "    </div>\n"
            . "</section>\n";
    }

    /**
     * Erzeugt CSS-Variablen für einen Abschnitt.
     */
    private function buildSectionStyleDeclarations(array $config): array {
        $backgroundColor = 'transparent';
        if ($config['backgroundMode'] === 'accent1') {
            $backgroundColor = 'var(--accent-color)';
        } elseif ($config['backgroundMode'] === 'accent2') {
            $backgroundColor = 'var(--accent-color-2)';
        } elseif ($config['backgroundMode'] === 'accent3') {
            $backgroundColor = 'var(--accent-color-3)';
        }

        $backgroundImage = 'none';
        if ($config['backgroundMode'] === 'image' && $config['backgroundImage'] !== '') {
            $backgroundImage = "url('" . htmlspecialchars($config['backgroundImage'], ENT_QUOTES, 'UTF-8') . "')";
        }

        $backgroundBlur = $config['overlayBlurEnabled']
            ? round(($config['overlayBlurStrength'] / 100) * 24, 2)
            : 0;
        $backgroundScale = $config['overlayBlurEnabled']
            ? number_format(1 + ($config['overlayBlurStrength'] / 1000), 3, '.', '')
            : '1';
        $motionFactor = (float)max(0, min(100, (int)($config['backgroundMotionPercent'] ?? 0))) / 100;

        return [
            '--section-background-color:' . $backgroundColor,
            '--section-background-image:' . $backgroundImage,
            '--section-background-size:' . ($config['backgroundDisplay'] === 'tile'
                ? 'auto'
                : 'cover'),
            '--section-background-repeat:' . ($config['backgroundDisplay'] === 'tile' ? 'repeat' : 'no-repeat'),
            '--section-background-attachment:scroll',
            '--section-overlay-color:' . $config['overlayColor'],
            '--section-overlay-opacity:' . ($config['overlayColorEnabled'] ? (string)($config['overlayOpacity'] / 100) : '0'),
            '--section-background-blur:' . $backgroundBlur . 'px',
            '--section-background-scale:' . $backgroundScale,
            '--section-motion-factor:' . $motionFactor,
            '--section-motion-height:112vh',
            '--section-motion-top:-6vh'
        ];
    }

    /**
     * Baut ein style-Attribut aus CSS-Deklarationen.
     */
    private function buildStyleAttribute(array $declarations): string {
        $declarations = array_values(array_filter($declarations, static function($value) {
            return $value !== null && $value !== '';
        }));

        if (empty($declarations)) {
            return '';
        }

        return ' style="' . htmlspecialchars(implode(';', $declarations) . ';', ENT_QUOTES, 'UTF-8') . '"';
    }

    /**
     * Prüft, ob ein Abschnitt im Export grundsätzlich berücksichtigt wird.
     */
    private function shouldExportSection(array $section): bool {
        $markerTile = $section['markerTile'];
        if ($markerTile === null) {
            return true;
        }

        return !isset($markerTile['visible']) || $markerTile['visible'] !== false;
    }

    /**
     * Prüft, ob eine URL ein erlaubter Media-Pfad ist.
     */
    private function isValidMediaPath(string $path): bool {
        return $path !== '' && preg_match('#^/backend/media/[a-z0-9/_\-.]+$#i', $path) === 1;
    }

    /**
     * Prüft, ob eine Tile ein Abschnittsmarker ist.
     */
    private function isSectionTile(array $tile): bool {
        return ($tile['type'] ?? '') === 'section';
    }

    /**
     * Prüft, ob ein Tile-Typ immer über die volle Breite gerendert wird.
     */
    private function isFullWidthType(string $type): bool {
        return in_array($type, ['separator', 'section'], true);
    }

    /**
     * Generiert data-Attribute für Zeitsteuerung.
     */
    private function getScheduleAttributes(array $tile): string {
        if (!isset($tile['visibilitySchedule']) || empty($tile['visibilitySchedule'])) {
            return '';
        }

        $schedule = $tile['visibilitySchedule'];
        $attrs = '';

        if (!empty($schedule['showFrom'])) {
            $attrs .= ' data-show-from="' . htmlspecialchars($schedule['showFrom'], ENT_QUOTES, 'UTF-8') . '"';
        }

        if (!empty($schedule['showUntil'])) {
            $attrs .= ' data-show-until="' . htmlspecialchars($schedule['showUntil'], ENT_QUOTES, 'UTF-8') . '"';
        }

        return $attrs;
    }
    
    /**
     * Lädt und kombiniert alle shared CSS-Dateien
     */
    private function loadSharedCSS(): string {
        $sharedDir = __DIR__ . '/../../assets/css/shared/';
        $files = ['variables.css', 'base.css', 'grid.css', 'sections.css', 'tiles.css', 'header.css', 'footer.css', 'components.css'];
        
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
     * Lädt eine einzelne Shared-CSS-Datei.
     */
    private function loadSharedCSSFile(string $file): string {
        $path = __DIR__ . '/../../assets/css/shared/' . $file;

        if (!file_exists($path)) {
            LogService::warning('GeneratorService', 'Shared CSS file missing', ['file' => $path]);
            return '';
        }

        return "/* {$file} */\n" . file_get_contents($path) . "\n";
    }

    /**
     * Rendert mehrzeiligen Klartext sicher fuer den Footer.
     */
    private function renderFooterText($text): string {
        if (!is_string($text)) {
            return '';
        }

        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($text === '') {
            return '';
        }

        return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Rendert die sichtbaren Impressum/Datenschutz-Buttons.
     */
    private function renderLegalActions(array $legal): string {
        if (empty($legal['enabled'])) {
            return '';
        }

        $labels = [
            'imprint' => 'Impressum',
            'privacy' => 'Datenschutz'
        ];
        $routes = [
            'imprint' => '/impressum',
            'privacy' => '/datenschutz'
        ];
        $actions = [];

        foreach ($labels as $key => $label) {
            $entry = $legal[$key] ?? [];
            if (!SecurityHelper::isLegalEntryConfigured($entry)) {
                continue;
            }

            $labelHtml = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            $route = htmlspecialchars($routes[$key], ENT_QUOTES, 'UTF-8');
            if (($entry['mode'] ?? 'off') === 'link') {
                $target = htmlspecialchars($entry['link'], ENT_QUOTES, 'UTF-8');
                $actions[] = "<a class=\"site-footer__legal-button\" href=\"{$route}\" data-legal-key=\"{$key}\" data-legal-mode=\"link\" data-legal-target=\"{$target}\">{$labelHtml}</a>";
                continue;
            }

            if (($entry['mode'] ?? 'off') === 'text') {
                $actions[] = "<a class=\"site-footer__legal-button\" href=\"{$route}\" data-legal-key=\"{$key}\" data-legal-mode=\"text\">{$labelHtml}</a>";
            }
        }

        return implode('', $actions);
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
        $legacyMotion = in_array(($theme['narrowBackgroundImageMotion'] ?? ''), ['fixed', 'parallax', 'stretch'], true)
            ? $theme['narrowBackgroundImageMotion']
            : 'stretch';
        $legacyMotionPercent = match ($legacyMotion) {
            'fixed' => 100,
            'parallax' => 60,
            default => 0
        };
        $motionPercent = (int)($theme['narrowBackgroundMotionPercent'] ?? $legacyMotionPercent);
        if ($motionPercent < 0 || $motionPercent > 100) {
            $motionPercent = $legacyMotionPercent;
        }
        $angle = (int)($theme['narrowGradientAngle'] ?? 180);
        if ($angle < 0 || $angle > 360) {
            $angle = 180;
        }
        $overlayOpacity = (int)($theme['narrowBackgroundOverlayOpacity'] ?? 35);
        if ($overlayOpacity < 0 || $overlayOpacity > 100) {
            $overlayOpacity = 35;
        }
        $overlayBlurStrength = (int)($theme['narrowBackgroundOverlayBlurStrength'] ?? 24);
        if ($overlayBlurStrength < 0 || $overlayBlurStrength > 100) {
            $overlayBlurStrength = 24;
        }

        $image = is_string($theme['narrowBackgroundImage'] ?? null) ? trim($theme['narrowBackgroundImage']) : '';
        if (!preg_match('#^/backend/media/[a-z0-9/_\-.]+$#i', $image)) {
            $image = '';
        }
        $overlayEnabled = $mode === 'image' && !empty($theme['narrowBackgroundOverlayEnabled']);
        $overlayColorEnabled = $overlayEnabled
            && (!array_key_exists('narrowBackgroundOverlayColorEnabled', $theme) || !empty($theme['narrowBackgroundOverlayColorEnabled']));
        $overlayBlurEnabled = $overlayEnabled && !empty($theme['narrowBackgroundOverlayBlurEnabled']);

        return [
            'mode' => $mode,
            'color' => $isValidHexColor($theme['narrowBackgroundColor'] ?? null, '#1a1a2e'),
            'gradientColor1' => $isValidHexColor($theme['narrowGradientColor1'] ?? null, '#1a1a2e'),
            'gradientColor2' => $isValidHexColor($theme['narrowGradientColor2'] ?? null, '#16213e'),
            'gradientAngle' => $angle,
            'image' => $image,
            'imageDisplay' => $display,
            'motionPercent' => $motionPercent,
            'overlayEnabled' => $overlayEnabled,
            'overlayColorEnabled' => $overlayColorEnabled,
            'overlayBlurEnabled' => $overlayBlurEnabled,
            'overlayColor' => $isValidHexColor($theme['narrowBackgroundOverlayColor'] ?? null, '#000000'),
            'overlayOpacity' => $overlayOpacity / 100,
            'overlayBlurStrength' => $overlayBlurStrength,
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
        $legalJS = $this->getLegalModalJS();
        $pullToRefreshCSS = $this->getPullToRefreshCSS();
        $pullToRefreshJS = $this->getPullToRefreshJS();
        
        // Shared CSS laden
        $sharedCSS = $this->loadSharedCSS();
        
        // Defaults
        $site = $settings['site'] ?? [];
        $theme = $settings['theme'] ?? [];
        $siteTitle = htmlspecialchars($site['title'] ?? '');
        $siteTitleRaw = $site['title'] ?? '';
        $hideHeaderTitle = !empty($site['hideHeaderTitle']);
        $headerImage = $site['headerImage'] ?? null;
        $headerImagePlaceholder = $site['headerImagePlaceholder'] ?? null;
        $headerImageWidth = (int)($site['headerImageWidth'] ?? 0);
        $headerImageHeight = (int)($site['headerImageHeight'] ?? 0);
        $headerFocusPoint = htmlspecialchars($site['headerFocusPoint'] ?? 'center center');
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
        $narrowOverlayDisplay = $narrowConfig['overlayColorEnabled'] ? 'block' : 'none';
        $narrowShadow = $narrowConfig['contentShadow'] ? '0 0 60px rgba(0,0,0,0.4)' : 'none';
        $narrowMotionFactor = (float)max(0, min(100, (int)$narrowConfig['motionPercent'])) / 100;
        $narrowHasMotion = $narrowConfig['mode'] === 'image' && $narrowConfig['motionPercent'] > 0;
        $narrowImageSize = $narrowConfig['imageDisplay'] === 'tile'
            ? 'auto'
            : 'cover';
        $narrowImageRepeat = $narrowConfig['imageDisplay'] === 'tile' ? 'repeat' : 'no-repeat';
        $narrowImageAttachment = 'scroll';
        $narrowMotionClass = $narrowHasMotion ? ' has-motion' : '';
        $narrowBlurAmount = $narrowConfig['overlayBlurEnabled']
            ? number_format(($narrowConfig['overlayBlurStrength'] / 100) * 24, 2, '.', '')
            : '0';
        $narrowBackgroundScale = $narrowConfig['overlayBlurEnabled']
            ? number_format(1 + ($narrowConfig['overlayBlurStrength'] / 1000), 3, '.', '')
            : '1';
        
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
        $showHeaderTitle = !$hideHeaderTitle && !empty($siteTitleRaw);
        $titleHtml = $showHeaderTitle ? "<h1 class=\"site-title\">{$siteTitle}</h1>" : '';
        
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
        } elseif ($showHeaderTitle) {
            // Minimaler Header nur wenn Titel vorhanden
            $headerHtml = <<<HTML
    <header class="site-header site-header--minimal">
        <h1 class="site-title">{$siteTitle}</h1>
    </header>
HTML;
        }
        
        // Footer + Rechtsmodal
        $footerHtml = $this->renderFooterMarkup($settings);
        $legalModalHtml = $this->renderLegalModalMarkup($settings);
        
        // Narrow Layout (optional: zentrierter Container mit begrenzter Breite)
        $narrowCSS = '';
        $narrowSharedCSS = '';
        $narrowOpen = '';
        $narrowClose = '';
        $bodyClassAttr = '';
        if ($narrowLayout) {
            $narrowSharedCSS = $this->loadSharedCSSFile('narrow.css');
            $narrowCSS = <<<CSS
        /* Narrow Layout */
        body.narrow-layout {
            --narrow-width: {$narrowWidth}px;
            --narrow-backdrop: {$narrowBackdrop};
            --narrow-backdrop-fallback: {$narrowConfig['color']};
            --narrow-image-repeat: {$narrowImageRepeat};
            --narrow-image-size: {$narrowImageSize};
            --narrow-image-attachment: {$narrowImageAttachment};
            --narrow-background-blur: {$narrowBlurAmount}px;
            --narrow-background-scale: {$narrowBackgroundScale};
            --narrow-motion-factor: {$narrowMotionFactor};
            --narrow-motion-height: 112vh;
            --narrow-motion-top: -6vh;
            --narrow-overlay-color: {$narrowConfig['overlayColor']};
            --narrow-overlay-opacity: {$narrowConfig['overlayOpacity']};
            --narrow-overlay-display: {$narrowOverlayDisplay};
            --narrow-surface-shadow: {$narrowShadow};
        }
        .page-shell__surface {
            max-width: {$narrowWidth}px;
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
{$narrowSharedCSS}
{$tileCSS}
{$narrowCSS}
{$pullToRefreshCSS}
    </style>
</head>
<body{$bodyClassAttr}>
    <div class="pull-refresh-indicator" id="pullRefreshIndicator" aria-live="polite" aria-atomic="true">
        <span class="pull-refresh-indicator__glyph" aria-hidden="true">↓</span>
        <span class="pull-refresh-indicator__label">Zum Neuladen ziehen</span>
    </div>
    <div class="page-root" id="pageRoot">
{$narrowOpen}
{$headerHtml}

    <main class="page-sections">
{$tilesHtml}
    </main>

{$footerHtml}
{$narrowClose}
    </div>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox">
        <span class="lightbox-close" data-lightbox-close role="button" tabindex="0" aria-label="Bildansicht schließen">&times;</span>
        <img src="" alt="" id="lightbox-img">
    </div>

    <!-- Iframe Modal -->
    <div class="iframe-modal" id="iframe-modal">
        <div class="iframe-modal-content iframe-modal-content--bg-default iframe-modal-content--motion-fixed">
            <div class="iframe-modal-header iframe-modal-header--default">
                <h3 class="iframe-modal-title" id="iframe-modal-title">Formular</h3>
                <button type="button" class="iframe-modal-close" data-iframe-close>&times;</button>
            </div>
            <div class="iframe-modal-body">
                <iframe src="" id="iframe-modal-frame"></iframe>
            </div>
        </div>
    </div>

{$legalModalHtml}

    <script>
{$legalJS}
{$contrastJS}
{$pullToRefreshJS}
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

        const _bgColorCache = new Map();

        function extractImageUrl(backgroundImageValue) {
            if (!backgroundImageValue || backgroundImageValue === 'none') {
                return null;
            }

            const match = backgroundImageValue.match(/url\(["']?(.*?)["']?\)/i);
            return match ? match[1] : null;
        }

        function getAverageHexColor(src) {
            if (!src) {
                return Promise.resolve(null);
            }

            if (_bgColorCache.has(src)) {
                return Promise.resolve(_bgColorCache.get(src));
            }

            return new Promise((resolve) => {
                const img = new Image();
                img.decoding = 'async';
                img.crossOrigin = 'anonymous';

                img.onload = () => {
                    try {
                        const canvas = document.createElement('canvas');
                        canvas.width = 24;
                        canvas.height = 24;
                        const ctx = canvas.getContext('2d', { willReadFrequently: true });
                        if (!ctx) {
                            _bgColorCache.set(src, null);
                            resolve(null);
                            return;
                        }

                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                        const { data } = ctx.getImageData(0, 0, canvas.width, canvas.height);

                        let r = 0;
                        let g = 0;
                        let b = 0;
                        let w = 0;

                        for (let i = 0; i < data.length; i += 4) {
                            const alpha = data[i + 3] / 255;
                            if (alpha <= 0) continue;
                            r += data[i] * alpha;
                            g += data[i + 1] * alpha;
                            b += data[i + 2] * alpha;
                            w += alpha;
                        }

                        const hex = w > 0
                            ? '#' + [r / w, g / w, b / w]
                                .map((v) => Math.max(0, Math.min(255, Math.round(v))).toString(16).padStart(2, '0'))
                                .join('')
                            : null;

                        _bgColorCache.set(src, hex);
                        resolve(hex);
                    } catch (err) {
                        _bgColorCache.set(src, null);
                        resolve(null);
                    }
                };

                img.onerror = () => {
                    _bgColorCache.set(src, null);
                    resolve(null);
                };

                img.src = src;
            });
        }

        function initBackgroundFallbackColors() {
            const narrowMedia = document.querySelector('.page-shell__backdrop-media');
            const narrowBackdrop = document.querySelector('.page-shell__backdrop');

            if (narrowMedia && narrowBackdrop) {
                const narrowImage = extractImageUrl(getComputedStyle(narrowMedia).backgroundImage);
                if (narrowImage) {
                    getAverageHexColor(narrowImage).then((hex) => {
                        if (!hex) return;
                        narrowBackdrop.style.setProperty('--narrow-backdrop-fallback', hex);
                        narrowMedia.style.setProperty('--narrow-backdrop-fallback', hex);
                    });
                }
            }

            const sectionBackgrounds = Array.from(document.querySelectorAll('.page-section__background'));
            sectionBackgrounds.forEach((bgEl) => {
                const imageUrl = extractImageUrl(getComputedStyle(bgEl).backgroundImage);
                if (!imageUrl) return;

                getAverageHexColor(imageUrl).then((hex) => {
                    if (!hex) return;
                    const section = bgEl.closest('.page-section');
                    if (section) {
                        section.style.setProperty('--section-background-color', hex);
                    }
                });
            });
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
            const backdrop = document.querySelector('.page-shell__backdrop.has-motion');
            if (!backdrop || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            let frameRequested = false;
            let currentOffset = 0;

            const update = () => {
                frameRequested = false;
                const rect = backdrop.getBoundingClientRect();
                const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                const motionFactor = Number.parseFloat(getComputedStyle(backdrop).getPropertyValue('--narrow-motion-factor')) || 0;
                const minHeight = viewportHeight * 1.12;
                const maxHeight = rect.height + (viewportHeight * 0.12);
                const height = minHeight + ((1 - motionFactor) * Math.max(0, maxHeight - minHeight));
                const top = -(height * 0.06);
                const targetOffset = (-rect.top) * motionFactor;
                const followAlpha = motionFactor >= 0.98 ? 1 : (0.18 + (motionFactor * 0.5));
                currentOffset += (targetOffset - currentOffset) * followAlpha;
                if (motionFactor >= 0.98 || Math.abs(targetOffset - currentOffset) < 0.2) {
                    currentOffset = targetOffset;
                }
                const offset = Math.round(currentOffset);
                backdrop.style.setProperty('--narrow-motion-height', String(height) + 'px');
                backdrop.style.setProperty('--narrow-motion-top', String(top) + 'px');
                backdrop.style.setProperty('--narrow-parallax-offset', String(offset) + 'px');

                if (Math.abs(targetOffset - currentOffset) >= 0.2) {
                    requestUpdate();
                }
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

        function initSectionParallax() {
            const sections = Array.from(document.querySelectorAll('.page-section--bg-motion'));
            if (sections.length === 0 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            let frameRequested = false;
            const currentOffsets = new WeakMap();

            const update = () => {
                frameRequested = false;
                sections.forEach((section) => {
                    const rect = section.getBoundingClientRect();
                    const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                    const motionFactor = Number.parseFloat(getComputedStyle(section).getPropertyValue('--section-motion-factor')) || 0;
                    const minHeight = viewportHeight * 1.12;
                    const maxHeight = rect.height + (viewportHeight * 0.12);
                    const height = minHeight + ((1 - motionFactor) * Math.max(0, maxHeight - minHeight));
                    const top = -(height * 0.06);
                    const targetOffset = (-rect.top) * motionFactor;
                    const prevOffset = currentOffsets.get(section) || 0;
                    const followAlpha = motionFactor >= 0.98 ? 1 : (0.18 + (motionFactor * 0.5));
                    let currentOffset = prevOffset + ((targetOffset - prevOffset) * followAlpha);
                    if (motionFactor >= 0.98 || Math.abs(targetOffset - currentOffset) < 0.2) {
                        currentOffset = targetOffset;
                    }
                    currentOffsets.set(section, currentOffset);
                    const offset = Math.round(currentOffset);
                    section.style.setProperty('--section-motion-height', String(height) + 'px');
                    section.style.setProperty('--section-motion-top', String(top) + 'px');
                    section.style.setProperty('--section-parallax-offset', String(offset) + 'px');
                });

                const hasPending = sections.some((section) => {
                    const rect = section.getBoundingClientRect();
                    const motionFactor = Number.parseFloat(getComputedStyle(section).getPropertyValue('--section-motion-factor')) || 0;
                    const targetOffset = (-rect.top) * motionFactor;
                    const currentOffset = currentOffsets.get(section) || 0;
                    return Math.abs(targetOffset - currentOffset) >= 0.2;
                });

                if (hasPending) {
                    requestUpdate();
                }
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
            initBackgroundFallbackColors();
            initNarrowParallax();
            initSectionParallax();
            initPullToRefresh();
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
        $tilesHtml = $this->renderPageSections($tiles);
        
        return $this->renderPage($settings, $tilesHtml);
    }
}
