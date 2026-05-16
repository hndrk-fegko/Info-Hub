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
    require_once __DIR__ . '/../core/ConfigService.php';

    $isValidHexColor = static function($value): bool {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    };

    $sanitizeMediaPath = static function($value): ?string {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return preg_match('#^/backend/media/[a-z0-9/_\-.]+$#i', $value) ? $value : null;
    };
    
    // Auth prüfen (außer für bestimmte Actions)
    $auth = new AuthService();
    $configService = new ConfigService(__DIR__ . '/../config.php');
    $publicActions = [];  // Alle Actions erfordern Authentifizierung
    $getActions = ['get_tiles', 'get_tile', 'get_settings', 'get_tile_types', 'list_files', 'preview', 'render_all_tiles_html', 'render_canvas_layout', 'get_canvas_css', 'get_canvas_js'];  // GET erlaubt
    
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
                http_response_code(400);
            }

            echo json_encode($result);
            break;
            
        case 'update_positions':
            $positions = json_decode($_POST['positions'] ?? '[]', true);
            
            $tileService = new TileService();
            $result = $tileService->updatePositions($positions);

            if (!$result['success']) {
                http_response_code(400);
            }

            echo json_encode($result);
            break;
            
        case 'get_tile_types':
            $tileService = new TileService();
            $types = $tileService->getAvailableTypes();
            // fieldMeta ergänzen für WYSIWYG-Editor (Feldtypen, Labels, Defaults)
            $typesWithMeta = $tileService->getAvailableTypesWithMeta();
            echo json_encode(['success' => true, 'types' => $types, 'typesWithMeta' => $typesWithMeta]);
            break;
        
        // ===== WYSIWYG EDITOR (v2) =====
        
        case 'render_tile_html':
            // Rendert eine einzelne Tile als HTML (für Live-Preview im Editor)
            $tileData = json_decode(file_get_contents('php://input'), true)['tile'] ?? [];
            if (empty($tileData)) {
                $tileData = json_decode($_POST['tile'] ?? '{}', true);
            }
            
            if (empty($tileData) || empty($tileData['type'])) {
                throw new InvalidArgumentException('Tile-Daten mit type erforderlich');
            }
            
            $generator = new GeneratorService();
            $html = $generator->renderSingleTile($tileData);
            
            if ($html === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Unbekannter Tile-Typ']);
            } else {
                echo json_encode(['success' => true, 'html' => $html]);
            }
            break;
        
        case 'render_all_tiles_html':
            // Rendert alle Tiles als HTML-Fragmente (für WYSIWYG Canvas)
            // PARALLEL RENDER CONTRACT:
            // Response-Shape must stay in sync with GeneratorService::renderAllTilesHtml(),
            // backend/v2/editor.php and assets/js/v2/canvas.js.
            $generator = new GeneratorService();
            $tiles = $generator->renderAllTilesHtml();
            echo json_encode(['success' => true, 'tiles' => $tiles]);
            break;

        case 'render_canvas_layout':
            // Rendert die veröffentlichungsnahe Abschnittsstruktur für den WYSIWYG-Canvas.
            // PARALLEL RENDER CONTRACT:
            // Response-Shape must stay in sync with GeneratorService::renderCanvasSections(),
            // backend/v2/editor.php and assets/js/v2/canvas.js.
            $generator = new GeneratorService();
            $sections = $generator->renderCanvasSections();
            echo json_encode(['success' => true, 'sections' => $sections]);
            break;
        
        case 'get_canvas_css':
            // Gibt shared+tile CSS zurück (für WYSIWYG Canvas)
            $generator = new GeneratorService();
            $css = $generator->getCanvasCSS();
            header('Content-Type: text/css; charset=utf-8');
            echo $css;
            exit;
        
        case 'get_canvas_js':
            // Gibt tile-spezifisches JS zurück (Lightbox, Countdown, etc.)
            $generator = new GeneratorService();
            $js = $generator->getCanvasJS();
            header('Content-Type: application/javascript; charset=utf-8');
            echo $js;
            exit;
        
        // ===== SETTINGS =====
        
        case 'get_settings':
            $storage = new StorageService('settings.json');
            $settings = $storage->read();
            $responseSettings = $settings;
            
            // Email für Security maskieren (nur letzte 4 Zeichen zeigen)
            if (isset($responseSettings['auth']['email'])) {
                $email = $responseSettings['auth']['email'];
                $masked = '***' . substr($email, -10);
                $responseSettings['auth']['emailMasked'] = $masked;
            }
            if (isset($responseSettings['auth']['emails']) && is_array($responseSettings['auth']['emails'])) {
                $responseSettings['auth']['emailsMasked'] = array_map(function($email) {
                    return '***' . substr($email, -10);
                }, $responseSettings['auth']['emails']);
            }
            
            // Sensible Auth-Daten entfernen - nur maskierte Version senden
            unset($responseSettings['auth']['email'], $responseSettings['auth']['emails'], $responseSettings['auth']['invites'], $responseSettings['system']);
            $responseSettings['system']['mailFromAddress'] = $configService->getMailFromAddress(
                $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '',
                $_SESSION['auth_email'] ?? ''
            );
            
            echo json_encode(['success' => true, 'settings' => $responseSettings]);
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
                $previousHeaderImage = $settings['site']['headerImage'] ?? null;

                // Site-Felder: nur erlaubte Keys, Strings sanitizen
                $allowedSiteKeys = ['title', 'pageTitle', 'headerImage', 'headerFocusPoint', 'footerText'];
                foreach ($allowedSiteKeys as $key) {
                    if (isset($newSettings['site'][$key])) {
                        $settings['site'][$key] = $key === 'headerImage'
                            ? $sanitizeMediaPath($newSettings['site'][$key])
                            : $newSettings['site'][$key];
                    }
                }

                if (array_key_exists('headerImage', $newSettings['site']) && empty($newSettings['site']['headerImage'])) {
                    $settings['site']['headerImage'] = null;
                    $settings['site']['headerImagePlaceholder'] = null;
                    $settings['site']['headerImageWidth'] = null;
                    $settings['site']['headerImageHeight'] = null;
                } elseif (($settings['site']['headerImage'] ?? null) !== $previousHeaderImage) {
                    $settings['site']['headerImagePlaceholder'] = null;
                    $settings['site']['headerImageWidth'] = null;
                    $settings['site']['headerImageHeight'] = null;
                }
            }
            if (isset($newSettings['theme'])) {
                // Theme-Farben: nur gültige Hex-Werte (#RRGGBB) erlauben
                $colorKeys = [
                    'backgroundColor',
                    'accentColor',
                    'accentColor2',
                    'accentColor3',
                    'narrowBackgroundColor',
                    'narrowGradientColor1',
                    'narrowGradientColor2',
                    'narrowBackgroundOverlayColor'
                ];
                foreach ($colorKeys as $key) {
                    if (isset($newSettings['theme'][$key])) {
                        $color = $newSettings['theme'][$key];
                        if ($isValidHexColor($color)) {
                            $settings['theme'][$key] = $color;
                        }
                    }
                }

                if (isset($newSettings['theme']['narrowBackgroundMode'])) {
                    $mode = (string) $newSettings['theme']['narrowBackgroundMode'];
                    if (in_array($mode, ['solid', 'gradient', 'image'], true)) {
                        $settings['theme']['narrowBackgroundMode'] = $mode;
                    }
                }

                if (isset($newSettings['theme']['narrowBackgroundImageDisplay'])) {
                    $display = (string) $newSettings['theme']['narrowBackgroundImageDisplay'];
                    if (in_array($display, ['cover', 'tile'], true)) {
                        $settings['theme']['narrowBackgroundImageDisplay'] = $display;
                    }
                }

                if (isset($newSettings['theme']['narrowBackgroundImageMotion'])) {
                    $motion = (string) $newSettings['theme']['narrowBackgroundImageMotion'];
                    if (in_array($motion, ['fixed', 'parallax'], true)) {
                        $settings['theme']['narrowBackgroundImageMotion'] = $motion;
                    }
                }
                
                // Boolean-Felder
                if (isset($newSettings['theme']['narrowLayout'])) {
                    $settings['theme']['narrowLayout'] = (bool) $newSettings['theme']['narrowLayout'];
                }
                if (isset($newSettings['theme']['narrowBackgroundOverlayEnabled'])) {
                    $settings['theme']['narrowBackgroundOverlayEnabled'] = (bool) $newSettings['theme']['narrowBackgroundOverlayEnabled'];
                }
                if (isset($newSettings['theme']['narrowContentShadow'])) {
                    $settings['theme']['narrowContentShadow'] = (bool) $newSettings['theme']['narrowContentShadow'];
                }
                
                // Numerische Felder
                if (isset($newSettings['theme']['narrowWidth'])) {
                    $w = (int) $newSettings['theme']['narrowWidth'];
                    if ($w >= 600 && $w <= 1400) {
                        $settings['theme']['narrowWidth'] = $w;
                    }
                }
                if (isset($newSettings['theme']['narrowGradientAngle'])) {
                    $angle = (int) $newSettings['theme']['narrowGradientAngle'];
                    if ($angle >= 0 && $angle <= 360) {
                        $settings['theme']['narrowGradientAngle'] = $angle;
                    }
                }
                if (isset($newSettings['theme']['narrowBackgroundOverlayOpacity'])) {
                    $opacity = (int) $newSettings['theme']['narrowBackgroundOverlayOpacity'];
                    if ($opacity >= 0 && $opacity <= 100) {
                        $settings['theme']['narrowBackgroundOverlayOpacity'] = $opacity;
                    }
                }

                if (array_key_exists('narrowBackgroundImage', $newSettings['theme'])) {
                    $settings['theme']['narrowBackgroundImage'] = $sanitizeMediaPath($newSettings['theme']['narrowBackgroundImage']);
                }
            }

            if (isset($newSettings['system']['mailFromAddress'])) {
                $mailFromAddress = strtolower(trim((string) $newSettings['system']['mailFromAddress']));
                if ($mailFromAddress === '' || !filter_var($mailFromAddress, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Gültige Absender-Adresse erforderlich');
                }

                if (!$configService->updateMailFromAddress($mailFromAddress)) {
                    throw new RuntimeException('MAIL_FROM_ADDRESS konnte nicht in config.php gespeichert werden');
                }

                $responseMailFromAddress = $mailFromAddress;
            } else {
                $responseMailFromAddress = $configService->getMailFromAddress(
                    $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '',
                    $_SESSION['auth_email'] ?? ''
                );
            }

            unset($settings['system']);
            
            // Speichern
            if ($storage->write($settings)) {
                LogService::info('API', 'Settings saved');
                $responseSettings = $settings;
                $responseSettings['system']['mailFromAddress'] = $responseMailFromAddress;
                echo json_encode(['success' => true, 'settings' => $responseSettings]);
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
                $clientPlaceholder = $_POST['headerPlaceholder'] ?? null;
                if (!is_string($clientPlaceholder) || !preg_match('#^data:image/(?:webp|jpeg);base64,[A-Za-z0-9+/=]+$#', $clientPlaceholder) || strlen($clientPlaceholder) > 100000) {
                    $clientPlaceholder = null;
                }

                $clientWidth = isset($_POST['headerImageWidth']) ? (int)$_POST['headerImageWidth'] : null;
                $clientHeight = isset($_POST['headerImageHeight']) ? (int)$_POST['headerImageHeight'] : null;

                if (empty($result['placeholder']) && $clientPlaceholder) {
                    $result['placeholder'] = $clientPlaceholder;
                }
                if (empty($result['width']) && $clientWidth && $clientWidth > 0) {
                    $result['width'] = $clientWidth;
                }
                if (empty($result['height']) && $clientHeight && $clientHeight > 0) {
                    $result['height'] = $clientHeight;
                }

                // Auch in Settings speichern
                $storage = new StorageService('settings.json');
                $settings = $storage->read();
                $settings['site']['headerImage'] = $result['path'];
                $settings['site']['headerImagePlaceholder'] = $result['placeholder'] ?? null;
                $settings['site']['headerImageWidth'] = $result['width'] ?? null;
                $settings['site']['headerImageHeight'] = $result['height'] ?? null;
                $storage->write($settings);
            } else {
                http_response_code(400);
            }
            echo json_encode($result);
            break;

        case 'upload_background':
            if (empty($_FILES['file'])) {
                throw new InvalidArgumentException('Keine Datei hochgeladen');
            }

            $uploadService = new UploadService();
            $result = $uploadService->uploadBackground($_FILES['file']);

            if ($result['success']) {
                $storage = new StorageService('settings.json');
                $settings = $storage->read();
                $settings['theme']['narrowBackgroundImage'] = $result['path'];
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
                if (preg_match('#/backend/media/(images|downloads|header|backgrounds)/(.+)$#', $path, $matches)) {
                    $type = $matches[1];
                    $filename = $matches[2];
                }
            }
            
            if (empty($type) || empty($filename)) {
                throw new InvalidArgumentException('Typ und Dateiname erforderlich');
            }
            
            if (!in_array($type, ['images', 'downloads', 'header', 'backgrounds'])) {
                throw new InvalidArgumentException('Ungültiger Dateityp');
            }
            
            $uploadService = new UploadService();
            $success = $uploadService->deleteFile($type, $filename);
            echo json_encode(['success' => $success]);
            break;
            
        case 'list_files':
            $type = $_GET['type'] ?? 'images';
            if (!in_array($type, ['images', 'downloads', 'header', 'backgrounds'])) {
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
