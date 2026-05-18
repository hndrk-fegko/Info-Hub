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

require_once __DIR__ . '/ApiActionGroupInterface.php';
require_once __DIR__ . '/ApiResponder.php';
require_once __DIR__ . '/ApiContext.php';
require_once __DIR__ . '/actions/TileApiActions.php';
require_once __DIR__ . '/actions/RenderApiActions.php';
require_once __DIR__ . '/actions/SettingsApiActions.php';
require_once __DIR__ . '/actions/AdminApiActions.php';
require_once __DIR__ . '/actions/UploadApiActions.php';
require_once __DIR__ . '/actions/BackupApiActions.php';
require_once __DIR__ . '/actions/SystemApiActions.php';

// Error Handling
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    // Auth prüfen (außer für bestimmte Actions)
    $auth = $container->authService();
    $settingsService = $container->settingsService();
    $context = new ApiContext($container, $auth, $settingsService);
    $responder = new ApiResponder();
    $actionGroups = [
        new TileApiActions(),
        new RenderApiActions(),
        new SettingsApiActions(),
        new AdminApiActions(),
        new UploadApiActions(),
        new BackupApiActions(),
        new SystemApiActions(),
    ];
    $actionHandlers = [];
    foreach ($actionGroups as $actionGroup) {
        foreach ($actionGroup->register($context, $responder) as $actionName => $handler) {
            $actionHandlers[$actionName] = $handler;
        }
    }
    $publicActions = [];  // Alle Actions erfordern Authentifizierung
    $getActions = ['get_tiles', 'get_tile', 'get_settings', 'get_tile_types', 'list_files', 'preview', 'render_all_tiles_html', 'render_canvas_layout', 'get_canvas_css', 'get_canvas_js', 'list_backups', 'view_backup_html', 'view_backup_asset', 'export_backup'];  // GET erlaubt
    
    // Action ermitteln
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // Auth-Check (nur für nicht-öffentliche Actions)
    if (!in_array($action, $publicActions) && !$auth->isAuthenticated()) {
        $responder->json(['success' => false, 'error' => 'Nicht authentifiziert'], 401);
        exit;
    }
    
    // CSRF-Token Validierung für modifizierende Actions (POST, nicht GET-Actions)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, $getActions)) {
        $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            LogService::warning('API', 'CSRF token mismatch', ['action' => $action]);
            $responder->json(['success' => false, 'error' => 'Ungültiges CSRF-Token'], 403);
            exit;
        }
    }

    if (!isset($actionHandlers[$action])) {
        $responder->json(['success' => false, 'error' => 'Unbekannte Action: ' . $action], 400);
    } else {
        $actionHandlers[$action]();
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
