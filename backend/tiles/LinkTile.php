<?php
/**
 * LinkTile - Externe/Interne Link-Kachel
 * 
 * Felder:
 * - title (Pflicht): Link-Titel
 * - description (Optional): Beschreibungstext
 * - url (Pflicht): Ziel-URL
 * - linkText (Optional): Text auf dem Link
 * - external (Optional): In neuem Tab öffnen (default: true für externe Links)
 */

class LinkTile extends TileBase {
    
    public function getName(): string {
        return 'Link';
    }
    
    public function getDescription(): string {
        return 'Link zu externer oder interner Seite';
    }
    
    public function getFields(): array {
        return ['title', 'showTitle', 'description', 'url', 'linkText', 'external'];
    }
    
    public function getFieldMeta(): array {
        return [
            'title' => [
                'type' => 'text',
                'label' => 'Titel',
                'required' => true,
                'placeholder' => 'z.B. Gemeinde-Website'
            ],
            'showTitle' => [
                'type' => 'checkbox',
                'label' => 'Titel auf Seite anzeigen',
                'required' => false,
                'default' => true
            ],
            'description' => [
                'type' => 'textarea',
                'label' => 'Beschreibung',
                'required' => false,
                'placeholder' => 'Kurze Beschreibung...'
            ],
            'url' => [
                'type' => 'url',
                'label' => 'URL',
                'required' => true,
                'placeholder' => 'https://example.com'
            ],
            'linkText' => [
                'type' => 'text',
                'label' => 'Link-Text',
                'required' => false,
                'placeholder' => 'Mehr erfahren',
                'default' => 'Mehr erfahren'
            ],
            'external' => [
                'type' => 'checkbox',
                'label' => 'In neuem Tab öffnen',
                'required' => false,
                'default' => true
            ]
        ];
    }
    
    public function validate(array $data): array {
        $errors = [];
        
        if (empty(trim($data['title'] ?? ''))) {
            $errors[] = 'Titel ist erforderlich';
        }
        
        if (empty($data['url'])) {
            $errors[] = 'URL ist erforderlich';
        } elseif (!$this->isValidUrl($data['url']) && !$this->isValidPath($data['url'])) {
            $errors[] = 'Ungültige URL';
        }
        
        // Bei externen URLs prüfen ob https verwendet wird
        if (!empty($data['url']) && strpos($data['url'], 'http://') === 0) {
            // Warnung, kein Fehler
            // $warnings[] = 'HTTP-Links sind unsicher, bitte HTTPS verwenden';
        }
        
        return $errors;
    }
    
    public function render(array $data): string {
        $title = $this->esc($data['title'] ?? '');
        $showTitle = $data['showTitle'] ?? true;
        $description = nl2br($this->esc($data['description'] ?? ''));
        $url = $this->esc($data['url'] ?? '#');
        $linkText = $this->esc($data['linkText'] ?? 'Mehr erfahren');
        
        // Externe Links automatisch erkennen wenn nicht gesetzt
        $isExternal = $data['external'] ?? $this->isExternalUrl($data['url'] ?? '');
        $target = $isExternal ? ' target="_blank" rel="noopener noreferrer"' : '';
        $externalIcon = $isExternal ? ' ↗' : '';
        
        $html = '';
        
        if ($showTitle && !empty($title)) {
            $html .= "<h3>{$title}</h3>\n";
        }
        
        if (!empty($description)) {
            $html .= "<p>{$description}</p>\n";
        }
        
        $html .= "<a href=\"{$url}\"{$target} class=\"tile-link\">{$linkText}{$externalIcon}</a>\n";
        
        return $html;
    }
    
    /**
     * Prüft ob URL extern ist
     */
    private function isExternalUrl(string $url): bool {
        // Beginnt mit http:// oder https:// = extern
        return preg_match('/^https?:\/\//', $url) === 1;
    }
}
