<?php
/**
 * ContactTile - Kontaktkachel mit Crawler-Schutz
 * 
 * Zeigt Kontaktdaten einer Person mit Profilbild an.
 * Email und Telefon werden XOR-verschleiert gespeichert und
 * erst bei Klick clientseitig entschlüsselt (Anti-Spam).
 * 
 * Felder:
 * - title (Pflicht): Interner Titel für Editor
 * - name (Pflicht): Name der Kontaktperson
 * - role (Optional): Funktion/Rolle
 * - image (Optional): Profilbild
 * - email (Optional): Email-Adresse (wird verschleiert)
 * - phone (Optional): Telefonnummer (wird verschleiert)
 * - showEmailButton (Optional): "Email anzeigen" Button anzeigen
 * - showPhoneButton (Optional): "Telefon anzeigen" Button anzeigen
 */

class ContactTile extends TileBase {
    
    // XOR-Key für einfache Verschleierung (kein echtes Crypto nötig)
    private const XOR_KEY = 'InfoHub2026';
    
    public function getName(): string {
        return 'Kontakt';
    }
    
    public function getDescription(): string {
        return 'Kontaktperson mit Crawler-geschützter Email/Telefon';
    }
    
    public function getFields(): array {
        return ['title', 'name', 'role', 'image', 'email', 'phone', 'showEmailButton', 'showPhoneButton'];
    }
    
    public function getFieldMeta(): array {
        return [
            'title' => [
                'type' => 'text',
                'label' => 'Interner Titel',
                'required' => true,
                'placeholder' => 'z.B. "Kontakt Pastor"'
            ],
            'name' => [
                'type' => 'text',
                'label' => 'Name',
                'required' => true,
                'placeholder' => 'Vor- und Nachname'
            ],
            'role' => [
                'type' => 'text',
                'label' => 'Funktion / Rolle',
                'required' => false,
                'placeholder' => 'z.B. "Pastor", "Jugendleiter"'
            ],
            'image' => [
                'type' => 'image',
                'label' => 'Profilbild',
                'required' => false,
                'accept' => '.jpg,.jpeg,.png,.gif,.webp'
            ],
            'email' => [
                'type' => 'email',
                'label' => 'Email-Adresse',
                'required' => false,
                'placeholder' => 'name@beispiel.de'
            ],
            'phone' => [
                'type' => 'tel',
                'label' => 'Telefonnummer',
                'required' => false,
                'placeholder' => '+49 123 456789'
            ],
            'showEmailButton' => [
                'type' => 'checkbox',
                'label' => '"Email anzeigen" Button',
                'required' => false,
                'default' => true
            ],
            'showPhoneButton' => [
                'type' => 'checkbox',
                'label' => '"Telefon anzeigen" Button',
                'required' => false,
                'default' => true
            ]
        ];
    }
    
    public function validate(array $data): array {
        $errors = [];
        
        if (empty(trim($data['title'] ?? ''))) {
            $errors[] = 'Interner Titel ist erforderlich';
        }
        
        if (empty(trim($data['name'] ?? ''))) {
            $errors[] = 'Name ist erforderlich';
        }
        
        if (strlen($data['name'] ?? '') > 100) {
            $errors[] = 'Name darf maximal 100 Zeichen haben';
        }
        
        if (strlen($data['role'] ?? '') > 100) {
            $errors[] = 'Rolle darf maximal 100 Zeichen haben';
        }
        
        // Email validieren wenn vorhanden
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ungültige Email-Adresse';
        }
        
        // Bild validieren wenn vorhanden
        if (!empty($data['image'])) {
            if (!$this->isValidPath($data['image'])) {
                $errors[] = 'Ungültiger Bildpfad';
            } elseif (!$this->hasAllowedExtension($data['image'], ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $errors[] = 'Ungültiges Bildformat';
            }
        }
        
        // Mindestens eine Kontaktmöglichkeit sollte vorhanden sein (Warnung, kein Fehler)
        // if (empty($data['email']) && empty($data['phone'])) {
        //     $errors[] = 'Mindestens Email oder Telefon sollte angegeben werden';
        // }
        
        return $errors;
    }
    
    public function render(array $data): string {
        $name = $this->esc($data['name'] ?? '');
        $role = $this->esc($data['role'] ?? '');
        $image = $this->esc($data['image'] ?? '');
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $showEmailButton = $data['showEmailButton'] ?? true;
        $showPhoneButton = $data['showPhoneButton'] ?? true;
        
        // Verschleierte Daten für Frontend
        $emailEncoded = !empty($email) ? $this->encodeData($email) : '';
        $phoneEncoded = !empty($phone) ? $this->encodeData($phone) : '';
        
        $html = '<div class="contact-content">';
        
        // Profilbild
        if (!empty($image)) {
            $html .= "<div class=\"contact-image\">\n";
            $html .= "    <img src=\"{$image}\" alt=\"{$name}\">\n";
            $html .= "</div>\n";
        }
        
        $html .= '<div class="contact-info">';
        
        // Name
        $html .= "<h3 class=\"contact-name\">{$name}</h3>\n";
        
        // Rolle
        if (!empty($role)) {
            $html .= "<p class=\"contact-role\">{$role}</p>\n";
        }
        
        // Buttons für Kontaktdaten
        $html .= '<div class="contact-actions">';
        
        // Email Button
        if (!empty($email) && $showEmailButton) {
            $html .= "<button class=\"contact-reveal-btn\" onclick=\"revealContact(this, 'email', '{$emailEncoded}')\">\n";
            $html .= "    <span class=\"contact-icon\">📧</span>\n";
            $html .= "    <span class=\"contact-label\">Email anzeigen</span>\n";
            $html .= "</button>\n";
        }
        
        // Telefon Button
        if (!empty($phone) && $showPhoneButton) {
            $html .= "<button class=\"contact-reveal-btn\" onclick=\"revealContact(this, 'phone', '{$phoneEncoded}')\">\n";
            $html .= "    <span class=\"contact-icon\">📞</span>\n";
            $html .= "    <span class=\"contact-label\">Telefon anzeigen</span>\n";
            $html .= "</button>\n";
        }
        
        $html .= '</div>'; // .contact-actions
        $html .= '</div>'; // .contact-info
        $html .= '</div>'; // .contact-content
        
        return $html;
    }
    
    /**
     * Verschleiert einen String mit XOR + Base64
     * 
     * Kein echtes Crypto - nur gegen automatische Crawler
     */
    private function encodeData(string $data): string {
        $key = self::XOR_KEY;
        $keyLen = strlen($key);
        $result = '';
        
        for ($i = 0; $i < strlen($data); $i++) {
            $result .= chr(ord($data[$i]) ^ ord($key[$i % $keyLen]));
        }
        
        return base64_encode($result);
    }
}
