<?php
/**
 * IframeTile - Eingebettete externe Inhalte (Formulare, Widgets, etc.)
 * 
 * Felder:
 * - title (Optional): Titel über dem Iframe
 * - url (Pflicht): URL der einzubettenden Seite
 * - description (Optional): Beschreibung (wird bei Modal als Preview gezeigt)
 * - displayMode: 'inline' (direkt eingebettet) oder 'modal' (öffnet in Lightbox)
 * - aspectRatio: '16:9', '4:3', '1:1', 'custom'
 * - customHeight: Höhe in px (nur bei aspectRatio='custom')
 */

class IframeTile extends TileBase {

    private const MODAL_PRESENTATIONS = ['dialog', 'fullscreen-mobile'];
    private const MODAL_HEADER_SCHEMES = ['default', 'accent1', 'accent2', 'accent3'];
    private const MODAL_BACKGROUND_MODES = ['default', 'accent1', 'accent2', 'accent3', 'image'];
    private const MODAL_BACKGROUND_DISPLAYS = ['cover', 'tile'];
    
    public function getName(): string {
        return 'Iframe';
    }
    
    public function getDescription(): string {
        return 'Externe Inhalte einbetten (Formulare, Widgets, etc.)';
    }
    
    public function getFieldMeta(): array {
        return [
            'title' => [
                'type' => 'text',
                'label' => 'Titel',
                'required' => true,
                'placeholder' => 'Titel für Übersicht im Editor'
            ],
            'showTitle' => [
                'type' => 'checkbox',
                'label' => 'Titel auf Seite anzeigen',
                'required' => false,
                'default' => false
            ],
            'url' => [
                'type' => 'url',
                'label' => 'Iframe-URL',
                'required' => true,
                'placeholder' => 'https://forms.example.com/...'
            ],
            'description' => [
                'type' => 'textarea',
                'label' => 'Beschreibung',
                'required' => false,
                'placeholder' => 'Kurze Beschreibung (bei Modal als Vorschau sichtbar)'
            ],
            'displayMode' => [
                'type' => 'select',
                'label' => 'Anzeigemodus',
                'required' => false,
                'options' => [
                    'inline' => 'Inline (direkt eingebettet)',
                    'modal' => 'Modal (öffnet bei Klick)'
                ],
                'default' => 'inline'
            ],
            'modalPresentation' => [
                'type' => 'select',
                'label' => 'Modal-Darstellung',
                'required' => false,
                'group' => 'modal',
                'options' => [
                    'dialog' => 'Dialog (Desktop + Mobil mit Rand)',
                    'fullscreen-mobile' => 'Fullscreen auf Mobil (ohne Eckenrundung)'
                ],
                'default' => 'dialog',
                'hint' => 'Nur aktiv, wenn Anzeigemodus = Modal.'
            ],
            'modalHeaderScheme' => [
                'type' => 'select',
                'label' => 'Header-Farbschema',
                'required' => false,
                'group' => 'modal',
                'options' => [
                    'default' => 'Standard',
                    'accent1' => 'Akzent 1',
                    'accent2' => 'Akzent 2',
                    'accent3' => 'Akzent 3'
                ],
                'default' => 'default'
            ],
            'modalBackgroundMode' => [
                'type' => 'select',
                'label' => 'Modal-Hintergrund',
                'required' => false,
                'group' => 'modalStyle',
                'options' => [
                    'default' => 'Standard',
                    'accent1' => 'Akzent 1',
                    'accent2' => 'Akzent 2',
                    'accent3' => 'Akzent 3',
                    'image' => 'Bild'
                ],
                'default' => 'default'
            ],
            'modalBackgroundColorOverrideEnabled' => [
                'type' => 'checkbox',
                'label' => 'Eigene Hintergrundfarbe aktivieren',
                'required' => false,
                'default' => false,
                'group' => 'modalStyle'
            ],
            'modalBackgroundColorOverride' => [
                'type' => 'color',
                'label' => 'Hintergrund-Override',
                'required' => false,
                'group' => 'modalStyle',
                'default' => '#f5f5f5',
                'hint' => 'Optional: ueberschreibt den Farbmodus mit einem eigenen Farbwert.'
            ],
            'modalBackgroundImage' => [
                'type' => 'image',
                'label' => 'Hintergrundbild',
                'required' => false,
                'mediaType' => 'backgrounds',
                'uploadAction' => 'background',
                'group' => 'modalStyle'
            ],
            'modalBackgroundDisplay' => [
                'type' => 'select',
                'label' => 'Bilddarstellung',
                'required' => false,
                'group' => 'modalStyle',
                'options' => [
                    'cover' => 'Cover',
                    'tile' => 'Gekachelt'
                ],
                'default' => 'cover'
            ],
            'modalBackgroundMotionPercent' => [
                'type' => 'range',
                'label' => 'Bildbewegung (%)',
                'required' => false,
                'group' => 'modalStyle',
                'default' => 100,
                'min' => 0,
                'max' => 100,
                'step' => 1,
                'unit' => '%',
                'hint' => '0% = gestreckt, 100% = fixiert. Parallax liegt dazwischen.'
            ],
            'modalOverlayEnabled' => [
                'type' => 'checkbox',
                'label' => 'Overlay aktivieren',
                'required' => false,
                'default' => false,
                'group' => 'modalAdvanced'
            ],
            'modalOverlayColorEnabled' => [
                'type' => 'checkbox',
                'label' => 'Overlay-Farbe',
                'required' => false,
                'default' => true,
                'group' => 'modalAdvanced'
            ],
            'modalOverlayColor' => [
                'type' => 'color',
                'label' => 'Overlay-Farbe',
                'required' => false,
                'default' => '#000000',
                'group' => 'modalAdvanced'
            ],
            'modalOverlayOpacity' => [
                'type' => 'range',
                'label' => 'Overlay-Deckkraft (%)',
                'required' => false,
                'default' => 35,
                'min' => 0,
                'max' => 100,
                'step' => 1,
                'unit' => '%',
                'group' => 'modalAdvanced'
            ],
            'modalOverlayBlurEnabled' => [
                'type' => 'checkbox',
                'label' => 'Overlay-Blur',
                'required' => false,
                'default' => false,
                'group' => 'modalAdvanced'
            ],
            'modalOverlayBlurStrength' => [
                'type' => 'range',
                'label' => 'Blur-Staerke (%)',
                'required' => false,
                'default' => 24,
                'min' => 0,
                'max' => 100,
                'step' => 1,
                'unit' => '%',
                'group' => 'modalAdvanced'
            ],
            'aspectRatio' => [
                'type' => 'select',
                'label' => 'Seitenverhältnis',
                'required' => false,
                'options' => [
                    '16:9' => '16:9 (Breitbild)',
                    '4:3' => '4:3 (Standard)',
                    '1:1' => '1:1 (Quadrat)',
                    'custom' => 'Benutzerdefinierte Höhe'
                ],
                'default' => '16:9'
            ],
            'customHeight' => [
                'type' => 'number',
                'label' => 'Höhe in Pixel (nur bei benutzerdefiniert)',
                'required' => false,
                'placeholder' => '500',
                'default' => 500
            ]
        ];
    }
    
