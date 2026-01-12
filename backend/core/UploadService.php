<?php
/**
 * UploadService - Sichere Datei-Uploads
 * 
 * Unterstützt:
 * - Bilder (jpg, jpeg, png, gif, webp) - max 5MB
 * - Downloads (pdf, docx, xlsx, zip) - max 50MB
 * - Header-Bilder
 */

require_once __DIR__ . '/LogService.php';

class UploadService {
    
    private const ALLOWED_IMAGES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const ALLOWED_DOWNLOADS = ['pdf', 'docx', 'xlsx', 'zip', 'pptx', 'txt'];
    private const MAX_IMAGE_SIZE = 5 * 1024 * 1024;      // 5MB
    private const MAX_DOWNLOAD_SIZE = 50 * 1024 * 1024;  // 50MB
    
    private string $mediaDir;
    
    public function __construct() {
        $this->mediaDir = __DIR__ . '/../media/';
        $this->ensureMediaDirs();
    }
    
    /**
     * Upload eines Bildes
     * 
     * @param array $file $_FILES Array-Element
     * @return array ['success' => bool, 'path' => string] oder ['error' => string]
     */
    public function uploadImage(array $file): array {
        return $this->upload($file, 'images', self::ALLOWED_IMAGES, self::MAX_IMAGE_SIZE);
    }
    
    /**
     * Upload eines Header-Bildes
     */
    public function uploadHeader(array $file): array {
        return $this->upload($file, 'header', self::ALLOWED_IMAGES, self::MAX_IMAGE_SIZE);
    }
    
    /**
     * Upload einer Download-Datei
     */
    public function uploadDownload(array $file): array {
        return $this->upload($file, 'downloads', self::ALLOWED_DOWNLOADS, self::MAX_DOWNLOAD_SIZE);
    }
    
    /**
     * Generische Upload-Methode
     */
    private function upload(array $file, string $type, array $allowedExts, int $maxSize): array {
        // 1. Upload-Error prüfen
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = $this->getUploadErrorMessage($file['error']);
            LogService::error('UploadService', 'Upload error', [
                'error_code' => $file['error'],
                'message' => $errorMsg
            ]);
            return ['success' => false, 'error' => $errorMsg];
        }
        
        // 2. Dateigröße prüfen
        if ($file['size'] > $maxSize) {
            $maxMB = round($maxSize / 1024 / 1024, 1);
            return ['success' => false, 'error' => "Datei zu groß (max. {$maxMB}MB)"];
        }
        
        // 3. Dateiendung prüfen
        $originalName = $file['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowedExts)) {
            LogService::warning('UploadService', 'Invalid file extension', [
                'extension' => $ext,
                'allowed' => $allowedExts
            ]);
            return ['success' => false, 'error' => 'Dateityp nicht erlaubt'];
        }
        
        // 4. Bei Bildern: MIME-Type prüfen
        if (in_array($ext, self::ALLOWED_IMAGES)) {
            $mimeType = mime_content_type($file['tmp_name']);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            
            if (!in_array($mimeType, $allowedMimes)) {
                LogService::warning('UploadService', 'Invalid MIME type', [
                    'mime' => $mimeType,
                    'file' => $originalName
                ]);
                return ['success' => false, 'error' => 'Ungültiger Dateityp'];
            }
        }
        
        // 5. Sicherer Dateiname generieren
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '', $basename);
        $basename = substr($basename, 0, 50) ?: 'file';  // Max 50 Zeichen
        $filename = $basename . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        
        // 6. Zielverzeichnis
        $targetDir = $this->mediaDir . $type . '/';
        $targetPath = $targetDir . $filename;
        
        // 7. Datei verschieben
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            LogService::error('UploadService', 'Failed to move uploaded file', [
                'target' => $targetPath
            ]);
            return ['success' => false, 'error' => 'Speichern fehlgeschlagen'];
        }
        
        LogService::info('UploadService', 'File uploaded successfully', [
            'filename' => $filename,
            'type' => $type,
            'size' => $file['size']
        ]);
        
        return [
            'success' => true,
            'filename' => $filename,
            'path' => "/backend/media/$type/$filename",
            'originalName' => $originalName,
            'size' => $file['size']
        ];
    }
    
    /**
     * Löscht eine Datei
     * 
     * @param string $path Relativer Pfad (z.B. /backend/media/images/file.jpg)
     * @return bool Erfolg
     */
    public function delete(string $path): bool {
        // Sicherheit: Nur Dateien im media-Ordner erlauben
        if (strpos($path, '/backend/media/') !== 0) {
            LogService::warning('UploadService', 'Invalid delete path', ['path' => $path]);
            return false;
        }
        
        // Relativen Pfad zu absolutem umwandeln
        $absolutePath = __DIR__ . '/..' . str_replace('/backend', '', $path);
        $absolutePath = realpath($absolutePath);
        
        // Sicherstellen, dass Pfad im Media-Ordner liegt
        if (!$absolutePath || strpos($absolutePath, realpath($this->mediaDir)) !== 0) {
            LogService::warning('UploadService', 'Path traversal attempt', ['path' => $path]);
            return false;
        }
        
        if (file_exists($absolutePath) && unlink($absolutePath)) {
            LogService::info('UploadService', 'File deleted', ['path' => $path]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Listet Dateien eines Typs auf
     */
    public function listFiles(string $type): array {
        $dir = $this->mediaDir . $type . '/';
        
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
        
        // Nach Datum sortieren (neueste zuerst)
        usort($files, fn($a, $b) => $b['modified'] <=> $a['modified']);
        
        return $files;
    }
    
    /**
     * Stellt sicher, dass Media-Verzeichnisse existieren
     */
    private function ensureMediaDirs(): void {
        $dirs = ['images', 'downloads', 'header'];
        
        foreach ($dirs as $dir) {
            $path = $this->mediaDir . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
    
    /**
     * Übersetzt Upload-Fehlercodes
     */
    private function getUploadErrorMessage(int $errorCode): string {
        return match($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'Datei überschreitet Server-Limit',
            UPLOAD_ERR_FORM_SIZE => 'Datei zu groß',
            UPLOAD_ERR_PARTIAL => 'Upload unvollständig',
            UPLOAD_ERR_NO_FILE => 'Keine Datei ausgewählt',
            UPLOAD_ERR_NO_TMP_DIR => 'Server-Fehler (temp)',
            UPLOAD_ERR_CANT_WRITE => 'Server-Fehler (write)',
            UPLOAD_ERR_EXTENSION => 'Upload blockiert',
            default => 'Unbekannter Fehler'
        };
    }
}
