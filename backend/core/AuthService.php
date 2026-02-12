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
    private int $inviteExpiry;
    
    private StorageService $settingsStorage;
    
    public function __construct() {
        // Werte aus config.php laden (mit Fallbacks)
        $this->codeExpiry = defined('LOGIN_CODE_EXPIRY') ? constant('LOGIN_CODE_EXPIRY') : 900;
        $this->sessionExpiry = defined('SESSION_TIMEOUT') ? constant('SESSION_TIMEOUT') : 3600;
        $this->maxAttempts = defined('MAX_LOGIN_ATTEMPTS') ? constant('MAX_LOGIN_ATTEMPTS') : 3;
        $this->lockoutTime = defined('LOGIN_LOCKOUT_DURATION') ? constant('LOGIN_LOCKOUT_DURATION') : 600;
        $this->warningBefore = defined('SESSION_WARNING_BEFORE') ? constant('SESSION_WARNING_BEFORE') : 300;
        $this->inviteExpiry = defined('ADMIN_INVITE_EXPIRY') ? constant('ADMIN_INVITE_EXPIRY') : 3600;
        
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
     * Normalisiert eine Email-Adresse
     */
    private function normalizeEmail(string $email): string {
        return strtolower(trim($email));
    }

    /**
     * Lädt Settings und sorgt für kompatibles Auth-Format
     */
    private function getSettings(bool $saveIfChanged = true): array {
        $settings = $this->settingsStorage->read();
        $changed = false;

        if (!isset($settings['auth']) || !is_array($settings['auth'])) {
            $settings['auth'] = [];
            $changed = true;
        }

        if (!isset($settings['auth']['emails']) || !is_array($settings['auth']['emails'])) {
            if (!empty($settings['auth']['email'])) {
                $settings['auth']['emails'] = [$settings['auth']['email']];
            } else {
                $settings['auth']['emails'] = [];
            }
            $changed = true;
        }

        // Altes Feld aufräumen nach Migration (einmalig)
        if (array_key_exists('email', $settings['auth'])) {
            unset($settings['auth']['email']);
            $changed = true;
        }

        if (!isset($settings['auth']['invites']) || !is_array($settings['auth']['invites'])) {
            $settings['auth']['invites'] = [];
            $changed = true;
        }

        if ($this->cleanupExpiredInvites($settings)) {
            $changed = true;
        }

        if ($saveIfChanged && $changed) {
            $this->settingsStorage->write($settings);
        }

        return $settings;
    }

    /**
     * Entfernt abgelaufene Einladungen
     */
    private function cleanupExpiredInvites(array &$settings): bool {
        if (empty($settings['auth']['invites'])) {
            return false;
        }

        $now = time();
        // Abgelaufene Einladungen bleiben noch so lange wie ihre Gültigkeit war,
        // damit sendCode() eine hilfreiche "abgelaufen"-Meldung geben kann.
        // Danach werden sie beim nächsten Login (lazy) aufgeräumt.
        $gracePeriod = $this->inviteExpiry;
        $before = count($settings['auth']['invites']);

        $settings['auth']['invites'] = array_values(array_filter(
            $settings['auth']['invites'],
            fn($invite) => ($invite['expiresAt'] ?? 0) > ($now - $gracePeriod)
        ));

        return count($settings['auth']['invites']) !== $before;
    }

    /**
     * Findet eine gültige Einladung für eine Email
     */
    private function findInvite(array $settings, string $email): ?array {
        $email = $this->normalizeEmail($email);

        foreach ($settings['auth']['invites'] as $invite) {
            if ($this->normalizeEmail($invite['email'] ?? '') === $email) {
                return $invite;
            }
        }

        return null;
    }
    
    /**
     * Sendet Login-Code an die hinterlegte Email
     * 
     * @param string $email Eingegebene Email-Adresse
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendCode(string $email): array {
        $settings = $this->getSettings();
        $email = $this->normalizeEmail($email);
        $adminEmails = $settings['auth']['emails'] ?? [];
        
        if (empty($adminEmails)) {
            LogService::error('AuthService', 'No admin email configured');
            return ['success' => false, 'message' => 'System nicht konfiguriert'];
        }

        // Prüfen ob Email autorisiert ist (Admin oder gültige Einladung)
        $isAdmin = in_array($email, array_map([$this, 'normalizeEmail'], $adminEmails), true);
        
        // Auch abgelaufene Einladungen suchen (vor Cleanup!) um bessere Fehlermeldung zu geben
        $invite = $isAdmin ? null : $this->findInvite($settings, $email);

        if (!$isAdmin && $invite && ($invite['expiresAt'] ?? 0) <= time()) {
            LogService::info('AuthService', 'Invite expired on login attempt', [
                'email' => $email,
                'expiredAt' => date('c', $invite['expiresAt'] ?? 0)
            ]);
            return ['success' => false, 'message' => 'Einladung ist abgelaufen. Bitte fordere eine neue Einladung an.'];
        }

        if (!$isAdmin && !$invite) {
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
        $_SESSION['auth_email'] = $email;
        $_SESSION['auth_pending_email'] = $invite ? $email : null;
        
        // Email versenden
        $siteName = trim($settings['site']['title'] ?? '') ?: 'Info-Hub';
        $subject = "$siteName - Login-Code";
        
        // Email-Inhalt mit Sicherheitshinweisen
        $securityInfo = SecurityHelper::getEmailSecurityInfo();
        $codeExpiryMinutes = ceil($this->codeExpiry / 60);
        $message = "Dein Login-Code: $code\n\nGültig für {$codeExpiryMinutes} Minuten.\n\nFalls du diesen Code nicht angefordert hast, ignoriere diese Email.{$securityInfo}";
        
        $mailSent = $this->sendMail($email, $subject, $message);
        
        // DEVELOPMENT MODE: Wenn mail() fehlschlägt, Code in Session für Debug anzeigen
        if (!$mailSent) {
            LogService::error('AuthService', 'Failed to send email', [
                'email' => $email,
                'isAdmin' => $isAdmin,
                'isInvite' => ($invite !== null)
            ]);
            
            // Für Development: Code trotzdem in Session speichern und per Message zurückgeben
            if (defined('DEBUG_MODE') && constant('DEBUG_MODE')) {
                $_SESSION['debug_code_display'] = $code;
                LogService::warning('AuthService', 'DEBUG: Code will be displayed in UI', ['code' => $code]);
                return ['success' => true, 'message' => 'Email-Versand fehlgeschlagen. DEBUG: Code = ' . $code];
            }
            
            return ['success' => false, 'message' => 'Email konnte nicht versendet werden'];
        }
        
        LogService::info('AuthService', 'Login code sent', ['email' => $email]);
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

        // Einladung prüfen/aktivieren (falls vorhanden)
        $pendingEmail = $_SESSION['auth_pending_email'] ?? null;
        if (!empty($pendingEmail)) {
            $settings = $this->getSettings();
            $invite = $this->findInvite($settings, $pendingEmail);

            if (!$invite) {
                return ['success' => false, 'message' => 'Einladung ist nicht mehr gültig'];
            }

            // Einladung aktivieren
            $emails = $settings['auth']['emails'] ?? [];
            if (!in_array($pendingEmail, array_map([$this, 'normalizeEmail'], $emails), true)) {
                $settings['auth']['emails'][] = $pendingEmail;
            }

            // Einladung entfernen
            $settings['auth']['invites'] = array_values(array_filter(
                $settings['auth']['invites'],
                fn($i) => $this->normalizeEmail($i['email'] ?? '') !== $pendingEmail
            ));

            $this->settingsStorage->write($settings);
        }
        
        // Erfolg!
        // Session-ID regenerieren (Security: verhindert Session-Fixation)
        session_regenerate_id(true);
        
        $_SESSION['authenticated'] = true;
        $_SESSION['auth_time'] = time();
        
        // CSRF-Token generieren
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        unset($_SESSION['auth_code'], $_SESSION['auth_code_expires'], $_SESSION['auth_attempts'], $_SESSION['auth_pending_email']);
        
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
     * Versendet eine Email mit korrekten Headers
     * 
     * Zentrale Mail-Methode: Stellt sicher, dass From-Header,
     * Encoding und Envelope-Sender korrekt gesetzt sind.
     * Ohne das lehnen externe Mailserver die Mail ab.
     *
     * @param string $to Empfänger-Email
     * @param string $subject Betreff
     * @param string $message Nachrichtentext
     * @return bool true wenn mail() erfolgreich war
     */
    private function sendMail(string $to, string $subject, string $message): bool {
        $settings = $this->settingsStorage->read();
        $siteName = trim($settings['site']['title'] ?? '');
        
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Port entfernen (z.B. localhost:8000 → localhost)
        $host = preg_replace('/:\d+$/', '', $host);
        $fromEmail = 'noreply@' . $host;
        
        // From-Header: Mit oder ohne Display-Name
        if (!empty($siteName)) {
            // Display-Name RFC 2047 kodieren für Umlaute/Sonderzeichen
            $encodedName = '=?UTF-8?B?' . base64_encode($siteName) . '?=';
            $fromHeader = "{$encodedName} <{$fromEmail}>";
        } else {
            $fromHeader = $fromEmail;
        }
        
        // Subject RFC 2047 kodieren für Umlaute
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        
        $headers  = "From: {$fromHeader}\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        $headers .= "X-Mailer: Info-Hub/1.0";
        
        $envelopeSender = "-f{$fromEmail}";
        
        // DEBUG: Komplette Mail-Rohdaten loggen vor dem Versand
        if (defined('DEBUG_MODE') && constant('DEBUG_MODE')) {
            LogService::debug('AuthService', 'MAIL_DEBUG: Preparing to send', [
                'to' => $to,
                'subject_raw' => $subject,
                'subject_encoded' => $encodedSubject,
                'from_email' => $fromEmail,
                'from_header' => $fromHeader,
                'envelope_sender' => $envelopeSender,
                'host' => $host,
                'headers_raw' => str_replace("\r\n", ' | ', $headers),
                'message_length' => strlen($message),
                'message_preview' => mb_substr($message, 0, 200),
                'php_mail_path' => ini_get('sendmail_path') ?: '(not set)',
                'php_mail_from' => ini_get('sendmail_from') ?: '(not set)',
                'smtp_host' => ini_get('SMTP') ?: '(not set)',
                'smtp_port' => ini_get('smtp_port') ?: '(not set)'
            ]);
        }
        
        // PHP-Fehler abfangen für bessere Diagnose
        error_clear_last();
        $result = @mail($to, $encodedSubject, $message, $headers, $envelopeSender);
        $lastError = error_get_last();
        
        // DEBUG: Ergebnis loggen
        if (defined('DEBUG_MODE') && constant('DEBUG_MODE')) {
            LogService::debug('AuthService', 'MAIL_DEBUG: mail() returned', [
                'to' => $to,
                'result' => $result ? 'TRUE (accepted by local MTA)' : 'FALSE (rejected)',
                'php_error' => $lastError ? $lastError['message'] : null,
                'php_error_type' => $lastError ? $lastError['type'] : null
            ]);
        }
        
        return $result;
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

    /**
     * Gibt Admin-Emails zurück
     */
    public function getAdminEmails(): array {
        $settings = $this->getSettings();
        return $settings['auth']['emails'] ?? [];
    }

    /**
     * Gibt offene Einladungen zurück
     */
    public function getPendingInvites(): array {
        $settings = $this->getSettings();
        return $settings['auth']['invites'] ?? [];
    }

    /**
     * Erstellt eine neue Einladung
     */
    public function createInvite(string $email, string $createdBy = ''): array {
        $settings = $this->getSettings();
        $email = $this->normalizeEmail($email);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Ungültige Email-Adresse'];
        }

        $emails = $settings['auth']['emails'] ?? [];
        $emailsNormalized = array_map([$this, 'normalizeEmail'], $emails);

        if (in_array($email, $emailsNormalized, true)) {
            return ['success' => false, 'message' => 'Diese Email ist bereits Admin'];
        }

        foreach ($settings['auth']['invites'] as $invite) {
            if ($this->normalizeEmail($invite['email'] ?? '') === $email) {
                return ['success' => false, 'message' => 'Für diese Email existiert bereits eine Einladung'];
            }
        }

        $createdAt = time();
        $expiresAt = $createdAt + $this->inviteExpiry;

        $settings['auth']['invites'][] = [
            'email' => $email,
            'createdAt' => $createdAt,
            'expiresAt' => $expiresAt,
            'createdBy' => $createdBy
        ];

        $this->settingsStorage->write($settings);

        // Einladung per Email versenden (Link mit Prefill)
        $expiryMinutes = ceil($this->inviteExpiry / 60);
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        // Basispfad dynamisch ermitteln (funktioniert auch in Unterverzeichnissen)
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
        $backendPos = strpos($scriptPath, '/backend/');
        $basePath = ($backendPos !== false) ? substr($scriptPath, 0, $backendPos) : '';
        $inviteLink = $scheme . '://' . $host . $basePath . '/backend/login.php?email=' . urlencode($email);

        $message = "Du wurdest als Admin eingeladen.\n\n" .
                   "Bitte besuche innerhalb von {$expiryMinutes} Minuten folgenden Link und melde dich mit dieser Adresse an, um deinen Zugang zu aktivieren:\n" .
                   "$inviteLink\n\n" .
                   "Wenn du diese Einladung nicht erwartest, kannst du diese Email ignorieren.";

        $siteName = trim($settings['site']['title'] ?? '') ?: 'Info-Hub';
        $mailSent = $this->sendMail($email, "$siteName - Admin Einladung", $message);

        if (!$mailSent) {
            LogService::error('AuthService', 'Failed to send invite email', ['email' => $email]);
            if (defined('DEBUG_MODE') && constant('DEBUG_MODE')) {
                return ['success' => true, 'message' => 'Einladung erstellt. DEBUG: ' . $inviteLink];
            }
            return ['success' => false, 'message' => 'Einladung erstellt, aber Email-Versand fehlgeschlagen'];
        }

        LogService::info('AuthService', 'Invite created', ['email' => $email, 'createdBy' => $createdBy]);
        return ['success' => true, 'message' => 'Einladung wurde versendet'];
    }

    /**
     * Entfernt eine Admin-Email (letzte darf nicht gelöscht werden)
     */
    public function removeAdminEmail(string $email): array {
        $settings = $this->getSettings();
        $email = $this->normalizeEmail($email);
        $emails = $settings['auth']['emails'] ?? [];
        $emailsNormalized = array_map([$this, 'normalizeEmail'], $emails);

        if (!in_array($email, $emailsNormalized, true)) {
            return ['success' => false, 'message' => 'Email nicht gefunden'];
        }

        if (count($emails) <= 1) {
            return ['success' => false, 'message' => 'Die letzte Admin-Email kann nicht gelöscht werden'];
        }

        $settings['auth']['emails'] = array_values(array_filter(
            $emails,
            fn($e) => $this->normalizeEmail($e) !== $email
        ));

        $this->settingsStorage->write($settings);
        LogService::info('AuthService', 'Admin email removed', ['email' => $email]);

        return ['success' => true, 'message' => 'Admin entfernt'];
    }

    /**
     * Entfernt eine offene Einladung
     */
    public function removeInvite(string $email): array {
        $settings = $this->getSettings();
        $email = $this->normalizeEmail($email);

        $before = count($settings['auth']['invites'] ?? []);
        $settings['auth']['invites'] = array_values(array_filter(
            $settings['auth']['invites'] ?? [],
            fn($i) => $this->normalizeEmail($i['email'] ?? '') !== $email
        ));

        if (count($settings['auth']['invites']) === $before) {
            return ['success' => false, 'message' => 'Einladung nicht gefunden'];
        }

        $this->settingsStorage->write($settings);
        LogService::info('AuthService', 'Invite removed', ['email' => $email]);

        return ['success' => true, 'message' => 'Einladung entfernt'];
    }
}
