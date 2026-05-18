<?php
/**
 * InfoboxTile - Einfache Text-Kachel mit Titel und Beschreibung
 * 
 * Felder:
 * - title (Pflicht): Überschrift
 * - description (Optional): Beschreibungstext
 */

class InfoboxTile extends TileBase {
    
    public function getName(): string {
        return 'Infobox';
    }
    
    public function getDescription(): string {
        return 'Einfache Textbox mit Titel und Beschreibung';
    }
    
    public function getFieldMeta(): array {
        return [
            'title' => [
                'type' => 'text',
                'label' => 'Titel',
                'required' => true,
                'placeholder' => 'Titel eingeben...'
            ],
            'showTitle' => [
                'type' => 'checkbox',
                'label' => 'Titel auf Seite anzeigen',
                'required' => false,
                'default' => true
            ],
            'textAlign' => [
                'type' => 'select',
                'label' => 'Textausrichtung',
                'required' => false,
                'default' => 'left',
                'options' => [
                    'left' => 'Links',
                    'center' => 'Zentriert',
                    'right' => 'Rechts'
                ]
            ],
            'description' => [
                'type' => 'textarea',
                'label' => 'Beschreibung',
                'required' => false,
                'placeholder' => 'Beschreibungstext eingeben...'
            ]
        ];
    }
    
    public function validate(array $data): array {
        $errors = [];
        
        if (empty(trim($data['title'] ?? ''))) {
            $errors[] = 'Titel ist erforderlich';
        }
        
        if (strlen($data['title'] ?? '') > 200) {
            $errors[] = 'Titel darf maximal 200 Zeichen haben';
        }
        
        if (strlen($data['description'] ?? '') > 5000) {
            $errors[] = 'Beschreibung darf maximal 5000 Zeichen haben';
        }

        $textAlign = $data['textAlign'] ?? 'left';
        if (!in_array($textAlign, ['left', 'center', 'right'], true)) {
            $errors[] = 'Ungültige Textausrichtung';
        }
        
        return $errors;
    }
    
    public function render(array $data): string {
        $title = $this->esc($data['title'] ?? '');
        $showTitle = $data['showTitle'] ?? true;
        $textAlign = $data['textAlign'] ?? 'left';
        $description = $data['description'] ?? '';

        if (!in_array($textAlign, ['left', 'center', 'right'], true)) {
            $textAlign = 'left';
        }
        
        // Beschreibung: Zeilenumbrüche zu <br> konvertieren
        $description = nl2br($this->esc($description));
        
        $html = "<div class=\"infobox-content align-{$textAlign}\">\n";
        
        if ($showTitle && !empty($title)) {
            $html .= "<h3>{$title}</h3>\n";
        }
        
        if (!empty($description)) {
            $html .= "<p>{$description}</p>\n";
        }

        $html .= "</div>\n";
        
        return $html;
    }
}
