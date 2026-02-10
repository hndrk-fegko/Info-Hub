<?php
/**
 * API Endpoints - Zentraler Request Handler
 * 
 * Alle API-Anfragen werden hier verarbeitet.
 * Dünne Wrapper-Schicht - Business Logic in Services.
 * 
 * Actions:
 * - get_tiles: Alle Tiles laden
 * - get_tile: Einzelne Tile laden
 * - save_tile: Tile speichern (neu/update)
 * - delete_tile: Tile löschen
 * - update_positions: Positionen aktualisieren
 * - get_settings: Settings laden
 * - save_settings: Settings speichern
 * - upload_image: Bild hochladen
 * - upload_download: Download-Datei hochladen
 * - upload_header: Header-Bild hochladen
 * - delete_file: Datei löschen
 * - list_files: Dateien auflisten
 * - generate: HTML generieren
 * - preview: Preview HTML
 * - get_tile_types: Verfügbare Tile-Typen
 */

header('Content-Type: application/json; charset=utf-8');

// Zentrale Konfiguration laden
if (!file_exists(__DIR__ . '/../config.php')) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'System nicht konfiguriert. Bitte Setup ausführen.']);
    exit;
}
require_once __DIR__ . '/../config.php';

// Error Handling
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    // Session starten für CSRF und Auth
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Services laden
    require_once __DIR__ . '/../core/LogService.php';
    require_once __DIR__ . '/../core/AuthService.php';
    require_once __DIR__ . '/../core/TileService.php';
    require_once __DIR__ . '/../core/UploadService.php';
    require_once __DIR__ . '/../core/GeneratorService.php';
    require_once __DIR__ . '/../core/StorageService.php';
    
    // Auth prüfen (außer für bestimmte Actions)
    $auth = new AuthService();
    $publicActions = [];  // Alle Actions erfordern Authentifizierung
    $getActions = ['get_tiles', 'get_tile', 'get_settings', 'get_tile_types', 'list_files', 'preview'];  // GET erlaubt
    
    // Action ermitteln
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // Auth-Check (nur für nicht-öffentliche Actions)
    if (!in_array($action, $publicActions) && !$auth->isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Nicht authentifiziert']);
        exit;
    }
    
    // CSRF-Token Validierung für modifizierende Actions (POST, nicht GET-Actions)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, $getActions)) {
        $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            LogService::warning('API', 'CSRF token mismatch', ['action' => $action]);
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Ungültiges CSRF-Token']);
            exit;
        }
    }
    
    // Request verarbeiten
    switch ($action) {
        
        // ===== TILES =====
        
        case 'get_tiles':
            $tileService = new TileService();
            $tiles = $tileService->getTiles();
            echo json_encode(['success' => true, 'tiles' => $tiles]);
            break;
            
        case 'get_tile':
            $id = $_GET['id'] ?? $_POST['id'] ?? '';
            if (empty($id)) {
                throw new InvalidArgumentException('Tile-ID erforderlich');
            }
            
            $tileService = new TileService();
            $tile = $tileService->getTile($id);
            
            if ($tile === null) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Tile nicht gefunden']);
            } else {
                echo json_encode(['success' => true, 'tile' => $tile]);
            }
            break;
            
        case 'save_tile':
            $tileData = json_decode($_POST['tile'] ?? '{}', true);
            if (empty($tileData)) {
                $tileData = json_decode(file_get_contents('php://input'), true)['tile'] ?? [];
            }
            
            if (empty($tileData)) {
                throw new InvalidArgumentException('Tile-Daten erforderlich');
            }
            
            $tileService = new TileService();
            $result = $tileService->saveTile($tileData);
            
            if (!$result['success']) {
                http_response_code(400);
            } else {
                // Alle Tiles mit zurückgeben für Quick-Edit-Sync
                $result['tiles'] = $tileService->getTiles();
            }
            echo json_encode($result);
            break;
            
        case 'delete_tile':
            $id = $_POST['id'] ?? '';
            if (empty($id)) {
                throw new InvalidArgumentException('Tile-ID erforderlich');
            }
            
            $tileService = new TileService();
            $result = $tileService->deleteTile($id);
            
            if (!$result['success']) {
                http_response_code(404);
            }
            echo json_encode($result);
            break;
            
        case 'update_positions':
            $positions = json_decode($_POST['positions'] ?? '[]', true);
            if (empty($positions)) {
                $positions = json_decode(file_get_contents('php://input'), true)['positions'] ?? [];
            }
            
            $tileService = new TileService();
            $result = $tileService->updatePositions($positions);
            echo json_encode($result);
            break;
            
        case 'get_tile_types':
            $tileService = new TileService();
            $types = $tileService->getAvailableTypes();
            echo json_encode(['success' => true, 'types' => $types]);
            break;
        
        // ===== SETTINGS =====
        
        case 'get_settings':
            $storage = new StorageService('settings.json');
            $settings = $storage->read();
            
            // Email für Security maskieren (nur letzte 4 Zeichen zeigen)
            if (isset($settings['auth']['email'])) {
                $email = $settings['auth']['email'];
                $masked = '***' . substr($email, -10);
                $settings['auth']['emailMasked'] = $masked;
            }
            if (isset($settings['auth']['emails']) && is_array($settings['auth']['emails'])) {
                $settings['auth']['emailsMasked'] = array_map(function($email) {
                    return '***' . substr($email, -10);
                }, $settings['auth']['emails']);
            }
            
            // Sensible Auth-Daten entfernen - nur maskierte Version senden
            unset($settings['auth']['email'], $settings['auth']['emails'], $settings['auth']['invites']);
            
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;
            
        case 'save_settings':
            $newSettings = json_decode($_POST['settings'] ?? '{}', true);
            if (empty($newSettings)) {
                $newSettings = json_decode(file_get_contents('php://input'), true)['settings'] ?? [];
            }
            
            // Aktuelle Settings laden
            $storage = new StorageService('settings.json');
            $settings = $storage->read();
            
            // Nur erlaubte Felder aktualisieren (Email ist geschützt)
            if (isset($newSettings['site'])) {
                // Site-Felder: nur erlaubte Keys, Strings sanitizen
                $allowedSiteKeys = ['title', 'pageTitle', 'headerImage', 'headerFocusPoint', 'footerText'];
                foreach ($allowedSiteKeys as $key) {
                    if (isset($newSettings['site'][$key])) {
                        $settings['site'][$key] = $newSettings['site'][$key];
                    }
                }
            }
            if (isset($newSettings['theme'])) {
                // Theme-Farben: nur gültige Hex-Werte (#RRGGBB) erlauben
                $colorKeys = ['backgroundColor', 'accentColor', 'accentColor2', 'accentColor3'];
                foreach ($colorKeys as $key) {
                    if (isset($newSettings['theme'][$key])) {
                        $color = $newSettings['theme'][$key];
                        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                            $settings['theme'][$key] = $color;
                        }
                    }
                }
            }
            
            // Speichern
            if ($storage->write($settings)) {
                LogService::info('API', 'Settings saved');
                echo json_encode(['success' => true, 'settings' => $settings]);
            } else {
                throw new Exception('Speichern fehlgeschlagen');
            }
            break;

        case 'get_admins':
            $emails = $auth->getAdminEmails();
            $invites = $auth->getPendingInvites();
            echo json_encode(['success' => true, 'emails' => $emails, 'invites' => $invites]);
            break;

        case 'invite_admin':
            $email = $_POST['email'] ?? '';
            $createdBy = $_SESSION['auth_email'] ?? '';
            $result = $auth->createInvite($email, $createdBy);
            if (!$result['success']) {
                http_response_code(400);
            }
            $result['emails'] = $auth->getAdminEmails();
            $result['invites'] = $auth->getPendingInvites();
            echo json_encode($result);
            break;

        case 'remove_admin_email':
            $email = $_POST['email'] ?? '';
            $result = $auth->removeAdminEmail($email);
            if (!$result['success']) {
                http_response_code(400);
            }
            $result['emails'] = $auth->getAdminEmails();
            $result['invites'] = $auth->getPendingInvites();
            echo json_encode($result);
            break;

        case 'remove_admin_invite':
            $email = $_POST['email'] ?? '';
            $result = $auth->removeInvite($email);
            if (!$result['success']) {
                http_response_code(400);
            }
            $result['emails'] = $auth->getAdminEmails();
            $result['invites'] = $auth->getPendingInvites();
            echo json_encode($result);
            break;
        
        // ===== UPLOADS =====
        
        case 'upload_image':
            if (empty($_FILES['file'])) {
                throw new InvalidArgumentException('Keine Datei hochgeladen');
            }
            
            $uploadService = new UploadService();
            $result = $uploadService->uploadImage($_FILES['file']);
            
            if (!$result['success']) {
                http_response_code(400);
            }
            echo json_encode($result);
            break;
            
        case 'upload_download':
            if (empty($_FILES['file'])) {
                throw new InvalidArgumentException('Keine Datei hochgeladen');
            }
            
            $uploadService = new UploadService();
            $result = $uploadService->uploadDownload($_FILES['file']);
            
            if (!$result['success']) {
                http_response_code(400);
            }
            echo json_encode($result);
            break;
            
        case 'upload_header':
            if (empty($_FILES['file'])) {
                throw new InvalidArgumentException('Keine Datei hochgeladen');
            }
            
            $uploadService = new UploadService();
            $result = $uploadService->uploadHeader($_FILES['file']);
            
            if ($result['success']) {
                // Auch in Settings speichern
                $storage = new StorageService('settings.json');
                $settings = $storage->read();
                $settings['site']['headerImage'] = $result['path'];
                $storage->write($settings);
            } else {
                http_response_code(400);
            }
            echo json_encode($result);
            break;
            
        case 'delete_file':
            $type = $_POST['type'] ?? '';
            $filename = $_POST['filename'] ?? '';
            
            // Alternativ: Pfad parsen falls nur path übergeben wird
            if (empty($type) && !empty($_POST['path'])) {
                $path = $_POST['path'];
                // Pfad: /backend/media/images/filename.jpg
                if (preg_match('#/backend/media/(images|downloads|header)/(.+)$#', $path, $matches)) {
                    $type = $matches[1];
                    $filename = $matches[2];
                }
            }
            
            if (empty($type) || empty($filename)) {
                throw new InvalidArgumentException('Typ und Dateiname erforderlich');
            }
            
            if (!in_array($type, ['images', 'downloads', 'header'])) {
                throw new InvalidArgumentException('Ungültiger Dateityp');
            }
            
            $uploadService = new UploadService();
            $success = $uploadService->deleteFile($type, $filename);
            echo json_encode(['success' => $success]);
            break;
            
        case 'list_files':
            $type = $_GET['type'] ?? 'images';
            if (!in_array($type, ['images', 'downloads', 'header'])) {
                throw new InvalidArgumentException('Ungültiger Dateityp');
            }
            
            $uploadService = new UploadService();
            $files = $uploadService->listFiles($type);
            echo json_encode(['success' => true, 'files' => $files]);
            break;
        
        // ===== GENERATOR =====
        
        case 'generate':
            $generator = new GeneratorService();
            
            // Backup vor Generierung
            $tileService = new TileService();
            $tileService->backup();
            
            $result = $generator->generate();
            
            if (!$result['success']) {
                http_response_code(500);
            }
            echo json_encode($result);
            break;
            
        case 'preview':
            $generator = new GeneratorService();
            $html = $generator->preview();
            
            // HTML direkt zurückgeben (nicht JSON)
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            break;
        
        case 'extend_session':
            // Session-Zeit zurücksetzen
            $_SESSION['auth_time'] = time();
            echo json_encode(['success' => true, 'message' => 'Session verlängert']);
            break;
        
        case 'check_permissions':
            // Diagnose-Check für Admin - Schreibrechte prüfen
            require_once __DIR__ . '/../core/SecurityHelper.php';
            $perms = SecurityHelper::checkMediaDirectoryPermissions();
            echo json_encode([
                'success' => $perms['writable'],
                'permissions' => $perms
            ]);
            break;
        
        // ===== DEFAULT =====
        
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unbekannte Action: ' . $action]);
    }
    
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    
} catch (Exception $e) {
    LogService::error('API', 'Exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Interner Fehler']);
}
