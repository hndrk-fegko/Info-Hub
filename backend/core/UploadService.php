<?php
/**
 * UploadService - Sichere Datei-Uploads
 * 
 * Validiert und speichert hochgeladene Dateien.
 * 
 * HINWEIS: config.php muss VOR diesem Service geladen werden.
 * Passiert zentral ueber backend/bootstrap.php; Fallback-Werte im Konstruktor bleiben fuer Robustheit bestehen.
 */

require_once __DIR__ . '/LogService.php';

class UploadService {
    
    private const ALLOWED_IMAGES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const ALLOWED_DOWNLOADS = ['pdf', 'docx', 'xlsx', 'zip', 'doc', 'xls', 'pptx', 'ppt', 'txt'];
    private const ALLOWED_MEDIA_TYPES = ['images', 'downloads', 'header', 'backgrounds'];
    private const HEADER_MAX_DIMENSION = 1920;
    private const HEADER_PLACEHOLDER_WIDTH = 32;
    
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
    public function uploadHeader(array $file, ?string $clientPlaceholder = null, $clientWidth = null, $clientHeight = null): array {
        $result = $this->upload($file, 'header', self::ALLOWED_IMAGES, $this->maxImageSize);
        if (!$result['success']) {
            return $result;
        }

        $absolutePath = self::MEDIA_PATH . 'header/' . $result['filename'];
        $derivatives = $this->createHeaderDerivatives($absolutePath);

        if (!empty($derivatives)) {
            $result = array_merge($result, $derivatives);
        }

        return $this->mergeHeaderMetadata($result, $clientPlaceholder, $clientWidth, $clientHeight);
    }

    /**
     * Lädt ein Hintergrund-Bild hoch
     */
    public function uploadBackground(array $file): array {
        return $this->upload($file, 'backgrounds', self::ALLOWED_IMAGES, $this->maxImageSize);
    }

    /**
     * Erstellt optimierte Header-Derivate (optimiertes Hauptbild + Blur-Placeholder).
     *
     * @return array{placeholder?: string|null, width?: int, height?: int}
     */
    private function createHeaderDerivatives(string $absolutePath): array {
        $imageInfo = @getimagesize($absolutePath);
        if ($imageInfo === false) {
            LogService::warning('UploadService', 'Could not read header image info', ['path' => $absolutePath]);
            return [];
        }

        $sourceWidth = (int)($imageInfo[0] ?? 0);
        $sourceHeight = (int)($imageInfo[1] ?? 0);
        $mime = $imageInfo['mime'] ?? '';

        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            return [];
        }

        $result = [
            'width' => $sourceWidth,
            'height' => $sourceHeight,
            'placeholder' => null
        ];

        if (!extension_loaded('gd')) {
            LogService::warning('UploadService', 'GD extension not available, skipping header derivatives', ['path' => $absolutePath]);
            return $result;
        }

        $sourceImage = $this->createImageResource($absolutePath, $mime);
        if (!$sourceImage) {
            LogService::warning('UploadService', 'Unsupported header image format for derivatives', [
                'path' => $absolutePath,
                'mime' => $mime
            ]);
            return $result;
        }

        $optimizedImage = $sourceImage;
        $optimizedWidth = $sourceWidth;
        $optimizedHeight = $sourceHeight;
        $placeholderImage = null;

