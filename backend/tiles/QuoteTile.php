<?php
/**
 * QuoteTile - Zitat oder Bibelvers Kachel
 * 
 * Zeigt ein Zitat/Vers zentriert und kursiv an, mit optionaler Quellenangabe.
 * Optional kann die gesamte Kachel als Link fungieren.
 * 
 * Felder:
 * - title (Optional): Überschrift über dem Zitat
 * - quote (Pflicht): Der Zitattext
 * - source (Optional): Quellenangabe (z.B. "Johannes 3,16" oder "Martin Luther")
 * - link (Optional): URL - macht die gesamte Kachel klickbar
 * 
 * Zugehörige Dateien:
 * - QuoteTile.css: Styling für Zitat (kursiv, zentriert) und Quelle (rechts)
 */

class QuoteTile extends TileBase {
    
    public function getName(): string {
        return 'Zitat';
    }
    
    public function getDescription(): string {
        return 'Zitat oder Bibelvers mit optionaler Quellenangabe';
    }
    
    public function getFields(): array {
        return ['title', 'showTitle', 'quote', 'source', 'link'];
    }
    
    public function getFieldMeta(): array {
        return [
            'title' => [
                'type' => 'text',
                'label' => 'Titel (optional)',
                'required' => false,
                'placeholder' => 'z.B. "Vers der Woche"'
            ],
            'showTitle' => [
                'type' => 'checkbox',
                'label' => 'Titel anzeigen',
                'required' => false,
                'default' => true
            ],
            'quote' => [
                'type' => 'textarea',
                'label' => 'Zitat / Bibelvers',
                'required' => true,
                'placeholder' => 'Denn also hat Gott die Welt geliebt...'
            ],
            'source' => [
                'type' => 'text',
                'label' => 'Quelle',
                'required' => false,
                'placeholder' => 'z.B. "Johannes 3,16" oder "Martin Luther"'
            ],
            'link' => [
                'type' => 'url',
                'label' => 'Link (optional)',
                'required' => false,
                'placeholder' => 'https://... - macht Kachel klickbar'
            ]
        ];
    }
    
    public function validate(array $data): array {
        $errors = [];
        
        if (empty(trim($data['quote'] ?? ''))) {
            $errors[] = 'Zitat ist erforderlich';
        }
        
        if (strlen($data['quote'] ?? '') > 2000) {
            $errors[] = 'Zitat darf maximal 2000 Zeichen haben';
        }
        
        if (strlen($data['title'] ?? '') > 200) {
            $errors[] = 'Titel darf maximal 200 Zeichen haben';
        }
        
        if (strlen($data['source'] ?? '') > 200) {
            $errors[] = 'Quelle darf maximal 200 Zeichen haben';
        }
        
        // Link validieren wenn vorhanden
        if (!empty($data['link']) && !$this->isValidUrl($data['link'])) {
            $errors[] = 'Ungültige Link-URL';
        }
        
        return $errors;
    }
    
    public function render(array $data): string {
        $title = $this->esc($data['title'] ?? '');
        $showTitle = $data['showTitle'] ?? true;
        $quote = $this->esc($data['quote'] ?? '');
        $source = $this->esc($data['source'] ?? '');
        $link = $this->esc($data['link'] ?? '');
        
        // Zeilenumbrüche im Zitat erhalten
        $quote = nl2br($quote);
        
        $html = '';
        
        // Titel
        if ($showTitle && !empty($title)) {
            $html .= "<h3 class=\"quote-title\">{$title}</h3>\n";
        }
        
        // Content-Wrapper (für Link, safeHref blockiert javascript: etc.)
        if (!empty($link)) {
            $safeLink = $this->safeHref($data['link'] ?? '');
            $html .= "<a href=\"{$safeLink}\" class=\"quote-link\" target=\"_blank\" rel=\"noopener\">\n";
        }
        
        // Zitat
        $html .= "<blockquote class=\"quote-text\">\n";
        $html .= "    <p>{$quote}</p>\n";
        $html .= "</blockquote>\n";
        
        // Quelle
        if (!empty($source)) {
            $html .= "<cite class=\"quote-source\">{$source}</cite>\n";
        }
        
        // Link schließen
        if (!empty($link)) {
            $html .= "</a>\n";
        }
        
        return $html;
    }
}
