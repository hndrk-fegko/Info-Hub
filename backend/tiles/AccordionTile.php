<?php
/**
 * AccordionTile - Akkordeon mit auf-/zuklappbaren Bereichen
 * 
 * Kompakte Darstellung von mehreren Inhaltsblöcken.
 * Ideal für FAQs, Anleitungen, oder gruppierte Informationen.
 * 
 * Felder:
 * - title (Optional): Überschrift über dem Akkordeon
 * - section1_heading ... section10_heading: Bereichs-Überschriften
 * - section1_content ... section10_content: Bereichs-Inhalte
 * - singleOpen: Nur ein Bereich gleichzeitig offen (default: true)
 * - autoScroll: Zum geöffneten Bereich scrollen (always|mobile|never)
 * - defaultOpen: Welcher Bereich initial offen ist (-1 = keiner)
 * - fullRow: Tile nimmt volle Grid-Zeile ein
 * 
 * Zugehörige Dateien:
 * - AccordionTile.css: Styling für Akkordeon
 * - AccordionTile.js: Interaktionslogik
 */

class AccordionTile extends TileBase {
    
    private const MAX_SECTIONS = 10;
    
    public function getName(): string {
        return 'Akkordeon';
    }
    
    public function getDescription(): string {
        return 'Auf-/zuklappbare Bereiche für kompakte Infos';
    }
    
    public function getFields(): array {
        $fields = ['title', 'showTitle'];
        
        // 10 Sections
        for ($i = 1; $i <= self::MAX_SECTIONS; $i++) {
            $fields[] = "section{$i}_heading";
            $fields[] = "section{$i}_content";
        }
        
        // Optionen
        $fields[] = 'singleOpen';
        $fields[] = 'autoScroll';
        $fields[] = 'defaultOpen';
        $fields[] = 'fullRow';
        
        return $fields;
    }
    
    public function getFieldMeta(): array {
        $meta = [
            'title' => [
                'type' => 'text',
                'label' => 'Titel (optional)',
                'required' => false,
                'placeholder' => 'z.B. "Häufige Fragen"'
            ],
            'showTitle' => [
                'type' => 'checkbox',
                'label' => 'Titel anzeigen',
                'required' => false,
                'default' => true
            ]
        ];
        
        // Section-Felder
        for ($i = 1; $i <= self::MAX_SECTIONS; $i++) {
            $meta["section{$i}_heading"] = [
                'type' => 'text',
                'label' => "Bereich {$i} - Überschrift",
                'required' => $i === 1, // Nur erste Section Pflicht
                'placeholder' => 'Überschrift...',
                'group' => 'sections',
                'sectionIndex' => $i
            ];
            $meta["section{$i}_content"] = [
                'type' => 'textarea',
                'label' => "Bereich {$i} - Inhalt",
                'required' => $i === 1,
                'placeholder' => 'Inhalt...',
                'group' => 'sections',
                'sectionIndex' => $i
            ];
        }
        
        // Optionen
        $meta['singleOpen'] = [
            'type' => 'checkbox',
            'label' => 'Nur ein Bereich gleichzeitig offen',
            'required' => false,
            'default' => true,
            'group' => 'options'
        ];
        $meta['autoScroll'] = [
            'type' => 'select',
            'label' => 'Auto-Scroll zum geöffneten Bereich',
            'required' => false,
            'default' => 'mobile',
            'options' => [
                'always' => 'Immer',
                'mobile' => 'Nur auf Mobilgeräten',
                'never' => 'Nie'
            ],
            'group' => 'options'
        ];
        $meta['defaultOpen'] = [
            'type' => 'select',
            'label' => 'Initial geöffneter Bereich',
            'required' => false,
            'default' => '-1',
            'options' => $this->getDefaultOpenOptions(),
            'group' => 'options'
        ];
        $meta['fullRow'] = [
            'type' => 'checkbox',
            'label' => 'Volle Zeile im Grid',
            'required' => false,
            'default' => false,
            'hint' => 'Empfohlen wenn mehrere Bereiche gleichzeitig offen sein können',
            'group' => 'options'
        ];
        
        return $meta;
    }
    
