<?php
/**
 * SectionTile - Abschnittsmarker für nachfolgende Tiles
 *
 * Startet einen neuen Abschnitt, der bis zum nächsten SectionTile gilt.
 * Der Marker selbst wird im Frontend nicht als normale Kachel ausgegeben,
 * sondern steuert die Abschnitts-Konfiguration im Generator.
 */

class SectionTile extends TileBase {

    public function getName(): string {
        return 'Abschnitt';
    }

    public function getDescription(): string {
        return 'Markiert einen neuen Abschnitt mit eigenem Hintergrund und eigener Sichtbarkeit';
    }

    public function getFieldMeta(): array {
        return [
            'title' => [
                'type' => 'text',
                'label' => 'Abschnitts-Titel',
                'required' => false,
                'placeholder' => 'z.B. Highlights',
                'group' => 'options'
            ],
            'backgroundMode' => [
                'type' => 'select',
                'label' => 'Hintergrund',
                'required' => true,
                'default' => 'default',
                'group' => 'style',
                'options' => [
                    'default' => 'Standard',
                    'accent1' => 'Akzent 1',
                    'accent2' => 'Akzent 2',
                    'accent3' => 'Akzent 3',
                    'image' => 'Bild'
                ]
            ],
            'backgroundImage' => [
                'type' => 'image',
                'label' => 'Hintergrundbild',
                'required' => false,
                'mediaType' => 'backgrounds',
                'uploadAction' => 'background',
                'group' => 'style'
            ],
            'backgroundAttachment' => [
                'type' => 'select',
                'label' => 'Bildbezug',
                'required' => false,
                'default' => 'content',
                'group' => 'style',
                'options' => [
                    'content' => 'Relativ zum Inhalt',
                    'viewport' => 'Relativ zum Viewport'
                ]
            ],
            'backgroundDisplay' => [
                'type' => 'select',
                'label' => 'Bilddarstellung',
                'required' => false,
                'default' => 'cover',
                'group' => 'style',
                'options' => [
                    'cover' => 'Cover',
                    'tile' => 'Gekachelt'
                ]
            ],
            'overlayEnabled' => [
                'type' => 'checkbox',
                'label' => 'Overlay aktivieren',
                'required' => false,
                'default' => false,
                'group' => 'advanced'
            ],
            'overlayColorEnabled' => [
                'type' => 'checkbox',
                'label' => 'Farbe',
                'required' => false,
                'default' => true,
                'group' => 'advanced'
            ],
            'overlayColor' => [
                'type' => 'color',
                'label' => 'Overlay-Farbe',
                'required' => false,
                'default' => '#000000',
                'group' => 'advanced'
            ],
            'overlayOpacity' => [
                'type' => 'range',
                'label' => 'Overlay-Deckkraft (%)',
                'required' => false,
                'default' => 35,
                'min' => 0,
                'max' => 100,
                'step' => 1,
                'unit' => '%',
                'group' => 'advanced'
            ],
            'overlayBlurEnabled' => [
                'type' => 'checkbox',
                'label' => 'Blur',
                'required' => false,
                'default' => false,
                'group' => 'advanced'
            ],
            'overlayBlurStrength' => [
                'type' => 'range',
                'label' => 'Blur-Stärke',
                'required' => false,
                'default' => 24,
                'min' => 0,
                'max' => 100,
                'step' => 1,
                'unit' => '%',
                'group' => 'advanced'
            ]
        ];
    }

