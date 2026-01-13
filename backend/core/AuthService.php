<?php
/**
 * AuthService - Email-Code-Authentifizierung
 * 
 * Flow:
 * 1. User gibt Email ein
 * 2. 6-stelliger Code wird generiert und per mail() versendet
 * 3. User gibt Code ein
 * 4. Session wird aktiviert (konfigurierbare Dauer)
 * 
 * HINWEIS: config.php muss VOR diesem Service geladen werden!
 * (Passiert automatisch durch login.php, editor.php, endpoints.php)
 * Fallback-Werte im Konstruktor für Robustheit.
 */

require_once __DIR__ . '/LogService.php';
require_once __DIR__ . '/StorageService.php';
require_once __DIR__ . '/SecurityHelper.php';

class AuthService {
    
    // Werte aus config.php (mit Fallbacks)
    private int $codeExpiry;
    private int $sessionExpiry;
    private int $maxAttempts;
    private int $lockoutTime;
    private int $warningBefore;
    
    private StorageService $settingsStorage;
    
    public function __construct() {
        // Werte aus config.php laden (mit Fallbacks)
        $this->codeExpiry = defined('LOGIN_CODE_EXPIRY') ? constant('LOGIN_CODE_EXPIRY') : 900;
        $this->sessionExpiry = defined('SESSION_TIMEOUT') ? constant('SESSION_TIMEOUT') : 3600;
        $this->maxAttempts = defined('MAX_LOGIN_ATTEMPTS') ? constant('MAX_LOGIN_ATTEMPTS') : 3;
        $this->lockoutTime = defined('LOGIN_LOCKOUT_DURATION') ? constant('LOGIN_LOCKOUT_DURATION') : 600;
        $this->warningBefore = defined('SESSION_WARNING_BEFORE') ? constant('SESSION_WARNING_BEFORE') : 300;
        
        // Debug: Log die geladenen Werte
        if (defined('DEBUG_MODE') && constant('DEBUG_MODE')) {
            LogService::debug('AuthService', 'Config loaded', [
                'sessionExpiry' => $this->sessionExpiry,
                'codeExpiry' => $this->codeExpiry,
                'maxAttempts' => $this->maxAttempts,
                'lockoutTime' => $this->lockoutTime,
                'warningBefore' => $this->warningBefore,
                'SESSION_TIMEOUT_defined' => defined('SESSION_TIMEOUT'),
                'SESSION_TIMEOUT_value' => defined('SESSION_TIMEOUT') ? constant('SESSION_TIMEOUT') : 'not defined'
            ]);
        }
        
        $this->settingsStorage = new StorageService('settings.json');
        $this->ensureSession();
    }
    