    public function validate(array $data): array {
        $errors = [];
        
        if (empty(trim($data['title'] ?? ''))) {
            $errors[] = 'Titel ist erforderlich (für Übersicht im Editor)';
        }
        
        if (empty($data['url'])) {
            $errors[] = 'URL ist erforderlich';
        } elseif (!$this->isValidUrl($data['url'])) {
            $errors[] = 'Ungültige URL';
        }
        
        // HTTPS empfohlen (Mixed Content)
        if (!empty($data['url']) && strpos($data['url'], 'http://') === 0) {
            // Nur Warnung, kein Error
            LogService::warning('IframeTile', 'HTTP URL used, may cause mixed content issues', ['url' => $data['url']]);
        }
        
        if (($data['aspectRatio'] ?? '') === 'custom') {
            $height = intval($data['customHeight'] ?? 0);
            if ($height < 100 || $height > 2000) {
                $errors[] = 'Höhe muss zwischen 100 und 2000 Pixel sein';
            }
        }

        if (($data['displayMode'] ?? 'inline') === 'modal') {
            if (!in_array(($data['modalPresentation'] ?? 'dialog'), self::MODAL_PRESENTATIONS, true)) {
                $errors[] = 'Ungültige Modal-Darstellung';
            }

            if (!in_array(($data['modalHeaderScheme'] ?? 'default'), self::MODAL_HEADER_SCHEMES, true)) {
                $errors[] = 'Ungültiges Header-Farbschema';
            }

            $backgroundMode = $data['modalBackgroundMode'] ?? 'default';
            if (!in_array($backgroundMode, self::MODAL_BACKGROUND_MODES, true)) {
                $errors[] = 'Ungültiger Modal-Hintergrundmodus';
            }

            $colorOverrideEnabled = !empty($data['modalBackgroundColorOverrideEnabled']);
            $colorOverride = trim((string)($data['modalBackgroundColorOverride'] ?? ''));
            if ($colorOverrideEnabled && preg_match('/^#[0-9a-fA-F]{6}$/', $colorOverride) !== 1) {
                $errors[] = 'Ungültiger Hintergrund-Override';
            }

            if ($backgroundMode === 'image') {
                $backgroundDisplay = $data['modalBackgroundDisplay'] ?? 'cover';
                if (!in_array($backgroundDisplay, self::MODAL_BACKGROUND_DISPLAYS, true)) {
                    $errors[] = 'Ungültige Bilddarstellung';
                }

                $legacyMotionPercent = $this->mapLegacyMotionToPercent((string)($data['modalBackgroundMotion'] ?? 'fixed'));
                $backgroundMotionPercent = array_key_exists('modalBackgroundMotionPercent', $data)
                    ? (int)$data['modalBackgroundMotionPercent']
                    : $legacyMotionPercent;
                if ($backgroundMotionPercent < 0 || $backgroundMotionPercent > 100) {
                    $errors[] = 'Bildbewegung muss zwischen 0 und 100 liegen';
                }

                $backgroundImage = trim((string)($data['modalBackgroundImage'] ?? ''));
                if ($backgroundImage !== '' && !$this->isValidMediaPath($backgroundImage)) {
                    $errors[] = 'Ungültiger Hintergrundbild-Pfad';
                }

                $overlayEnabled = !empty($data['modalOverlayEnabled']);
                if ($overlayEnabled) {
                    $overlayColor = (string)($data['modalOverlayColor'] ?? '#000000');
                    if (preg_match('/^#[0-9a-fA-F]{6}$/', $overlayColor) !== 1) {
                        $errors[] = 'Ungültige Overlay-Farbe';
                    }

                    $overlayOpacity = (int)($data['modalOverlayOpacity'] ?? 35);
                    if ($overlayOpacity < 0 || $overlayOpacity > 100) {
                        $errors[] = 'Overlay-Deckkraft muss zwischen 0 und 100 liegen';
                    }

                    $overlayBlurStrength = (int)($data['modalOverlayBlurStrength'] ?? 24);
                    if ($overlayBlurStrength < 0 || $overlayBlurStrength > 100) {
                        $errors[] = 'Overlay-Blur muss zwischen 0 und 100 liegen';
                    }
                }
            }
        }
        
        return $errors;
    }
    
