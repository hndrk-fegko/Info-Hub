<?php
/**
 * SecurityHelper - Zentrale Sicherheitsprüfungen
 * 
 * Prüft auf unsichere Konfigurationen und gibt Warnungen aus.
 */

// Config laden falls noch nicht geschehen
if (!defined('DEBUG_MODE') && file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}

class SecurityHelper {
    
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
}
