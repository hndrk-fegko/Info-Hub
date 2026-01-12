<?php
/**
 * LogService - Zentrales Logging System
 * 
 * Alle Services loggen über diese Klasse.
 * Unterstützt Log-Rotation bei 5MB.
 */

class LogService {
    
    private const LOG_DIR = __DIR__ . '/../logs/';
    private const LOG_FILE = __DIR__ . '/../logs/app.log';
    private const MAX_SIZE = 5 * 1024 * 1024; // 5MB
    
    /**
     * Hauptlog-Methode
     * 
     * @param string $level DEBUG, INFO, WARNING, ERROR, SUCCESS, FAILURE
     * @param string $module Name des Moduls (z.B. TileService, AuthService)
     * @param string $message Log-Nachricht
     * @param array $context Zusätzliche Daten
     */
    public static function log(
        string $level, 
        string $module, 
        string $message, 
        array $context = []
    ): void {
        self::ensureLogDir();
        
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        
        $entry = [
            'timestamp' => date('c'),
            'level'     => strtoupper($level),
            'module'    => $module,
            'message'   => $message,
            'context'   => $context,
            'file'      => $backtrace[1]['file'] ?? '',
            'line'      => $backtrace[1]['line'] ?? 0
        ];
        
        self::rotateIfNeeded();
        
        $logLine = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents(self::LOG_FILE, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Convenience Methods
     */
    public static function debug(string $module, string $msg, array $ctx = []): void {
        if (defined('DEBUG_MODE') && constant('DEBUG_MODE')) {
            self::log('DEBUG', $module, $msg, $ctx);
        }
    }
    
    public static function info(string $module, string $msg, array $ctx = []): void {
        self::log('INFO', $module, $msg, $ctx);
    }
    
    public static function warning(string $module, string $msg, array $ctx = []): void {
        self::log('WARNING', $module, $msg, $ctx);
    }
    
    public static function error(string $module, string $msg, array $ctx = []): void {
        self::log('ERROR', $module, $msg, $ctx);
    }
    
    public static function success(string $module, string $msg, array $ctx = []): void {
        self::log('SUCCESS', $module, $msg, $ctx);
    }
    
    /**
     * Stellt sicher, dass das Log-Verzeichnis existiert
     */
    private static function ensureLogDir(): void {
        if (!is_dir(self::LOG_DIR)) {
            mkdir(self::LOG_DIR, 0755, true);
        }
    }
    
    /**
     * Rotiert Log-Datei wenn > MAX_SIZE
     */
    private static function rotateIfNeeded(): void {
        if (file_exists(self::LOG_FILE) && filesize(self::LOG_FILE) > self::MAX_SIZE) {
            $rotatedName = self::LOG_FILE . '.' . date('Y-m-d_H-i-s');
            rename(self::LOG_FILE, $rotatedName);
        }
    }
    
    /**
     * Liest die letzten N Log-Einträge (für Admin-Panel)
     * 
     * @param int $lines Anzahl der Zeilen
     * @return array Log-Einträge
     */
    public static function getRecentLogs(int $lines = 50): array {
        if (!file_exists(self::LOG_FILE)) {
            return [];
        }
        
        $file = file(self::LOG_FILE);
        $recentLines = array_slice($file, -$lines);
        
        $logs = [];
        foreach ($recentLines as $line) {
            $decoded = json_decode($line, true);
            if ($decoded) {
                $logs[] = $decoded;
            }
        }
        
        return array_reverse($logs);
    }
}
