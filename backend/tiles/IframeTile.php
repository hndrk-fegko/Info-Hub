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
    
    public function getName(): string {
        return 'Iframe';
    }
    
    public function getDescription(): string {
        return 'Externe Inhalte einbetten (Formulare, Widgets, etc.)';
    }
    
    public function getFields(): array {
        return ['title', 'showTitle', 'url', 'description', 'displayMode', 'aspectRatio', 'customHeight'];
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
            $html .= <<<HTML
<button class="iframe-modal-trigger" data-iframe-url="{$url}" data-iframe-title="{$modalTitle}">
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
}
