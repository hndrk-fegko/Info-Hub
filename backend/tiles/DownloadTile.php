<?php
/**
 * DownloadTile - Datei-Download-Kachel
 * 
 * Felder:
 * - title (Pflicht): Überschrift
 * - description (Optional): Beschreibungstext
 * - file (Pflicht): Pfad zur Datei
 * - buttonText (Optional): Text auf dem Download-Button
 */

class DownloadTile extends TileBase {
    
    public function getName(): string {
        return 'Download';
    }
    
    public function getDescription(): string {
        return 'Datei-Download mit Titel und Beschreibung';
    }
    
    public function getFields(): array {
        return ['title', 'showTitle', 'description', 'file', 'buttonText'];
    }
    
    public function getFieldMeta(): array {
        return [
            'title' => [
                'type' => 'text',
                'label' => 'Titel',
                'required' => true,
                'placeholder' => 'z.B. Anmeldeformular'
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
                'placeholder' => 'Kurze Beschreibung der Datei...'
            ],
            'file' => [
                'type' => 'file',
                'label' => 'Datei',
                'required' => true,
                'accept' => '.pdf,.docx,.xlsx,.zip,.pptx,.txt'
            ],
            'buttonText' => [
                'type' => 'text',
                'label' => 'Button-Text',
                'required' => false,
                'placeholder' => 'Download',
                'default' => 'Download'
            ]
        ];
    }
    
    public function validate(array $data): array {
        $errors = [];
        
        if (empty(trim($data['title'] ?? ''))) {
            $errors[] = 'Titel ist erforderlich';
        }
        
        if (empty($data['file'])) {
            $errors[] = 'Datei ist erforderlich';
        } elseif (!$this->isValidPath($data['file'])) {
            $errors[] = 'Ungültiger Dateipfad';
        }
        
        return $errors;
    }
    
    public function render(array $data): string {
        $title = $this->esc($data['title'] ?? '');
        $showTitle = $data['showTitle'] ?? true;
        $description = nl2br($this->esc($data['description'] ?? ''));
        $file = $this->esc($data['file'] ?? '');
        $buttonText = $this->esc($data['buttonText'] ?? 'Download');
        
        // Dateiendung für Icon ermitteln
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $icon = $this->getFileIcon($ext);
        
        $html = '<div class="download-content">';
        
        if ($showTitle && !empty($title)) {
            $html .= "<h3>{$title}</h3>\n";
        }
        
        if (!empty($description)) {
            $html .= "<p>{$description}</p>\n";
        }
        
        $html .= '</div>';
        
        $safeFile = $this->safeHref($data['file'] ?? '');
        $html .= <<<HTML
<div class="download-action">
<a href="{$safeFile}" class="download-btn" download>
    <span class="download-icon">{$icon}</span>
    <span>{$buttonText}</span>
</a>
</div>
HTML;
        
        return $html;
    }
    
    /**
     * Gibt ein passendes Icon für den Dateityp zurück
     */
    private function getFileIcon(string $ext): string {
        return match($ext) {
            'pdf' => '📄',
            'docx', 'doc' => '📝',
            'xlsx', 'xls' => '📊',
            'pptx', 'ppt' => '📽️',
            'zip', 'rar' => '📦',
            'txt' => '📃',
            default => '📎'
        };
    }
}