    private function getDefaultOpenOptions(): array {
        $options = ['-1' => 'Alle geschlossen'];
        for ($i = 1; $i <= self::MAX_SECTIONS; $i++) {
            $options[(string)($i - 1)] = "Bereich {$i}";
        }
        return $options;
    }
    
    public function validate(array $data): array {
        $errors = [];
        
        // Mindestens eine Section muss gefüllt sein
        $hasSection = false;
        for ($i = 1; $i <= self::MAX_SECTIONS; $i++) {
            $heading = trim($data["section{$i}_heading"] ?? '');
            $content = trim($data["section{$i}_content"] ?? '');
            
            if (!empty($heading) && !empty($content)) {
                $hasSection = true;
            } elseif (!empty($heading) && empty($content)) {
                $errors[] = "Bereich {$i}: Inhalt fehlt";
            } elseif (empty($heading) && !empty($content)) {
                $errors[] = "Bereich {$i}: Überschrift fehlt";
            }
            
            // Längenprüfung
            if (strlen($heading) > 200) {
                $errors[] = "Bereich {$i}: Überschrift zu lang (max. 200 Zeichen)";
            }
            if (strlen($content) > 5000) {
                $errors[] = "Bereich {$i}: Inhalt zu lang (max. 5000 Zeichen)";
            }
        }
        
        if (!$hasSection) {
            $errors[] = 'Mindestens ein Bereich mit Überschrift und Inhalt erforderlich';
        }
        
        return $errors;
    }
    
    public function render(array $data): string {
        $title = $this->esc($data['title'] ?? '');
        $showTitle = $data['showTitle'] ?? true;
        $singleOpen = $data['singleOpen'] ?? true;
        $autoScroll = $data['autoScroll'] ?? 'mobile';
        $defaultOpen = (int)($data['defaultOpen'] ?? -1);
        $fullRow = $data['fullRow'] ?? false;
        
        // Tile-ID für JS
        $tileId = 'accordion_' . substr(md5(json_encode($data)), 0, 8);
        
        $html = '';
        
        // Titel
        if ($showTitle && !empty($title)) {
            $html .= "<h3 class=\"accordion-title\">{$title}</h3>\n";
        }
        
        // Akkordeon Container
        $html .= "<div class=\"accordion\" id=\"{$tileId}\" ";
        $html .= "data-single-open=\"" . ($singleOpen ? 'true' : 'false') . "\" ";
        $html .= "data-auto-scroll=\"{$autoScroll}\">\n";
        
        // Sections rendern
        $sectionIndex = 0;
        for ($i = 1; $i <= self::MAX_SECTIONS; $i++) {
            $heading = trim($data["section{$i}_heading"] ?? '');
            $content = trim($data["section{$i}_content"] ?? '');
            
            if (empty($heading) || empty($content)) {
                continue;
            }
            
            $isOpen = $sectionIndex === $defaultOpen;
            $openClass = $isOpen ? ' open' : '';
            $ariaExpanded = $isOpen ? 'true' : 'false';
            
            $safeHeading = $this->esc($heading);
            $safeContent = nl2br($this->esc($content));
            
            $html .= "    <div class=\"accordion-item{$openClass}\">\n";
            $html .= "        <button class=\"accordion-header\" aria-expanded=\"{$ariaExpanded}\">\n";
            $html .= "            <span class=\"accordion-header-text\">{$safeHeading}</span>\n";
            $html .= "            <span class=\"accordion-icon\"></span>\n";
            $html .= "        </button>\n";
            $html .= "        <div class=\"accordion-content\">\n";
            $html .= "            <div class=\"accordion-content-inner\">{$safeContent}</div>\n";
            $html .= "        </div>\n";
            $html .= "    </div>\n";
            
            $sectionIndex++;
        }
        
        $html .= "</div>\n";
        
        return $html;
    }
    
    /**
     * Gibt zusätzliche CSS-Klassen für den Tile-Wrapper zurück
     */
    public function getWrapperClasses(array $data): array {
        $classes = [];
        
        if (!empty($data['fullRow'])) {
            $classes[] = 'tile-full-row';
        }
        
        return $classes;
    }
}
