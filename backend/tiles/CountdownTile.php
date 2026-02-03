<?php
/**
 * CountdownTile - Countdown zu einem Datum/Uhrzeit
 * 
 * Felder:
 * - title (Pflicht): Überschrift (für Editor-Übersicht)
 * - showTitle (Optional): Titel auf Seite anzeigen
 * - description (Optional): Beschreibungstext (z.B. "bis zur Anmeldung")
 * - targetDate (Pflicht): Zieldatum (YYYY-MM-DD)
 * - targetTime (Optional): Zielzeit (HH:MM)
 * - countMode (Optional): Anzeigemodus (days, hours, timer, dynamic)
 * - expiredText (Optional): Text nach Ablauf
 * - hideOnExpiry (Optional): Nach Ablauf ausblenden
 * 
 * Zugehörige Dateien:
 * - CountdownTile.css: Styling für Countdown-Anzeige und Timer-Modus
 * - CountdownTile.js: initCountdowns() für Live-Countdown-Aktualisierung
 */

class CountdownTile extends TileBase {
    
    public function getName(): string {
        return 'Countdown';
    }
    
    public function getDescription(): string {
        return 'Countdown zu einem Datum mit verschiedenen Anzeigemodi';
    }
    
    /**
     * Init-Funktion die bei DOMContentLoaded aufgerufen werden soll
     */
    public function getInitFunction(): ?string {
        return 'initCountdowns';
    }
    
    public function getFields(): array {
        return [
            'title', 
            'showTitle', 
            'description', 
            'targetDate', 
            'targetTime', 
            'countMode', 
            'expiredText', 
            'hideOnExpiry'
        ];
    }
    
    public function getFieldMeta(): array {
        return [
            'title' => [
                'type' => 'text',
                'label' => 'Titel',
                'required' => true,
                'placeholder' => 'z.B. Anmeldung startet'
            ],
            'showTitle' => [
                'type' => 'checkbox',
                'label' => 'Titel auf Seite anzeigen',
                'required' => false,
                'default' => true
            ],
            'description' => [
                'type' => 'text',
                'label' => 'Beschreibung',
                'required' => false,
                'placeholder' => 'z.B. bis zur Anmeldung'
            ],
            'targetDate' => [
                'type' => 'date',
                'label' => 'Zieldatum',
                'required' => true
            ],
            'targetTime' => [
                'type' => 'time',
                'label' => 'Uhrzeit (optional)',
                'required' => false,
                'default' => '00:00'
            ],
            'countMode' => [
                'type' => 'select',
                'label' => 'Anzeigemodus',
                'required' => false,
                'default' => 'dynamic',
                'options' => [
                    'dynamic' => 'Dynamisch (passt sich an)',
                    'days' => 'Tage (noch X Tage)',
                    'hours' => 'Stunden (noch X Stunden)',
                    'timer' => 'Timer (DD:HH:MM:SS)'
                ]
            ],
            'expiredText' => [
                'type' => 'text',
                'label' => 'Text nach Ablauf',
                'required' => false,
                'placeholder' => 'z.B. Jetzt anmelden!',
                'default' => 'Abgelaufen'
            ],
            'hideOnExpiry' => [
                'type' => 'checkbox',
                'label' => 'Nach Ablauf ausblenden',
                'required' => false,
                'default' => false
            ]
        ];
    }
    
    public function validate(array $data): array {
        $errors = [];
        
        if (empty(trim($data['title'] ?? ''))) {
            $errors[] = 'Titel ist erforderlich';
        }
        
        if (empty($data['targetDate'])) {
            $errors[] = 'Zieldatum ist erforderlich';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['targetDate'])) {
            $errors[] = 'Ungültiges Datumsformat (YYYY-MM-DD erwartet)';
        }
        
        if (!empty($data['targetTime']) && !preg_match('/^\d{2}:\d{2}$/', $data['targetTime'])) {
            $errors[] = 'Ungültiges Zeitformat (HH:MM erwartet)';
        }
        
        $validModes = ['dynamic', 'days', 'hours', 'timer'];
        if (!empty($data['countMode']) && !in_array($data['countMode'], $validModes)) {
            $errors[] = 'Ungültiger Anzeigemodus';
        }
        
        return $errors;
    }
    
    public function render(array $data): string {
        $title = $this->esc($data['title'] ?? '');
        $showTitle = $data['showTitle'] ?? true;
        $description = $this->esc($data['description'] ?? '');
        $targetDate = $this->esc($data['targetDate'] ?? '');
        $targetTime = $this->esc($data['targetTime'] ?? '00:00');
        $countMode = $this->esc($data['countMode'] ?? 'dynamic');
        $expiredText = $this->esc($data['expiredText'] ?? 'Abgelaufen');
        $hideOnExpiry = $data['hideOnExpiry'] ?? false;
        
        // ISO-Timestamp für JavaScript erstellen
        $targetTimestamp = "{$targetDate}T{$targetTime}:00";
        
        // Unique ID für diesen Countdown
        $countdownId = 'countdown_' . md5($targetTimestamp . $title);
        
        $html = '';
        
        if ($showTitle && !empty($title)) {
            $html .= "<h3>{$title}</h3>\n";
        }
        
        if (!empty($description)) {
            $html .= "<p class=\"countdown-description\">{$description}</p>\n";
        }
        
        // Countdown-Container
        $hideAttr = $hideOnExpiry ? 'true' : 'false';
        $html .= <<<HTML
<div class="countdown-display" 
     id="{$countdownId}" 
     data-target="{$targetTimestamp}" 
     data-mode="{$countMode}"
     data-expired-text="{$expiredText}"
     data-hide-on-expiry="{$hideAttr}">
    <div class="countdown-loading">Lädt...</div>
</div>
HTML;
        
        return $html;
    }
}
