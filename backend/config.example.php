<?php
/**
 * ZENTRALE KONFIGURATION - Info-Hub
 * 
 * Diese Datei wird von allen Backend-Dateien geladen.
 * Änderungen hier wirken sich systemweit aus.
 * 
 * INSTALLATION:
 * 1. Diese Datei kopieren zu: config.php
 * 2. DEBUG_MODE auf false setzen für Produktion
 */

// ============================================
// DEBUG-MODUS
// ============================================
// true  = Login-Code wird angezeigt (ohne Email), Fehler sichtbar
// false = Produktions-Modus, keine Debug-Ausgaben
define('DEBUG_MODE', false);

// ============================================
// ERROR REPORTING (abhängig von DEBUG_MODE)
// ============================================
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// ============================================
// WEITERE EINSTELLUNGEN
// ============================================

// Session-Timeout in Sekunden (Standard: 1 Stunde)
define('SESSION_TIMEOUT', 3600);

// Session-Warnung vor Ablauf in Sekunden (Standard: 5 Minuten)
define('SESSION_WARNING_BEFORE', 300);

// Login-Code Gültigkeit in Sekunden (Standard: 15 Minuten)
define('LOGIN_CODE_EXPIRY', 900);

// Maximale Login-Versuche vor Sperre
define('MAX_LOGIN_ATTEMPTS', 3);

// Sperrdauer nach zu vielen Versuchen in Sekunden (Standard: 10 Minuten)
define('LOGIN_LOCKOUT_DURATION', 600);

// ============================================
// UPLOAD-LIMITS
// ============================================

// Maximale Bildgröße in Bytes (Standard: 5 MB)
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024);

// Maximale Download-Dateigröße in Bytes (Standard: 50 MB)
define('MAX_DOWNLOAD_SIZE', 50 * 1024 * 1024);

// Erlaubte Bild-Erweiterungen
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Erlaubte Download-Erweiterungen
define('ALLOWED_DOWNLOAD_EXTENSIONS', ['pdf', 'docx', 'xlsx', 'zip', 'pptx']);
