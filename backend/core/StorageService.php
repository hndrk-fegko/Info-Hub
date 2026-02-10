<?php
/**
 * StorageService - JSON File I/O
 * 
 * Einzige Stelle für Datei-Operationen auf JSON-Daten.
 * Alle anderen Services nutzen diese Klasse.
 */

require_once __DIR__ . '/LogService.php';

class StorageService {
    
    private string $filePath;
    private string $dataDir;
    
    /**
     * @param string $filename Name der JSON-Datei (z.B. 'tiles.json')
     */
    public function __construct(string $filename) {
        $this->dataDir = __DIR__ . '/../data/';
        $this->filePath = $this->dataDir . $filename;
        $this->ensureDataDir();
    }
    
    /**
     * Liest JSON-Daten aus Datei
     * 
     * @return array Daten oder leeres Array
     */
    public function read(): array {
        if (!file_exists($this->filePath)) {
            LogService::debug('StorageService', 'File not found, returning empty array', [
                'file' => $this->filePath
            ]);
            return [];
        }
        
        $content = file_get_contents($this->filePath);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            LogService::error('StorageService', 'JSON decode error', [
                'file' => $this->filePath,
                'error' => json_last_error_msg()
            ]);
            return [];
        }
        
        return $data;
    }
    
    /**
     * Schreibt Daten in JSON-Datei
     * 
     * @param array $data Zu speichernde Daten
     * @return bool Erfolg
     */
    public function write(array $data): bool {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            LogService::error('StorageService', 'JSON encode error', [
                'file' => $this->filePath,
                'error' => json_last_error_msg()
            ]);
            return false;
        }
        
        // Atomares Schreiben: temp-Datei + rename verhindert Datenverlust bei Crash
        $tempFile = $this->filePath . '.tmp.' . getmypid();
        $result = file_put_contents($tempFile, $json, LOCK_EX);
        
        if ($result === false) {
            LogService::error('StorageService', 'Failed to write temp file', [
                'file' => $tempFile
            ]);
            @unlink($tempFile);
            return false;
        }
        
        if (!rename($tempFile, $this->filePath)) {
            LogService::error('StorageService', 'Failed to rename temp file', [
                'temp' => $tempFile,
                'target' => $this->filePath
            ]);
            @unlink($tempFile);
            return false;
        }
        
        LogService::debug('StorageService', 'File written successfully', [
            'file' => $this->filePath,
            'bytes' => $result
        ]);
        
        return true;
    }
    
    /**
     * Prüft ob Datei existiert
     */
    public function exists(): bool {
        return file_exists($this->filePath);
    }
    
    /**
     * Erstellt Backup der Datei
     * 
     * @return string|false Backup-Pfad oder false bei Fehler
     */
    public function backup(): string|false {
        if (!file_exists($this->filePath)) {
            return false;
        }
        
        $archiveDir = __DIR__ . '/../archive/';
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0755, true);
        }
        
        $filename = pathinfo($this->filePath, PATHINFO_FILENAME);
        $backupPath = $archiveDir . $filename . '_' . date('Y-m-d_H-i-s') . '.json';
        
        if (copy($this->filePath, $backupPath)) {
            LogService::info('StorageService', 'Backup created', [
                'source' => $this->filePath,
                'backup' => $backupPath
            ]);
            return $backupPath;
        }
        
        return false;
    }
    
    /**
     * Stellt sicher, dass das Data-Verzeichnis existiert
     */
    private function ensureDataDir(): void {
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }
    
    /**
     * Gibt den Dateipfad zurück
     */
    public function getFilePath(): string {
        return $this->filePath;
    }
}
