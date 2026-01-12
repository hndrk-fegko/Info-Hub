<?php
/**
 * SecurityHelper - Zentrale Sicherheitsprüfungen
 * 
 * Prüft Debug-Modi, HTTPS-Status und andere Sicherheitsaspekte.
 * Wird von Login, Editor und Email verwendet.
 */

class SecurityHelper {
    
    /**
     * Prüft ob irgendein Debug-Modus aktiv ist
     * 
     * @return bool True wenn Debug aktiv
     */
    public static function isDebugMode(): bool {
        return defined('DEBUG_MODE') && constant('DEBUG_MODE');
    }
    
    /**
     * Prüft ob HTTPS verwendet wird
     * 
     * @return bool True wenn HTTPS aktiv
     */
    public static function isHttps(): bool {
        // Standard HTTPS-Check
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        
        // Proxy/Load-Balancer Header
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }
        
        // CloudFlare
        if (!empty($_SERVER['HTTP_CF_VISITOR'])) {
            $visitor = json_decode($_SERVER['HTTP_CF_VISITOR'], true);
            if (isset($visitor['scheme']) && $visitor['scheme'] === 'https') {
                return true;
            }
        }
        
        // Port 443
        if (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Prüft ob localhost (Development)
     * 
     * @return bool True wenn localhost
     */
    public static function isLocalhost(): bool {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $localHosts = ['localhost', '127.0.0.1', '::1'];
        
        foreach ($localHosts as $local) {
            if (strpos($host, $local) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Gibt alle aktiven Sicherheitswarnungen zurück
     * 
     * @return array ['warnings' => [...], 'isProduction' => bool]
     */
    public static function getSecurityStatus(): array {
        $warnings = [];
        $isProduction = !self::isLocalhost();
        
        // Debug-Modus
        if (self::isDebugMode()) {
            $warnings[] = [
                'type' => 'debug',
                'icon' => '🐛',
                'title' => 'Debug-Modus aktiv',
                'message' => 'Debug-Modus sollte in Produktion deaktiviert werden.',
                'severity' => $isProduction ? 'error' : 'warning'
            ];
        }
        
        // HTTPS (nur warnen wenn nicht localhost)
        if (!self::isHttps() && $isProduction) {
            $warnings[] = [
                'type' => 'https',
                'icon' => '🔓',
                'title' => 'Kein HTTPS',
                'message' => 'Die Verbindung ist nicht verschlüsselt. Bitte HTTPS aktivieren.',
                'severity' => 'error'
            ];
        }
        
        // PHP Error Display (kritisch in Production)
        if (ini_get('display_errors') && $isProduction) {
            $warnings[] = [
                'type' => 'errors',
                'icon' => '⚠️',
                'title' => 'PHP-Fehler sichtbar',
                'message' => 'display_errors sollte in Produktion deaktiviert sein.',
                'severity' => 'warning'
            ];
        }
        
        return [
            'warnings' => $warnings,
            'hasWarnings' => count($warnings) > 0,
            'isProduction' => $isProduction,
            'isSecure' => count($warnings) === 0
        ];
    }
    
    /**
     * Generiert Security-Info für Login-Email
     * 
     * @return string Formatierter Text für Email
     */
    public static function getEmailSecurityInfo(): string {
        $lines = [];
        $lines[] = "\n---\nSicherheitshinweise:";
        
        // IP und Hostname
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unbekannt';
        $host = $_SERVER['HTTP_HOST'] ?? 'unbekannt';
        $lines[] = "• Angefragt von: $ip";
        $lines[] = "• Server: $host";
        
        // HTTPS-Status
        if (self::isHttps()) {
            $lines[] = "• Verbindung: ✓ Verschlüsselt (HTTPS)";
        } else {
            $lines[] = "• Verbindung: ⚠ Unverschlüsselt (HTTP)";
        }
        
        // Debug-Status
        if (self::isDebugMode()) {
            $lines[] = "• Debug-Modus: ⚠ AKTIV - In Produktion deaktivieren!";
        }
        
        // Localhost
        if (self::isLocalhost()) {
            $lines[] = "• Umgebung: Development (localhost)";
        } else {
            $lines[] = "• Umgebung: Production";
        }
        
        $lines[] = "\nZeit: " . date('d.m.Y H:i:s');
        
        return implode("\n", $lines);
    }
    
    /**
     * Generiert HTML für Security-Badge (Icon mit Tooltip)
     * 
     * @return string HTML oder leerer String wenn keine Warnungen
     */
    public static function renderSecurityBadge(): string {
        $status = self::getSecurityStatus();
        
        if (!$status['hasWarnings']) {
            return '';
        }
        
        $count = count($status['warnings']);
        $tooltip = [];
        $icons = [];
        
        foreach ($status['warnings'] as $warning) {
            $icons[] = $warning['icon'];
            $tooltip[] = $warning['title'] . ': ' . $warning['message'];
        }
        
        $iconStr = implode('', array_unique($icons));
        // &#10; für Zeilenumbruch im title-Attribut (HTML-konform)
        $tooltipStr = htmlspecialchars(implode("\n", $tooltip));
        $tooltipStr = str_replace("\n", "&#10;", $tooltipStr);
        
        return <<<HTML
<span class="security-badge security-warning" title="{$tooltipStr}">
    {$iconStr} <span class="badge-count">{$count}</span>
</span>
HTML;
    }
    
    /**
     * Generiert HTML für Security-Banner (dismissable)
     * 
     * @return string HTML oder leerer String wenn keine Warnungen
     */
    public static function renderSecurityBanner(): string {
        $status = self::getSecurityStatus();
        
        if (!$status['hasWarnings']) {
            return '';
        }
        
        $items = [];
        foreach ($status['warnings'] as $warning) {
            $severityClass = $warning['severity'] === 'error' ? 'banner-error' : 'banner-warning';
            $items[] = "<div class=\"banner-item {$severityClass}\">{$warning['icon']} <strong>{$warning['title']}:</strong> {$warning['message']}</div>";
        }
        
        $itemsHtml = implode("\n", $items);
        
        return <<<HTML
<div class="security-banner" id="securityBanner">
    <div class="banner-content">
        {$itemsHtml}
    </div>
    <button class="banner-dismiss" onclick="dismissSecurityBanner()" title="Schließen">×</button>
</div>
<script>
function dismissSecurityBanner() {
    document.getElementById('securityBanner').style.display = 'none';
    sessionStorage.setItem('securityBannerDismissed', '1');
}
// Auto-hide wenn bereits dismissed
if (sessionStorage.getItem('securityBannerDismissed')) {
    document.getElementById('securityBanner')?.style.setProperty('display', 'none');
}
</script>
HTML;
    }
}
