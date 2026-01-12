<?php
/**
 * ImageTile - Bild-Kachel mit optionaler Lightbox oder Link
 * 
 * Felder:
 * - title (Optional): Bildtitel/Beschriftung
 * - image (Pflicht): Pfad zum Bild
 * - caption (Optional): Bildunterschrift
 * - lightbox (Optional): Lightbox aktivieren (default: true)
 * - link (Optional): URL für Bildklick (nur wenn Lightbox deaktiviert)
 */

class ImageTile extends TileBase {
    
    public function getName(): string {
        return 'Bild';
    }
    
    public function getDescription(): string {
        return 'Bild mit optionaler Lightbox oder Link-Funktion';
    }
    
    public function getFields(): array {
        return ['title', 'showTitle', 'image', 'caption', 'lightbox', 'link'];
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
            'image' => [
                'type' => 'image',
                'label' => 'Bild',
                'required' => true,
                'accept' => '.jpg,.jpeg,.png,.gif,.webp'
            ],
            'caption' => [
                'type' => 'text',
                'label' => 'Bildunterschrift',
                'required' => false,
                'placeholder' => 'Beschreibung unter dem Bild'
            ],
            'lightbox' => [
                'type' => 'checkbox',
                'label' => 'Lightbox aktivieren',
                'required' => false,
                'default' => true
            ],
            'link' => [
                'type' => 'url',
                'label' => 'Link-URL (nur ohne Lightbox)',
                'required' => false,
                'placeholder' => 'https://beispiel.de'
            ]
        ];
    }
    
    public function validate(array $data): array {
        $errors = [];
        
        if (empty(trim($data['title'] ?? ''))) {
            $errors[] = 'Titel ist erforderlich (für Übersicht im Editor)';
        }
        
        if (empty($data['image'])) {
            $errors[] = 'Bild ist erforderlich';
        } elseif (!$this->isValidPath($data['image'])) {
            $errors[] = 'Ungültiger Bildpfad';
        } elseif (!$this->hasAllowedExtension($data['image'], ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $errors[] = 'Ungültiges Bildformat';
        }
        
        // Link-URL validieren wenn vorhanden
        if (!empty($data['link']) && !$this->isValidUrl($data['link'])) {
            $errors[] = 'Ungültige Link-URL';
        }
        
        return $errors;
    }
    
    public function render(array $data): string {
        $title = $this->esc($data['title'] ?? '');
        $showTitle = $data['showTitle'] ?? false;
        $image = $this->esc($data['image'] ?? '');
        $caption = $this->esc($data['caption'] ?? '');
        $lightbox = $data['lightbox'] ?? true;
        $link = $this->esc($data['link'] ?? '');
        
        $html = '';
        
        // Titel nur wenn showTitle aktiviert
        if ($showTitle && !empty($title)) {
            $html .= "<h3>{$title}</h3>\n";
        }
        
        // Bild (mit Lightbox, Link, oder einfach)
        if ($lightbox) {
            // Lightbox hat Priorität - Link wird ignoriert
            $html .= <<<HTML
<div class="tile-image-container tile-image-lightbox" onclick="openLightbox('{$image}')">
    <img src="{$image}" alt="{$title}" loading="lazy">
</div>
HTML;
        } elseif (!empty($link)) {
            // Link-Funktion (nur wenn Lightbox deaktiviert)
            $html .= <<<HTML
<a href="{$link}" class="tile-image-container tile-image-link" target="_blank" rel="noopener">
    <img src="{$image}" alt="{$title}" loading="lazy">
</a>
HTML;
        } else {
            // Einfaches Bild ohne Interaktion
            $html .= <<<HTML
<div class="tile-image-container">
    <img src="{$image}" alt="{$title}" loading="lazy">
</div>
HTML;
        }
        
        // Bildunterschrift
        if (!empty($caption)) {
            $html .= "<p class=\"tile-caption\">{$caption}</p>\n";
        }
        
        return $html;
    }
}
