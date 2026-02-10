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
        return ['title', 'showTitle', 'description', 'url', 'linkText', 'external', 'showDomain'];
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
            ],
            'showDomain' => [
                'type' => 'checkbox',
                'label' => 'Link-Vorschau anzeigen',
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
        $showDomain = $data['showDomain'] ?? true;
        
        // Externe Links automatisch erkennen wenn nicht gesetzt
        $isExternal = $data['external'] ?? $this->isExternalUrl($data['url'] ?? '');
        $target = $isExternal ? ' target="_blank" rel="noopener noreferrer"' : '';
        $externalIcon = $isExternal ? '<span class="external-icon">↗</span>' : '';
        $externalClass = $isExternal ? ' link-external' : '';
        
        // Domain für URL-Preview extrahieren
        $domain = '';
        if (preg_match('/^https?:\/\/([^\/]+)/', $data['url'] ?? '', $matches)) {
            $domain = $this->esc($matches[1]);
        }
        
        // Gesamte Kachel als Link-Container (safeHref blockiert javascript: etc.)
        $safeUrl = $this->safeHref($data['url'] ?? '#');
        $html = "<a href=\"{$safeUrl}\"{$target} class=\"link-card{$externalClass}\">\n";
        
        // Header: Titel + Icon im Kreis
        $html .= "<div class=\"link-header\">\n";
        
        if ($showTitle && !empty($title)) {
            $html .= "    <h3 class=\"link-title\">{$title}</h3>\n";
        }
        
        // Icon im Kreis (rechts)
        $html .= "    <span class=\"link-icon-circle\">🔗</span>\n";
        
        $html .= "</div>\n";
        
        // Content
        $html .= "<div class=\"link-content\">\n";
        
        if (!empty($description)) {
            $html .= "    <p class=\"link-description\">{$description}</p>\n";
        }
        
        // CTA Button
        $html .= "    <span class=\"link-cta\">→ {$linkText}{$externalIcon}</span>\n";
        
        $html .= "</div>\n";
        
        // URL Preview oder "öffnet in neuem Tab" Hinweis
        if ($showDomain && !empty($domain)) {
            $html .= "<span class=\"link-domain\">{$domain}</span>\n";
        } elseif ($isExternal && !$showDomain) {
            $html .= "<span class=\"link-hint\">öffnet in neuem Tab →</span>\n";
        }
        
        $html .= "</a>\n";
        
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
