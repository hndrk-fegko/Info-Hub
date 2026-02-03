<?php
/**
 * SeparatorTile - Trenner/Abschnittsumbruch
 * 
 * Erzwingt einen Zeilenumbruch im Grid und kann optional
 * eine visuelle Trennlinie anzeigen.
 * 
 * Felder:
 * - height: Höhe in Pixel (0 = nur Umbruch)
 * - showLine: Linie anzeigen ja/nein
 * - lineWidth: Breite der Linie (small/medium/large)
 * - lineStyle: Stil der Linie (solid/dashed/dotted)
 */

class SeparatorTile extends TileBase {
    
    public function getName(): string {
        return 'Trenner';
    }
    
    public function getDescription(): string {
        return 'Trenner/Abschnittsumbruch mit optionaler Linie';
    }
    
    public function getFields(): array {
        return ['height', 'showLine', 'lineWidth', 'lineStyle'];
    }
    
    public function getFieldMeta(): array {
        return [
            'height' => [
                'type' => 'number',
                'label' => 'Höhe (px)',
                'required' => false,
                'default' => 40,
                'placeholder' => '40'
            ],
            'showLine' => [
                'type' => 'checkbox',
                'label' => 'Linie anzeigen',
                'required' => false,
                'default' => false
            ],
            'lineWidth' => [
                'type' => 'select',
                'label' => 'Linienbreite',
                'required' => false,
                'default' => 'medium',
                'options' => [
                    'small' => 'Kurz (30%)',
                    'medium' => 'Mittel (60%)',
                    'large' => 'Voll (100%)'
                ]
            ],
            'lineStyle' => [
                'type' => 'select',
                'label' => 'Linienstil',
                'required' => false,
                'default' => 'solid',
                'options' => [
                    'solid' => 'Durchgezogen',
                    'dashed' => 'Gestrichelt',
                    'dotted' => 'Gepunktet'
                ]
            ]
        ];
    }
    
    public function validate(array $data): array {
        $errors = [];
        
        $height = $data['height'] ?? 40;
        if (!is_numeric($height) || $height < 0 || $height > 500) {
            $errors[] = 'Höhe muss zwischen 0 und 500 Pixel liegen';
        }
        
        $lineWidth = $data['lineWidth'] ?? 'medium';
        if (!in_array($lineWidth, ['small', 'medium', 'large'])) {
            $errors[] = 'Ungültige Linienbreite';
        }
        
        $lineStyle = $data['lineStyle'] ?? 'solid';
        if (!in_array($lineStyle, ['solid', 'dashed', 'dotted'])) {
            $errors[] = 'Ungültiger Linienstil';
        }
        
        return $errors;
    }
    
    public function render(array $data): string {
        $height = intval($data['height'] ?? 40);
        $showLine = !empty($data['showLine']);
        $lineWidth = $this->esc($data['lineWidth'] ?? 'medium');
        $lineStyle = $this->esc($data['lineStyle'] ?? 'solid');
        
        $html = '<div class="separator-content"';
        
        if ($height > 0) {
            $html .= ' style="height: ' . $height . 'px;"';
        }
        
        $html .= '>';
        
        if ($showLine) {
            $html .= '<hr class="separator-line line-' . $lineWidth . ' style-' . $lineStyle . '">';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}