    /**
     * Sendet Login-Code an die hinterlegte Email
     * 
     * @param string $email Eingegebene Email-Adresse
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendCode(string $email): array {
        $settings = $this->settingsStorage->read();
        $adminEmail = $settings['auth']['email'] ?? null;
        
        if (!$adminEmail) {
            LogService::error('AuthService', 'No admin email configured');
            return ['success' => false, 'message' => 'System nicht konfiguriert'];
        }
        
        // Email-Vergleich (case-insensitive)
        if (strtolower(trim($email)) !== strtolower(trim($adminEmail))) {
            LogService::warning('AuthService', 'Invalid email attempt', ['email' => $email]);
            // Gleiche Antwort für Security (kein Hinweis ob Email existiert)
            return ['success' => true, 'message' => 'Falls die Email korrekt ist, wurde ein Code versendet'];
        }
        
        // Code generieren
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // In Session speichern
        $_SESSION['auth_code'] = $code;
        $_SESSION['auth_code_expires'] = time() + $this->codeExpiry;
        $_SESSION['auth_attempts'] = 0;
        $_SESSION['auth_email'] = $adminEmail;
        
        // Email versenden
        $siteName = $settings['site']['title'] ?? 'Info-Hub';
        $subject = "$siteName - Login-Code";
        
        // Email-Inhalt mit Sicherheitshinweisen
        $securityInfo = SecurityHelper::getEmailSecurityInfo();
        $codeExpiryMinutes = ceil($this->codeExpiry / 60);
        $message = "Dein Login-Code: $code\n\nGültig für {$codeExpiryMinutes} Minuten.\n\nFalls du diesen Code nicht angefordert hast, ignoriere diese Email.{$securityInfo}";
        
        $headers = "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        
        $mailSent = @mail($adminEmail, $subject, $message, $headers);
        
        // DEVELOPMENT MODE: Wenn mail() fehlschlägt, Code in Session für Debug anzeigen
        if (!$mailSent) {
            LogService::error('AuthService', 'Failed to send email', ['email' => $adminEmail]);
            
            // Für Development: Code trotzdem in Session speichern und per Message zurückgeben
            if (defined('DEBUG_MODE') && constant('DEBUG_MODE')) {
                $_SESSION['debug_code_display'] = $code;
                LogService::warning('AuthService', 'DEBUG: Code will be displayed in UI', ['code' => $code]);
                return ['success' => true, 'message' => 'Email-Versand fehlgeschlagen. DEBUG: Code = ' . $code];
            }
            
            return ['success' => false, 'message' => 'Email konnte nicht versendet werden'];
        }
        
        LogService::info('AuthService', 'Login code sent', ['email' => $adminEmail]);
        return ['success' => true, 'message' => 'Falls die Email korrekt ist, wurde ein Code versendet'];
    }
    
    /**
     * Verifiziert den eingegebenen Code
     * 
     * @param string $inputCode Vom User eingegebener Code
     * @return array ['success' => bool, 'message' => string]
     */
    public function verifyCode(string $inputCode): array {
        // Rate Limiting prüfen
        if ($this->isLockedOut()) {
            $remaining = $_SESSION['auth_lockout'] - time();
            LogService::warning('AuthService', 'Account locked out', ['remaining' => $remaining]);
            return [
                'success' => false, 
                'message' => "Zu viele Versuche. Bitte warte " . ceil($remaining / 60) . " Minuten."
            ];
        }
        
        // Code vorhanden?
        if (!isset($_SESSION['auth_code'])) {
            return ['success' => false, 'message' => 'Kein Code angefordert'];
        }
        
        // Code abgelaufen?
        if (time() > $_SESSION['auth_code_expires']) {
            unset($_SESSION['auth_code'], $_SESSION['auth_code_expires']);
            return ['success' => false, 'message' => 'Code ist abgelaufen'];
        }
        
        // Code vergleichen
        $inputCode = trim($inputCode);
        if ($inputCode !== $_SESSION['auth_code']) {
            $_SESSION['auth_attempts'] = ($_SESSION['auth_attempts'] ?? 0) + 1;
            
            if ($_SESSION['auth_attempts'] >= $this->maxAttempts) {
                $_SESSION['auth_lockout'] = time() + $this->lockoutTime;
                LogService::warning('AuthService', 'Account locked after max attempts');
            }
            
            $remaining = $this->maxAttempts - $_SESSION['auth_attempts'] + 1;
            return [
                'success' => false, 
                'message' => "Falscher Code. Noch $remaining Versuche."
            ];
        }
        
        // Erfolg!
        // Session-ID regenerieren (Security: verhindert Session-Fixation)
        session_regenerate_id(true);
        
        $_SESSION['authenticated'] = true;
        $_SESSION['auth_time'] = time();
        
        // CSRF-Token generieren
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        unset($_SESSION['auth_code'], $_SESSION['auth_code_expires'], $_SESSION['auth_attempts']);
        
        LogService::success('AuthService', 'User authenticated', [
            'email' => $_SESSION['auth_email'] ?? 'unknown'
        ]);
        
        return ['success' => true, 'message' => 'Erfolgreich eingeloggt'];
    }
    
    /**
     * Prüft ob User authentifiziert ist
     */
    public function isAuthenticated(): bool {
        if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
            return false;
        }
        
        // Session ablaufen lassen nach konfigurierter Zeit
        if (!isset($_SESSION['auth_time']) || time() - $_SESSION['auth_time'] > $this->sessionExpiry) {
            $this->logout();
            return false;
        }
        
        return true;
    }
    
    /**
     * Loggt User aus
     */
    public function logout(): void {
        LogService::info('AuthService', 'User logged out');
        session_destroy();
        session_start();
    }
    
    /**
     * Prüft ob Account gesperrt ist
     */
    private function isLockedOut(): bool {
        if (!isset($_SESSION['auth_lockout'])) {
            return false;
        }
        
        if (time() >= $_SESSION['auth_lockout']) {
            unset($_SESSION['auth_lockout']);
            $_SESSION['auth_attempts'] = 0;
            return false;
        }
        
        return true;
    }
    
    /**
     * Stellt sicher, dass Session gestartet ist
     */
    private function ensureSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Gibt verbleibende Session-Zeit zurück
     */
    public function getRemainingSessionTime(): int {
        if (!$this->isAuthenticated()) {
            return 0;
        }
        
        return $this->sessionExpiry - (time() - $_SESSION['auth_time']);
    }
    
    /**
     * Gibt Session-Timeout zurück (für JS)
     */
    public function getSessionTimeout(): int {
        return $this->sessionExpiry;
    }
    
    /**
     * Gibt Warnung-Vorlauf zurück (für JS)
     */
    public function getSessionWarningBefore(): int {
        return $this->warningBefore;
    }
}
