<?php
/**
 * SecurityHelper - Zentrale Sicherheitsprüfungen
 * 
 * Prüft auf unsichere Konfigurationen und gibt Warnungen aus.
 * 
 * HINWEIS: config.php muss VOR diesem Service geladen werden!
 * Statische Methoden prüfen mit defined() für Robustheit.
 */

class SecurityHelper {

    private const LEGAL_TEXT_ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'b', 'i', 'ul', 'ol', 'li', 'a'];
    private const LEGAL_LINK_PROTOCOLS = ['http', 'https', 'mailto', 'tel'];
    
    /**
     * Prüft ob Debug-Mode aktiv ist
     */
    public static function isDebugMode(): bool {
        return defined('DEBUG_MODE') && constant('DEBUG_MODE') === true;
    }
    
    /**
     * Prüft ob HTTPS aktiv ist
     */
    public static function isHttps(): bool {
        // Standard HTTPS Check
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        
        // Proxy/Load Balancer Check
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }
        
        // CloudFlare Check
        if (!empty($_SERVER['HTTP_CF_VISITOR'])) {
            $visitor = json_decode($_SERVER['HTTP_CF_VISITOR'], true);
            if (isset($visitor['scheme']) && $visitor['scheme'] === 'https') {
                return true;
            }
        }
        
        // Port Check
        if (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Prüft ob Localhost (Development)
     */
    public static function isLocalhost(): bool {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        return in_array($host, ['localhost', '127.0.0.1', '::1']) 
            || strpos($host, 'localhost:') === 0
            || strpos($host, '127.0.0.1:') === 0;
    }
    
    /**
     * Gibt alle Sicherheitswarnungen zurück
     */
    public static function getSecurityStatus(): array {
        $warnings = [];
        
        if (self::isDebugMode()) {
            $warnings[] = [
                'type' => 'debug',
                'level' => self::isLocalhost() ? 'warning' : 'error',
                'message' => 'Debug-Modus ist aktiv',
                'detail' => 'In config.php DEBUG_MODE auf false setzen'
            ];
        }
        
        if (!self::isHttps() && !self::isLocalhost()) {
            $warnings[] = [
                'type' => 'https',
                'level' => 'error',
                'message' => 'Keine HTTPS-Verbindung',
                'detail' => 'SSL-Zertifikat installieren'
            ];
        }
        
        return $warnings;
    }
    
    /**
     * Gibt Security-Infos für Email zurück
     */
    public static function getEmailSecurityInfo(): string {
        $info = "\n\n---\nSicherheitshinweise:";
        $info .= "\n• Angefragt von: " . ($_SERVER['REMOTE_ADDR'] ?? 'unbekannt');
        $info .= "\n• Server: " . ($_SERVER['HTTP_HOST'] ?? 'unbekannt');
        $info .= "\n• Verbindung: " . (self::isHttps() ? '✓ Verschlüsselt (HTTPS)' : '⚠ Unverschlüsselt (HTTP)');
        
        if (self::isDebugMode()) {
            $info .= "\n• Debug-Modus: ⚠ AKTIV - In Produktion deaktivieren!";
        }
        
        if (self::isLocalhost()) {
            $info .= "\n• Umgebung: Development (localhost)";
        }
        
        $info .= "\n• Zeit: " . date('d.m.Y H:i:s');
        
        return $info;
    }
    
    /**
     * Generiert HTML für Security-Warnings Banner
     */
    public static function renderSecurityBanner(): string {
        $warnings = self::getSecurityStatus();
        
        if (empty($warnings)) {
            return '';
        }
        
        $html = '<div class="security-banner" id="securityBanner">';
        $html .= '<div class="security-banner-content">';
        $html .= '<strong>⚠️ Sicherheitshinweise:</strong> ';
        
        $messages = array_map(fn($w) => $w['message'], $warnings);
        $html .= implode(' | ', $messages);
        
        $html .= '</div>';
        $html .= '<button type="button" onclick="dismissSecurityBanner()" class="security-banner-close">×</button>';
        $html .= '</div>';
        
        $html .= '<script>
            function dismissSecurityBanner() {
                document.getElementById("securityBanner").style.display = "none";
                fetch("?dismiss_security_banner=1");
            }
        </script>';
        
        return $html;
    }
    
    /**
     * Generiert HTML für Security-Badge im Editor
     */
    public static function renderSecurityBadge(): string {
        $warnings = self::getSecurityStatus();
        
        if (empty($warnings)) {
            return '';
        }
        
        $count = count($warnings);
        $tooltip = implode('&#10;', array_map(fn($w) => "• {$w['message']}: {$w['detail']}", $warnings));
        
        $html = '<div class="security-badge" title="' . htmlspecialchars($tooltip) . '">';
        $html .= '<span class="security-badge-icon">🐛</span>';
        $html .= '<span class="security-badge-count">' . $count . '</span>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Gibt die Default-Konfiguration fuer Rechtstexte zurueck.
     */
    public static function getDefaultLegalSettings(): array {
        return [
            'enabled' => false,
            'displayStyle' => 'subtleButtons',
            'imprint' => [
                'mode' => 'off',
                'link' => '',
                'text' => ''
            ],
            'privacy' => [
                'mode' => 'off',
                'link' => '',
                'text' => ''
            ]
        ];
    }

    /**
     * Normalisiert die Legal-Settings und bereinigt Inhalt.
     */
    public static function normalizeLegalSettings($legal): array {
        $defaults = self::getDefaultLegalSettings();
        if (!is_array($legal)) {
            return $defaults;
        }

        $normalized = $defaults;
        $normalized['enabled'] = !empty($legal['enabled']);

        if (isset($legal['displayStyle']) && $legal['displayStyle'] === 'subtleButtons') {
            $normalized['displayStyle'] = 'subtleButtons';
        }

        foreach (['imprint', 'privacy'] as $entryKey) {
            $entry = $legal[$entryKey] ?? [];
            if (!is_array($entry)) {
                continue;
            }

            $mode = strtolower(trim((string) ($entry['mode'] ?? 'off')));
            if (!in_array($mode, ['off', 'link', 'text'], true)) {
                $mode = 'off';
            }

            $normalized[$entryKey]['mode'] = $mode;
            $normalized[$entryKey]['link'] = self::sanitizeLegalLink($entry['link'] ?? '');
            $normalized[$entryKey]['text'] = self::sanitizeLegalText($entry['text'] ?? '');

            if ($mode === 'link' && $normalized[$entryKey]['link'] === '') {
                $normalized[$entryKey]['mode'] = 'off';
            }

            if ($mode === 'text' && $normalized[$entryKey]['text'] === '') {
                $normalized[$entryKey]['mode'] = 'off';
            }
        }

        return $normalized;
    }

    /**
     * Prueft, ob mindestens ein Rechtseintrag konfiguriert ist.
     */
    public static function hasLegalContent($legal): bool {
        $normalized = self::normalizeLegalSettings($legal);

        foreach (['imprint', 'privacy'] as $entryKey) {
            if (self::isLegalEntryConfigured($normalized[$entryKey])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Liefert true, wenn der Eintrag im gewaehlten Modus gueltigen Inhalt hat.
     */
    public static function isLegalEntryConfigured($entry): bool {
        if (!is_array($entry)) {
            return false;
        }

        $mode = $entry['mode'] ?? 'off';
        if ($mode === 'link') {
            return !empty($entry['link']);
        }

        if ($mode === 'text') {
            return trim(strip_tags((string) ($entry['text'] ?? ''))) !== '';
        }

        return false;
    }

    /**
     * Bereinigt vereinfachtes HTML fuer modale Rechtstexte.
     */
    public static function sanitizeLegalText($html): string {
        if (!is_string($html)) {
            return '';
        }

        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = nl2br($html, false);
        $html = preg_replace('/<br\s*\/?>\s*<br\s*\/?>/i', '<br><br>', $html) ?? $html;

        if (!class_exists('DOMDocument')) {
            return strip_tags($html, '<p><br><strong><em><b><i><ul><ol><li><a>');
        }

        $previous = libxml_use_internal_errors(true);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrappedHtml = '<!DOCTYPE html><html><body><div id="legal-root">' . $html . '</div></body></html>';
        $dom->loadHTML($wrappedHtml, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);

        $root = $dom->getElementById('legal-root');
        if (!$root) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return '';
        }

        self::sanitizeLegalDomNode($root, $dom);

        $result = '';
        foreach (iterator_to_array($root->childNodes) as $childNode) {
            $result .= $dom->saveHTML($childNode);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return trim($result);
    }

    /**
     * Prueft und bereinigt Legal-Links.
     */
    public static function sanitizeLegalLink($url): string {
        if (!is_string($url)) {
            return '';
        }

        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (strpos($url, '/') === 0) {
            return preg_match('#^/[A-Za-z0-9/_\-\.~%?=&+#]*$#', $url) ? $url : '';
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, self::LEGAL_LINK_PROTOCOLS, true) ? $url : '';
    }

    private static function sanitizeLegalDomNode(DOMNode $node, DOMDocument $dom): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tagName = strtolower($child->tagName);
                if (!in_array($tagName, self::LEGAL_TEXT_ALLOWED_TAGS, true)) {
                    self::unwrapDomNode($child);
                    continue;
                }

                self::sanitizeLegalElement($child);
                self::sanitizeLegalDomNode($child, $dom);
                continue;
            }

            if ($child instanceof DOMComment) {
                $node->removeChild($child);
            }
        }
    }

    private static function sanitizeLegalElement(DOMElement $element): void {
        $tagName = strtolower($element->tagName);

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $keepAttribute = $tagName === 'a' && in_array($name, ['href', 'title'], true);
            if (!$keepAttribute) {
                $element->removeAttributeNode($attribute);
            }
        }

        if ($tagName !== 'a') {
            return;
        }

        $href = self::sanitizeLegalLink($element->getAttribute('href'));
        if ($href === '') {
            $element->removeAttribute('href');
            return;
        }

        $element->setAttribute('href', $href);

        if (preg_match('#^https?://#i', $href)) {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function unwrapDomNode(DOMNode $node): void {
        $parent = $node->parentNode;
        if (!$parent) {
            return;
        }

        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }
    
    /**
     * Prüft Schreibrechte für alle kritischen Verzeichnisse
     */
    public static function checkMediaDirectoryPermissions(): array {
        $issues = [];
        $recommendations = [];
        
        $dirs = [
            __DIR__ . '/../media/images',
            __DIR__ . '/../media/downloads',
            __DIR__ . '/../media/header',
            __DIR__ . '/../data',
            __DIR__ . '/../logs',
            __DIR__ . '/../archive'
        ];
        
        foreach ($dirs as $dir) {
            $isDir = is_dir($dir);
            $isReadable = is_readable($dir);
            $isWritable = is_writable($dir);
            
            if (!$isDir || !$isReadable || !$isWritable) {
                $issues[] = [
                    'dir' => str_replace(__DIR__ . '/../', '', $dir),
                    'exists' => $isDir,
                    'readable' => $isReadable,
                    'writable' => $isWritable
                ];
            }
        }
        
        if (!empty($issues)) {
            $recommendations[] = "Schreibrechte setzen: chmod 777 backend/media/images backend/media/downloads backend/media/header backend/data backend/logs backend/archive";
            $recommendations[] = "Oder mit Webserver-Eigentümer (z.B. www-data): chown -R www-data:www-data backend/ && chmod 755 backend/media backend/data backend/logs";
        }
        
        return [
            'writable' => empty($issues),
            'issues' => $issues,
            'recommendations' => $recommendations
        ];
    }
}