    public function render(array $data): string {
        $title = $this->esc($data['title'] ?? '');
        $showTitle = $data['showTitle'] ?? false;
        $url = $this->esc($data['url'] ?? '');
        $description = $this->esc($data['description'] ?? '');
        $displayMode = $data['displayMode'] ?? 'inline';
        $aspectRatio = $data['aspectRatio'] ?? '16:9';
        $customHeight = intval($data['customHeight'] ?? 500);
        
        $html = '';
        
        // Titel nur wenn showTitle aktiviert
        if ($showTitle && !empty($title)) {
            $html .= "<h3>{$title}</h3>\n";
        }
        
        // Beschreibung (bei Modal als Preview, bei Inline optional)
        if (!empty($description) && $displayMode === 'modal') {
            $html .= "<p class=\"tile-description\">{$description}</p>\n";
        }
        
        if ($displayMode === 'modal') {
            // Modal-Mode: Button zum Öffnen
            // data-Attribute statt onclick (sicher gegen HTML-decode → JS breakout)
            $modalTitle = $showTitle ? $title : '';
            $modalConfig = $this->normalizeModalConfig($data);
            $modalBackgroundImage = $modalConfig['backgroundImage'] !== ''
                ? $this->esc($modalConfig['backgroundImage'])
                : '';
            $modalBackgroundColorOverride = $modalConfig['backgroundColorOverride'] !== ''
                ? $this->esc($modalConfig['backgroundColorOverride'])
                : '';
            $overlayOpacity = (string)$modalConfig['overlayOpacity'];
            $overlayBlurStrength = (string)$modalConfig['overlayBlurStrength'];
            $html .= <<<HTML
<button class="iframe-modal-trigger"
    data-iframe-url="{$url}"
    data-iframe-title="{$modalTitle}"
    data-iframe-presentation="{$modalConfig['presentation']}"
    data-iframe-header-scheme="{$modalConfig['headerScheme']}"
    data-iframe-bg-mode="{$modalConfig['backgroundMode']}"
    data-iframe-bg-color-override="{$modalBackgroundColorOverride}"
    data-iframe-bg-image="{$modalBackgroundImage}"
    data-iframe-bg-display="{$modalConfig['backgroundDisplay']}"
    data-iframe-bg-motion-percent="{$modalConfig['backgroundMotionPercent']}"
    data-iframe-overlay-enabled="{$modalConfig['overlayEnabled']}"
    data-iframe-overlay-color-enabled="{$modalConfig['overlayColorEnabled']}"
    data-iframe-overlay-color="{$modalConfig['overlayColor']}"
    data-iframe-overlay-opacity="{$overlayOpacity}"
    data-iframe-overlay-blur-enabled="{$modalConfig['overlayBlurEnabled']}"
    data-iframe-overlay-blur-strength="{$overlayBlurStrength}">
    <span class="iframe-modal-icon">↗️</span>
    <span>Formular öffnen</span>
</button>
HTML;
        } else {
            // Inline-Mode: Responsiver Container
            $paddingBottom = $this->getPaddingForRatio($aspectRatio);
            $heightStyle = $aspectRatio === 'custom' 
                ? "height: {$customHeight}px; padding-bottom: 0;" 
                : "padding-bottom: {$paddingBottom};";
            
            $html .= <<<HTML
<div class="tile-iframe-container" style="{$heightStyle}">
    <iframe src="{$url}" frameborder="0" allowfullscreen loading="lazy"></iframe>
</div>
HTML;
        }
        
        // Beschreibung unter Inline-Iframe
        if (!empty($description) && $displayMode === 'inline') {
            $html .= "<p class=\"tile-caption\">{$description}</p>\n";
        }
        
        return $html;
    }
    
