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
 * - upload_background: Narrow-Hintergrund hochladen
 * - delete_file: Datei löschen
 * - list_files: Dateien auflisten
 * - generate: HTML generieren
 * - preview: Preview HTML
 * - get_tile_types: Verfügbare Tile-Typen
 */

header('Content-Type: application/json; charset=utf-8');
$bootstrapMode = 'api';
$bootstrapServices = [
    'LogService',
    'AuthService',
    'TileService',
    'UploadService',
    'GeneratorService',
    'StorageService',
    'ConfigService',
    'BackupService',
    'MediaPathHelper',
    'SettingsService',
];
$bootstrapMissingConfigMessage = 'System nicht konfiguriert. Bitte Setup ausführen.';
$bootstrap = require __DIR__ . '/../bootstrap.php';

$container = $bootstrap['container'];

// Error Handling
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    // Auth prüfen (außer für bestimmte Actions)
    $auth = $container->authService();
    $settingsService = $container->settingsService();
    $requestJson = null;
    $readJsonRequest = static function() use (&$requestJson): array {
        if (is_array($requestJson)) {
            return $requestJson;
        }

        $rawBody = file_get_contents('php://input');
        if (!is_string($rawBody) || trim($rawBody) === '') {
            $requestJson = [];
            return $requestJson;
        }

        $decoded = json_decode($rawBody, true);
        $requestJson = is_array($decoded) ? $decoded : [];
        return $requestJson;
    };
    $readArrayPayload = static function(string $postKey, string $bodyKey, array $default = []) use ($readJsonRequest): array {
        $postValue = json_decode($_POST[$postKey] ?? 'null', true);
        if (is_array($postValue) && $postValue !== []) {
            return $postValue;
        }

        $requestData = $readJsonRequest();
        $bodyValue = $requestData[$bodyKey] ?? $default;
        return is_array($bodyValue) ? $bodyValue : $default;
    };
    $requireStringParam = static function(array $values, string $message): string {
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        throw new InvalidArgumentException($message);
    };
    $tileService = null;
    $getTileService = static function() use (&$tileService, $container): TileService {
        if (!$tileService instanceof TileService) {
            $tileService = $container->tileService();
        }

        return $tileService;
    };
    $generatorService = null;
    $getGeneratorService = static function() use (&$generatorService, $container): GeneratorService {
        if (!$generatorService instanceof GeneratorService) {
            $generatorService = $container->generatorService();
        }

        return $generatorService;
    };
    $backupService = null;
    $getBackupService = static function() use (&$backupService, $container): BackupService {
        if (!$backupService instanceof BackupService) {
            $backupService = $container->backupService();
        }

        return $backupService;
    };
    $respondJson = static function(array $payload, int $status = 200): void {
        http_response_code($status);
        echo json_encode($payload);
    };
    $respondSuccess = static function(array $payload = [], int $status = 200) use ($respondJson): void {
        $respondJson(['success' => true] + $payload, $status);
    };
    $respondResult = static function(array $result, int $failureStatus = 400) use ($respondJson): void {
        $respondJson($result, !empty($result['success']) ? 200 : $failureStatus);
    };
    $getAdminSnapshot = static function(AuthService $auth): array {
        return [
            'emails' => $auth->getAdminEmails(),
            'invites' => $auth->getPendingInvites(),
        ];
    };
    $publicActions = [];  // Alle Actions erfordern Authentifizierung
    $getActions = ['get_tiles', 'get_tile', 'get_settings', 'get_tile_types', 'list_files', 'preview', 'render_all_tiles_html', 'render_canvas_layout', 'get_canvas_css', 'get_canvas_js', 'list_backups', 'view_backup_html', 'view_backup_asset', 'export_backup'];  // GET erlaubt
    
    // Action ermitteln
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // Auth-Check (nur für nicht-öffentliche Actions)
    if (!in_array($action, $publicActions) && !$auth->isAuthenticated()) {
        $respondJson(['success' => false, 'error' => 'Nicht authentifiziert'], 401);
        exit;
    }
    
    // CSRF-Token Validierung für modifizierende Actions (POST, nicht GET-Actions)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, $getActions)) {
        $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            LogService::warning('API', 'CSRF token mismatch', ['action' => $action]);
            $respondJson(['success' => false, 'error' => 'Ungültiges CSRF-Token'], 403);
            exit;
        }
    }
    
    // Request verarbeiten
    switch ($action) {
        
        // ===== TILES =====
        
        case 'get_tiles':
            $tiles = $getTileService()->getTiles();
            $respondSuccess(['tiles' => $tiles]);
            break;
            
        case 'get_tile':
            $id = $requireStringParam([
                $_GET['id'] ?? null,
                $_POST['id'] ?? null,
            ], 'Tile-ID erforderlich');

            $tile = $getTileService()->getTile($id);
            
            if ($tile === null) {
                $respondJson(['success' => false, 'error' => 'Tile nicht gefunden'], 404);
            } else {
                $respondSuccess(['tile' => $tile]);
            }
            break;
            
        case 'save_tile':
            $tileData = $readArrayPayload('tile', 'tile');
            
            if (empty($tileData)) {
                throw new InvalidArgumentException('Tile-Daten erforderlich');
            }
            
            $result = $getTileService()->saveTile($tileData);
            
            if (!$result['success']) {
            } else {
                // Alle Tiles mit zurückgeben für Quick-Edit-Sync
                $result['tiles'] = $getTileService()->getTiles();
            }
            $respondResult($result);
            break;
            
        case 'delete_tile':
            $id = $requireStringParam([
                $_POST['id'] ?? null,
            ], 'Tile-ID erforderlich');

            $result = $getTileService()->deleteTile($id);

            $respondResult($result);
            break;
            
        case 'update_positions':
            $positions = $readArrayPayload('positions', 'positions');

            $result = $getTileService()->updatePositions($positions);

            $respondResult($result);
            break;
            
        case 'get_tile_types':
            $types = $getTileService()->getAvailableTypes();
            // fieldMeta ergänzen für WYSIWYG-Editor (Feldtypen, Labels, Defaults)
            $typesWithMeta = $getTileService()->getAvailableTypesWithMeta();
            $respondSuccess(['types' => $types, 'typesWithMeta' => $typesWithMeta]);
            break;
        
        // ===== WYSIWYG EDITOR (v2) =====
        
        case 'render_tile_html':
            // Rendert eine einzelne Tile als HTML (für Live-Preview im Editor)
            $tileData = $readArrayPayload('tile', 'tile');
            
            if (empty($tileData) || empty($tileData['type'])) {
                throw new InvalidArgumentException('Tile-Daten mit type erforderlich');
            }
            
            $html = $getGeneratorService()->renderSingleTile($tileData);
            
            if ($html === null) {
                $respondJson(['success' => false, 'error' => 'Unbekannter Tile-Typ'], 400);
            } else {
                $respondSuccess(['html' => $html]);
            }
            break;
        
        case 'render_all_tiles_html':
            // Rendert alle Tiles als HTML-Fragmente (für WYSIWYG Canvas)
            // PARALLEL RENDER CONTRACT:
            // Response-Shape must stay in sync with GeneratorService::renderAllTilesHtml()
            // and RenderContract::RENDERED_TILE_KEYS,
            // backend/v2/editor.php and assets/js/v2/canvas.js.
            // tests/test_render_all_tiles_html_endpoint.php prueft diesen API-Vertrag explizit.
            $tiles = $getGeneratorService()->renderAllTilesHtml();
            $respondSuccess([
                'contractVersion' => RenderContract::RENDERED_TILE_VERSION,
                'tiles' => $tiles,
            ]);
            break;

        case 'render_canvas_layout':
            // Rendert die veröffentlichungsnahe Abschnittsstruktur für den WYSIWYG-Canvas.
            // PARALLEL RENDER CONTRACT:
            // Response-Shape must stay in sync with GeneratorService::renderCanvasSections()
            // and RenderContract::CANVAS_SECTION_KEYS,
            // backend/v2/editor.php and assets/js/v2/canvas.js.
            // tests/test_render_canvas_layout_endpoint.php prueft diesen API-Vertrag explizit.
            $sections = $getGeneratorService()->renderCanvasSections();
            $respondSuccess([
                'contractVersion' => RenderContract::CANVAS_SECTION_VERSION,
                'sections' => $sections,
            ]);
            break;
        
        case 'get_canvas_css':
            // Gibt shared+tile CSS zurück (für WYSIWYG Canvas)
            $css = $getGeneratorService()->getCanvasCSS();
            header('Content-Type: text/css; charset=utf-8');
            echo $css;
            exit;
        
        case 'get_canvas_js':
            // Gibt tile-spezifisches JS zurück (Lightbox, Countdown, etc.)
            $js = $getGeneratorService()->getCanvasJS();
            header('Content-Type: application/javascript; charset=utf-8');
            echo $js;
            exit;
        
        // ===== SETTINGS =====
        
        case 'get_settings':
            $responseSettings = $settingsService->getSettingsForEditor(
                $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '',
                $_SESSION['auth_email'] ?? ''
            );
            
            $respondSuccess(['settings' => $responseSettings]);
            break;
            
        case 'save_settings':
            $newSettings = $readArrayPayload('settings', 'settings');

            $responseSettings = $settingsService->saveSettings(
                $newSettings,
                $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '',
                $_SESSION['auth_email'] ?? ''
            );

            $respondSuccess(['settings' => $responseSettings]);
            break;

        case 'get_admins':
            $respondSuccess($getAdminSnapshot($auth));
            break;

        case 'invite_admin':
            $email = $_POST['email'] ?? '';
            $createdBy = $_SESSION['auth_email'] ?? '';
            $result = $auth->createInvite($email, $createdBy);
            $respondResult($result + $getAdminSnapshot($auth));
            break;

        case 'remove_admin_email':
            $email = $_POST['email'] ?? '';
            $currentEmail = $_SESSION['auth_email'] ?? '';
            $result = $auth->removeAdminEmail($email);
            if (!$result['success']) {
                http_response_code(400);
            }
            // Self-delete: Session sofort ungültig machen
            if ($result['success'] && strtolower(trim($email)) === strtolower(trim($currentEmail))) {
                LogService::info('AuthService', 'Admin self-deleted, destroying session', ['email' => $email]);
                $result['self_deleted'] = true;
                session_destroy();
            }
            $respondResult($result + $getAdminSnapshot($auth));
            break;

        case 'remove_admin_invite':
            $email = $_POST['email'] ?? '';
            $result = $auth->removeInvite($email);
            $respondResult($result + $getAdminSnapshot($auth));
            break;
        
        // ===== UPLOADS =====
        
        case 'upload_image':
            if (empty($_FILES['file'])) {
                throw new InvalidArgumentException('Keine Datei hochgeladen');
            }
            
            $uploadService = $container->uploadService();
            $result = $uploadService->uploadImage($_FILES['file']);

            $respondResult($result);
            break;
            
        case 'upload_download':
            if (empty($_FILES['file'])) {
                throw new InvalidArgumentException('Keine Datei hochgeladen');
            }
            
            $uploadService = $container->uploadService();
            $result = $uploadService->uploadDownload($_FILES['file']);

            $respondResult($result);
            break;
            
        case 'upload_header':
            if (empty($_FILES['file'])) {
                throw new InvalidArgumentException('Keine Datei hochgeladen');
            }
            
            $uploadService = $container->uploadService();
            $result = $uploadService->uploadHeader(
                $_FILES['file'],
                $_POST['headerPlaceholder'] ?? null,
                $_POST['headerImageWidth'] ?? null,
                $_POST['headerImageHeight'] ?? null
            );
            
            if ($result['success']) {
                $settingsService->saveSettings(
                    [
                        'site' => [
                            'headerImage' => $result['path'] ?? null,
                            'headerImagePlaceholder' => $result['placeholder'] ?? null,
                            'headerImageWidth' => isset($result['width']) ? (int) $result['width'] : null,
                            'headerImageHeight' => isset($result['height']) ? (int) $result['height'] : null,
                        ],
                    ],
                    $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '',
                    $_SESSION['auth_email'] ?? ''
                );
            }
            $respondResult($result);
            break;

        case 'upload_background':
            if (empty($_FILES['file'])) {
                throw new InvalidArgumentException('Keine Datei hochgeladen');
            }

            $uploadService = $container->uploadService();
            $result = $uploadService->uploadBackground($_FILES['file']);

            if ($result['success']) {
                $settingsService->saveSettings(
                    ['theme' => ['narrowBackgroundImage' => $result['path'] ?? null]],
                    $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '',
                    $_SESSION['auth_email'] ?? ''
                );
            }

            $respondResult($result);
            break;
            
        case 'delete_file':
            $uploadService = $container->uploadService();
            $success = $uploadService->deleteFile(
                $_POST['type'] ?? '',
                $_POST['filename'] ?? '',
                $_POST['path'] ?? null
            );
            $respondJson(['success' => $success]);
            break;
            
        case 'list_files':
            $uploadService = $container->uploadService();
            $files = $uploadService->listFiles($_GET['type'] ?? 'images');
            $respondSuccess(['files' => $files]);
            break;

        case 'list_backups':
            $respondSuccess(['backups' => $getBackupService()->listBackups()]);
            break;

        case 'view_backup_html':
            $id = $requireStringParam([
                $_GET['id'] ?? null,
            ], 'Backup-ID erforderlich');

            header_remove('Content-Type');
            header('Content-Type: text/html; charset=utf-8');
            echo $getBackupService()->getPreviewHtml($id);
            break;

        case 'view_backup_asset':
            $id = $requireStringParam([
                $_GET['id'] ?? null,
            ], 'Backup-ID und Asset-Pfad erforderlich');
            $path = $requireStringParam([
                $_GET['path'] ?? null,
            ], 'Backup-ID und Asset-Pfad erforderlich');

            $assetPath = $getBackupService()->resolvePreviewAsset($id, $path);
            if ($assetPath === null || !file_exists($assetPath)) {
                http_response_code(404);
                header_remove('Content-Type');
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Asset nicht gefunden';
                break;
            }

            header_remove('Content-Type');
            header('Content-Type: ' . (mime_content_type($assetPath) ?: 'application/octet-stream'));
            header('Content-Length: ' . filesize($assetPath));
            readfile($assetPath);
            break;

        case 'export_backup':
            $id = $requireStringParam([
                $_GET['id'] ?? null,
            ], 'Backup-ID erforderlich');

            $export = $getBackupService()->createExportArchive($id);
            if ($export === false || !file_exists($export['path'])) {
                $respondJson(['success' => false, 'error' => 'Export fehlgeschlagen'], 500);
                break;
            }

            header_remove('Content-Type');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($export['downloadName']) . '"');
            header('Content-Length: ' . filesize($export['path']));
            readfile($export['path']);
            @unlink($export['path']);
            break;

        case 'restore_backup':
            $id = $requireStringParam([
                $_POST['id'] ?? null,
            ], 'Backup-ID erforderlich');
            $mode = $_POST['mode'] ?? 'all';

            $result = $getBackupService()->restoreBackup($id, $mode);
            $respondResult($result);
            break;

        case 'quick_restore_last_publish':
            $result = $getBackupService()->quickRestoreLastPublish();
            $respondResult($result);
            break;

        case 'delete_backup':
            $id = $requireStringParam([
                $_POST['id'] ?? null,
            ], 'Backup-ID erforderlich');

            $result = $getBackupService()->deleteBackup($id);
            $respondResult($result);
            break;
        
        // ===== GENERATOR =====
        
        case 'generate':
            $result = $getBackupService()->publishCurrentState($getGeneratorService());
            $respondResult($result, 500);
            break;
            
        case 'preview':
            $html = $getGeneratorService()->preview();
            
            // HTML direkt zurückgeben (nicht JSON)
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            break;
        
        case 'extend_session':
            // Session-Zeit zurücksetzen
            $_SESSION['auth_time'] = time();
            $respondSuccess(['message' => 'Session verlängert']);
            break;
        
        case 'check_permissions':
            // Diagnose-Check für Admin - Schreibrechte prüfen
            require_once __DIR__ . '/../core/SecurityHelper.php';
            $perms = SecurityHelper::checkMediaDirectoryPermissions();
            $respondJson([
                'success' => $perms['writable'],
                'permissions' => $perms
            ]);
            break;
        
        // ===== DEFAULT =====
        
        default:
            $respondJson(['success' => false, 'error' => 'Unbekannte Action: ' . $action], 400);
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