    public function validate(array $data): array {
        $errors = [];

        $backgroundMode = $data['backgroundMode'] ?? 'default';
        if (!in_array($backgroundMode, ['default', 'accent1', 'accent2', 'accent3', 'image'], true)) {
            $errors[] = 'Ungültiger Hintergrundmodus';
        }

        $backgroundAttachment = $data['backgroundAttachment'] ?? 'content';
        if (!in_array($backgroundAttachment, ['content', 'viewport'], true)) {
            $errors[] = 'Ungültiger Bildbezug';
        }

        $backgroundDisplay = $data['backgroundDisplay'] ?? 'cover';
        if (!in_array($backgroundDisplay, ['cover', 'tile'], true)) {
            $errors[] = 'Ungültige Bilddarstellung';
        }

        $backgroundImage = trim((string)($data['backgroundImage'] ?? ''));
        if ($backgroundMode === 'image') {
            if ($backgroundImage === '') {
                $errors[] = 'Hintergrundbild ist erforderlich, wenn Bild-Hintergrund gewählt ist';
            } elseif (!$this->isValidPath($backgroundImage)) {
                $errors[] = 'Ungültiger Bildpfad';
            }
        } elseif ($backgroundImage !== '' && !$this->isValidPath($backgroundImage)) {
            $errors[] = 'Ungültiger Bildpfad';
        }

        $overlayEnabled = !empty($data['overlayEnabled']) && $backgroundMode === 'image';
        $overlayColorEnabled = $overlayEnabled
            && (!array_key_exists('overlayColorEnabled', $data) || !empty($data['overlayColorEnabled']));
        $overlayBlurEnabled = $overlayEnabled && !empty($data['overlayBlurEnabled']);

        if ($overlayColorEnabled) {
            $overlayColor = trim((string)($data['overlayColor'] ?? '#000000'));
            if ($overlayColor !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $overlayColor) !== 1) {
                $errors[] = 'Overlay-Farbe muss ein Hex-Wert wie #000000 sein';
            }

            $overlayOpacity = $data['overlayOpacity'] ?? 35;
            if (!is_numeric($overlayOpacity) || (int)$overlayOpacity < 0 || (int)$overlayOpacity > 100) {
                $errors[] = 'Overlay-Deckkraft muss zwischen 0 und 100 liegen';
            }
        }

        if ($overlayBlurEnabled) {
            $overlayBlurStrength = $data['overlayBlurStrength'] ?? 24;
            if (!is_numeric($overlayBlurStrength) || (int)$overlayBlurStrength < 0 || (int)$overlayBlurStrength > 100) {
                $errors[] = 'Blur-Stärke muss zwischen 0 und 100 liegen';
            }
        }

        return $errors;
    }

    public function render(array $data): string {
        $title = trim((string)($data['title'] ?? ''));
        $backgroundMode = $this->esc($data['backgroundMode'] ?? 'default');
        $backgroundAttachment = $this->esc($data['backgroundAttachment'] ?? 'content');
        $backgroundDisplay = $this->esc($data['backgroundDisplay'] ?? 'cover');
        $overlayEnabled = !empty($data['overlayEnabled']) && $backgroundMode === 'image';
        $overlayColorEnabled = $overlayEnabled
            && (!array_key_exists('overlayColorEnabled', $data) || !empty($data['overlayColorEnabled']));
        $overlayBlurEnabled = $overlayEnabled && !empty($data['overlayBlurEnabled']);
        $overlayOpacity = (int)($data['overlayOpacity'] ?? 35);
        $overlayBlurStrength = (int)($data['overlayBlurStrength'] ?? 24);

        $modeLabels = [
            'default' => 'Standard',
            'accent1' => 'Akzent 1',
            'accent2' => 'Akzent 2',
            'accent3' => 'Akzent 3',
            'image' => 'Bild'
        ];

        $attachmentLabels = [
            'content' => 'Inhalt',
            'viewport' => 'Viewport'
        ];

        $displayLabels = [
            'cover' => 'Cover',
            'tile' => 'Tile'
        ];

        $titleHtml = $title !== ''
            ? '<div class="section-marker-title">' . $this->esc($title) . '</div>'
            : '<div class="section-marker-title section-marker-title--muted">Ohne Titel</div>';

        $html = '<div class="section-marker-content">';
        $html .= '<div class="section-marker-label">Abschnitt</div>';
        $html .= $titleHtml;
        $html .= '<div class="section-marker-meta">';
        $html .= '<span class="section-marker-chip">Hintergrund: ' . $this->esc($modeLabels[$backgroundMode] ?? $backgroundMode) . '</span>';

        if ($backgroundMode === 'image') {
            $html .= '<span class="section-marker-chip">Bezug: ' . $this->esc($attachmentLabels[$backgroundAttachment] ?? $backgroundAttachment) . '</span>';
            $html .= '<span class="section-marker-chip">Bild: ' . $this->esc($displayLabels[$backgroundDisplay] ?? $backgroundDisplay) . '</span>';
        }

        if ($overlayColorEnabled) {
            $html .= '<span class="section-marker-chip">Overlay-Farbe: ' . $overlayOpacity . '%</span>';
        }

        if ($overlayBlurEnabled) {
            $html .= '<span class="section-marker-chip">Blur: ' . $overlayBlurStrength . '%</span>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}