    /**
     * Berechnet padding-bottom für Aspect-Ratio
     */
    private function getPaddingForRatio(string $ratio): string {
        switch ($ratio) {
            case '16:9': return '56.25%';
            case '4:3': return '75%';
            case '1:1': return '100%';
            default: return '56.25%';
        }
    }

    private function normalizeModalConfig(array $data): array {
        $presentation = in_array(($data['modalPresentation'] ?? ''), self::MODAL_PRESENTATIONS, true)
            ? $data['modalPresentation']
            : 'dialog';

        $headerScheme = in_array(($data['modalHeaderScheme'] ?? ''), self::MODAL_HEADER_SCHEMES, true)
            ? $data['modalHeaderScheme']
            : 'default';

        $backgroundMode = in_array(($data['modalBackgroundMode'] ?? ''), self::MODAL_BACKGROUND_MODES, true)
            ? $data['modalBackgroundMode']
            : 'default';

        $backgroundDisplay = in_array(($data['modalBackgroundDisplay'] ?? ''), self::MODAL_BACKGROUND_DISPLAYS, true)
            ? $data['modalBackgroundDisplay']
            : 'cover';

        $legacyMotionPercent = $this->mapLegacyMotionToPercent((string)($data['modalBackgroundMotion'] ?? 'fixed'));
        $backgroundMotionPercent = array_key_exists('modalBackgroundMotionPercent', $data)
            ? (int)$data['modalBackgroundMotionPercent']
            : $legacyMotionPercent;
        if ($backgroundMotionPercent < 0 || $backgroundMotionPercent > 100) {
            $backgroundMotionPercent = $legacyMotionPercent;
        }

        $backgroundImage = trim((string)($data['modalBackgroundImage'] ?? ''));
        if (!$this->isValidMediaPath($backgroundImage)) {
            $backgroundImage = '';
        }

        $backgroundColorOverrideEnabled = !empty($data['modalBackgroundColorOverrideEnabled']);
        $backgroundColorOverride = trim((string)($data['modalBackgroundColorOverride'] ?? ''));
        if (!$backgroundColorOverrideEnabled || preg_match('/^#[0-9a-fA-F]{6}$/', $backgroundColorOverride) !== 1) {
            $backgroundColorOverride = '';
        }

        $overlayEnabled = $backgroundMode === 'image' && !empty($data['modalOverlayEnabled']);
        $overlayColorEnabled = $overlayEnabled
            && (!array_key_exists('modalOverlayColorEnabled', $data) || !empty($data['modalOverlayColorEnabled']));
        $overlayBlurEnabled = $overlayEnabled && !empty($data['modalOverlayBlurEnabled']);

        $overlayColor = is_string($data['modalOverlayColor'] ?? null)
            && preg_match('/^#[0-9a-fA-F]{6}$/', $data['modalOverlayColor']) === 1
            ? $this->esc($data['modalOverlayColor'])
            : '#000000';

        $overlayOpacity = (int)($data['modalOverlayOpacity'] ?? 35);
        if ($overlayOpacity < 0 || $overlayOpacity > 100) {
            $overlayOpacity = 35;
        }

        $overlayBlurStrength = (int)($data['modalOverlayBlurStrength'] ?? 24);
        if ($overlayBlurStrength < 0 || $overlayBlurStrength > 100) {
            $overlayBlurStrength = 24;
        }

        return [
            'presentation' => $this->esc($presentation),
            'headerScheme' => $this->esc($headerScheme),
            'backgroundMode' => $this->esc($backgroundMode),
            'backgroundColorOverride' => $backgroundColorOverride,
            'backgroundImage' => $backgroundImage,
            'backgroundDisplay' => $this->esc($backgroundDisplay),
            'backgroundMotionPercent' => (string)$backgroundMotionPercent,
            'overlayEnabled' => $overlayEnabled ? '1' : '0',
            'overlayColorEnabled' => $overlayColorEnabled ? '1' : '0',
            'overlayColor' => $overlayColor,
            'overlayOpacity' => $overlayOpacity,
            'overlayBlurEnabled' => $overlayBlurEnabled ? '1' : '0',
            'overlayBlurStrength' => $overlayBlurStrength,
        ];
    }

    private function isValidMediaPath(string $path): bool {
        return $path !== '' && preg_match('#^/backend/media/[a-z0-9/_\-.]+$#i', $path) === 1;
    }

    private function mapLegacyMotionToPercent(string $motion): int {
        return match ($motion) {
            'fixed' => 100,
            'parallax' => 60,
            default => 0,
        };
    }
}
