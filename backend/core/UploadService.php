<?php
/**
 * UploadService - Sichere Datei-Uploads
 * 
 * Validiert und speichert hochgeladene Dateien.
 * 
 * HINWEIS: config.php muss VOR diesem Service geladen werden!
 * Fallback-Werte im Konstruktor für Robustheit.
 */

require_once __DIR__ . '/LogService.php';

class UploadService {
    
    private const ALLOWED_IMAGES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const ALLOWED_DOWNLOADS = ['pdf', 'docx', 'xlsx', 'zip', 'doc', 'xls', 'pptx', 'ppt', 'txt'];
    
    // Größenlimits aus config.php oder Fallbacks
    private int $maxImageSize;
    private int $maxDownloadSize;
    
    private const MEDIA_PATH = __DIR__ . '/../media/';
    
    public function __construct() {
        // Werte aus config.php laden (mit Fallbacks)
        $this->maxImageSize = defined('MAX_IMAGE_SIZE') ? constant('MAX_IMAGE_SIZE') : 5 * 1024 * 1024;
        $this->maxDownloadSize = defined('MAX_DOWNLOAD_SIZE') ? constant('MAX_DOWNLOAD_SIZE') : 50 * 1024 * 1024;
        
        // Debug: Log die geladenen Werte
        if (defined('DEBUG_MODE') && constant('DEBUG_MODE')) {
            LogService::debug('UploadService', 'Config loaded', [
                'maxImageSize' => $this->maxImageSize,
                'maxDownloadSize' => $this->maxDownloadSize
            ]);
        }
    }
    
    /**
     * Lädt ein Bild hoch
     */
    public function uploadImage(array $file): array {
        return $this->upload($file, 'images', self::ALLOWED_IMAGES, $this->maxImageSize);
    }
    
    /**
     * Lädt eine Download-Datei hoch
     */
    public function uploadDownload(array $file): array {
        return $this->upload($file, 'downloads', self::ALLOWED_DOWNLOADS, $this->maxDownloadSize);
    }
    
    /**
     * Lädt ein Header-Bild hoch
     */
    public function uploadHeader(array $file): array {
        return $this->upload($file, 'header', self::ALLOWED_IMAGES, $this->maxImageSize);
    }
    
    /**
     * Generische Upload-Methode
     */
    private function upload(array $file, string $type, array $allowedExts, int $maxSize): array {
        // 1. Validierung: Upload-Error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            LogService::warning('UploadService', 'Upload error', ['error' => $file['error']]);
            return ['success' => false, 'error' => 'Upload fehlgeschlagen'];
        }
        
        // 2. Validierung: Dateigröße
        if ($file['size'] > $maxSize) {
            LogService::warning('UploadService', 'File too large', [
                'size' => $file['size'],
                'max' => $maxSize
            ]);
            return ['success' => false, 'error' => 'Datei zu groß (max. ' . ($maxSize / 1024 / 1024) . 'MB)'];
        }
        
        // 3. Validierung: Extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            LogService::warning('UploadService', 'Invalid extension', ['ext' => $ext]);
            return ['success' => false, 'error' => 'Dateityp nicht erlaubt'];
        }
        
        // 4. Validierung: MIME-Type (für Bilder)
        if ($type === 'images' || $type === 'header') {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mime, $allowedMimes)) {
                LogService::warning('UploadService', 'Invalid MIME type', ['mime' => $mime]);
                return ['success' => false, 'error' => 'Ungültiger Bildtyp'];
            }
        }
        
        // 5. Sicherer Dateiname
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($file['name'], PATHINFO_FILENAME));
        if (empty($basename)) {
            $basename = 'file';
        }
        $filename = $basename . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        
        // 6. Zielverzeichnis
        $targetDir = self::MEDIA_PATH . $type . '/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $targetPath = $targetDir . $filename;
        
        // 7. Datei verschieben
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            LogService::error('UploadService', 'Failed to move file', ['target' => $targetPath]);
            return ['success' => false, 'error' => 'Speichern fehlgeschlagen'];
        }
        
        LogService::success('UploadService', 'File uploaded', [
            'filename' => $filename,
            'type' => $type,
            'size' => $file['size']
        ]);
        
        return [
            'success' => true,
            'filename' => $filename,
            'path' => "/backend/media/$type/$filename"
        ];
    }
    
    /**
     * Listet Dateien eines Typs
     */
    public function listFiles(string $type): array {
        $dir = self::MEDIA_PATH . $type . '/';
        if (!is_dir($dir)) {
            return [];
        }
        
        $files = [];
        foreach (glob($dir . '*') as $file) {
            if (is_file($file)) {
                $files[] = [
                    'filename' => basename($file),
                    'path' => "/backend/media/$type/" . basename($file),
                    'size' => filesize($file),
                    'modified' => filemtime($file)
                ];
            }
        }
        
        // Neueste zuerst
        usort($files, fn($a, $b) => $b['modified'] <=> $a['modified']);
        
        return $files;
    }
    
    /**
     * Löscht eine Datei
     */
    public function deleteFile(string $type, string $filename): bool {
        // Sicherheit: Nur Dateiname, kein Pfad
        $filename = basename($filename);
        $filepath = self::MEDIA_PATH . $type . '/' . $filename;
        
        if (file_exists($filepath) && is_file($filepath)) {
            unlink($filepath);
            LogService::info('UploadService', 'File deleted', ['file' => $filename]);
            return true;
        }
        
        return false;
    }
}