        try {
            $longestEdge = max($sourceWidth, $sourceHeight);
            if ($longestEdge > self::HEADER_MAX_DIMENSION) {
                $scale = self::HEADER_MAX_DIMENSION / $longestEdge;
                $optimizedWidth = max(1, (int)round($sourceWidth * $scale));
                $optimizedHeight = max(1, (int)round($sourceHeight * $scale));
                $optimizedImage = $this->resampleImage($sourceImage, $optimizedWidth, $optimizedHeight, $mime);
            }

            if ($mime !== 'image/gif') {
                $this->saveImageResource($optimizedImage, $absolutePath, $mime);
            }

            $result['width'] = $optimizedWidth;
            $result['height'] = $optimizedHeight;

            $placeholderWidth = min(self::HEADER_PLACEHOLDER_WIDTH, $optimizedWidth);
            $placeholderHeight = max(1, (int)round($optimizedHeight * ($placeholderWidth / $optimizedWidth)));
            $placeholderImage = $this->resampleImage($optimizedImage, $placeholderWidth, $placeholderHeight, $mime);
            $result['placeholder'] = $this->encodePlaceholderDataUri($placeholderImage);
        } catch (Throwable $e) {
            LogService::warning('UploadService', 'Failed to generate header derivatives', [
                'path' => $absolutePath,
                'error' => $e->getMessage()
            ]);
        } finally {
            $this->destroyImageResource($placeholderImage, $optimizedImage);
            $this->destroyImageResource($optimizedImage, $sourceImage);
            $this->destroyImageResource($sourceImage);
        }

        return $result;
    }

    private function mergeHeaderMetadata(array $result, ?string $clientPlaceholder, $clientWidth, $clientHeight): array {
        $normalizedPlaceholder = $this->normalizeHeaderPlaceholder($clientPlaceholder);
        $normalizedWidth = $this->normalizePositiveInt($clientWidth);
        $normalizedHeight = $this->normalizePositiveInt($clientHeight);

        if (empty($result['placeholder']) && $normalizedPlaceholder !== null) {
            $result['placeholder'] = $normalizedPlaceholder;
        }

        if (empty($result['width']) && $normalizedWidth !== null) {
            $result['width'] = $normalizedWidth;
        }

        if (empty($result['height']) && $normalizedHeight !== null) {
            $result['height'] = $normalizedHeight;
        }

        return $result;
    }

    private function normalizeHeaderPlaceholder(?string $value): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (!preg_match('#^data:image/(?:webp|jpeg);base64,[A-Za-z0-9+/=]+$#', $value) || strlen($value) > 100000) {
            return null;
        }

        return $value;
    }

    private function normalizePositiveInt($value): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int) $value;
        return $intValue > 0 ? $intValue : null;
    }

    /**
     * @return resource|GdImage|null
     */
    private function createImageResource(string $path, string $mime) {
        switch ($mime) {
            case 'image/jpeg':
                return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : null;
            case 'image/png':
                return function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : null;
            case 'image/gif':
                return function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : null;
            case 'image/webp':
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
            default:
                return null;
        }
    }

    /**
     * @param resource|GdImage $sourceImage
     * @return resource|GdImage
     */
    private function resampleImage($sourceImage, int $targetWidth, int $targetHeight, string $mime) {
        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

        if (in_array($mime, ['image/png', 'image/webp', 'image/gif'], true)) {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
            imagefill($targetImage, 0, 0, $transparent);
        }

        imagecopyresampled(
            $targetImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            imagesx($sourceImage),
            imagesy($sourceImage)
        );

        return $targetImage;
    }

    /**
     * @param resource|GdImage $image
     */
    private function saveImageResource($image, string $path, string $mime): void {
        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($image, $path, 82);
                break;
            case 'image/png':
                imagepng($image, $path, 6);
                break;
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    imagewebp($image, $path, 82);
                } else {
                    imagepng($image, $path, 6);
                }
                break;
            default:
                break;
        }
    }

    /**
     * @param resource|GdImage $image
     */
    private function encodePlaceholderDataUri($image): ?string {
        ob_start();

        if (function_exists('imagewebp')) {
            imagewebp($image, null, 35);
            $data = ob_get_clean();
            return $data === false ? null : 'data:image/webp;base64,' . base64_encode($data);
        }

        if (function_exists('imagejpeg')) {
            imagejpeg($image, null, 35);
            $data = ob_get_clean();
            return $data === false ? null : 'data:image/jpeg;base64,' . base64_encode($data);
        }

        ob_end_clean();
        return null;
    }

    /**
     * @param resource|GdImage|null $image
     * @param resource|GdImage|null $except
     */
    private function destroyImageResource($image, $except = null): void {
        if ($image === null || $image === $except) {
            return;
        }

        if (is_resource($image) || is_object($image)) {
            imagedestroy($image);
        }
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
        if ($type === 'images' || $type === 'header' || $type === 'backgrounds') {
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
            if (!mkdir($targetDir, 0777, true)) {
                LogService::error('UploadService', 'Failed to create directory', ['dir' => $targetDir]);
                return [
                    'success' => false, 
                    'error' => 'Upload-Verzeichnis konnte nicht erstellt werden',
                    'details' => [
                        'dir' => $targetDir,
                        'suggestion' => 'SSH-Terminal: mkdir -p backend/media/' . $type . ' && chmod 777 backend/media/' . $type
                    ]
                ];
            }
        }
        
        // Prüfe Schreibrechte
        if (!is_writable($targetDir)) {
            LogService::error('UploadService', 'Directory not writable', [
                'dir' => $targetDir,
                'perms' => substr(sprintf('%o', fileperms($targetDir)), -4)
            ]);
            return [
                'success' => false, 
                'error' => 'Keine Schreibrechte im Upload-Verzeichnis. Server-Admin muss Berechtigungen prüfen.',
                'details' => [
                    'dir' => $targetDir,
                    'suggestion' => 'SSH-Terminal: chmod 777 ' . str_replace(__DIR__ . '/../', 'backend/', $targetDir)
                ]
            ];
        }
        
        $targetPath = $targetDir . $filename;
        
        // 7. Datei verschieben
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            LogService::error('UploadService', 'Failed to move file', ['target' => $targetPath]);
            return [
                'success' => false, 
                'error' => 'Datei konnte nicht gespeichert werden. Prüfe Server-Berechtigungen.',
                'details' => [
                    'path' => $targetPath,
                    'suggestion' => 'SSH-Terminal: chmod 777 ' . str_replace(__DIR__ . '/../', 'backend/', $targetDir)
                ]
            ];
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
        $type = $this->normalizeMediaType($type);
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
    public function deleteFile(string $type, string $filename, ?string $path = null): bool {
        $target = $this->resolveDeleteTarget($type, $filename, $path);
        $type = $target['type'];
        $filename = $target['filename'];

        $filepath = self::MEDIA_PATH . $type . '/' . $filename;
        
        if (file_exists($filepath) && is_file($filepath)) {
            unlink($filepath);
            LogService::info('UploadService', 'File deleted', ['file' => $filename]);
            return true;
        }
        
        return false;
    }

            /**
             * Loest Delete-Request-Parameter in einen validierten Typ und Dateinamen auf.
             *
             * @return array{type: string, filename: string}
             */
            private function resolveDeleteTarget(?string $type, ?string $filename, ?string $path = null): array {
                $resolvedType = is_string($type) ? trim($type) : '';
                $resolvedFilename = is_string($filename) ? trim($filename) : '';

                if ($resolvedType === '' && is_string($path) && $path !== '') {
                    if (preg_match('#/backend/media/(images|downloads|header|backgrounds)/(.+)$#', $path, $matches)) {
                        $resolvedType = $matches[1];
                        $resolvedFilename = $matches[2];
                    }
                }

                if ($resolvedType === '' || $resolvedFilename === '') {
                    throw new InvalidArgumentException('Typ und Dateiname erforderlich');
                }

                return [
                    'type' => $this->normalizeMediaType($resolvedType),
                    'filename' => basename($resolvedFilename),
                ];
            }

            private function normalizeMediaType(string $type): string {
                if (!in_array($type, self::ALLOWED_MEDIA_TYPES, true)) {
                    throw new InvalidArgumentException('Ungültiger Dateityp');
                }

                return $type;
            }
}
