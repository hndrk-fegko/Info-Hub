<?php
/**
 * Shared bootstrap for backend entry points.
 *
 * Expected caller variables before require:
 * - $bootstrapMode: page|api|setup
 * - $bootstrapServices: list of service names to require
 * - $bootstrapMissingConfigRedirect: redirect target for page mode
 * - $bootstrapMissingConfigStatus: HTTP status for api mode
 * - $bootstrapMissingConfigMessage: JSON error message for api mode
 * - $bootstrapStartSession: whether to start a PHP session
 */

$bootstrapMode = $bootstrapMode ?? 'page';
$bootstrapServices = $bootstrapServices ?? [];
$bootstrapStartSession = $bootstrapStartSession ?? true;
$bootstrapBackendRoot = str_replace('\\', '/', __DIR__);
$bootstrapConfigPath = $bootstrapBackendRoot . '/config.php';
$bootstrapConfigLoaded = false;

$bootstrapServiceMap = [
    'AuthService' => 'core/AuthService.php',
    'BackupService' => 'core/BackupService.php',
    'ConfigService' => 'core/ConfigService.php',
    'GeneratorService' => 'core/GeneratorService.php',
    'LogService' => 'core/LogService.php',
    'MediaPathHelper' => 'core/MediaPathHelper.php',
    'SecurityHelper' => 'core/SecurityHelper.php',
    'SettingsService' => 'core/SettingsService.php',
    'StorageService' => 'core/StorageService.php',
    'TileService' => 'core/TileService.php',
    'UploadService' => 'core/UploadService.php',
];

if (file_exists($bootstrapConfigPath)) {
    require_once $bootstrapConfigPath;
    $bootstrapConfigLoaded = true;
} else {
    switch ($bootstrapMode) {
        case 'setup':
            if (!defined('DEBUG_MODE')) {
                define('DEBUG_MODE', false);
            }
            error_reporting(0);
            ini_set('display_errors', '0');
            break;

        case 'api':
            http_response_code((int) ($bootstrapMissingConfigStatus ?? 503));
            echo json_encode([
                'success' => false,
                'error' => (string) ($bootstrapMissingConfigMessage ?? 'System nicht konfiguriert. Bitte Setup ausführen.'),
            ]);
            exit;

        case 'page':
        default:
            header('Location: ' . (string) ($bootstrapMissingConfigRedirect ?? 'setup.php'));
            exit;
    }
}

if ($bootstrapStartSession && session_status() === PHP_SESSION_NONE) {
    session_start();
}

foreach ($bootstrapServices as $bootstrapServiceName) {
    $bootstrapRelativePath = $bootstrapServiceMap[$bootstrapServiceName] ?? null;
    if ($bootstrapRelativePath === null) {
        throw new InvalidArgumentException('Unknown bootstrap service: ' . $bootstrapServiceName);
    }

    require_once $bootstrapBackendRoot . '/' . $bootstrapRelativePath;
}

require_once $bootstrapBackendRoot . '/core/AppContainer.php';

$bootstrapContainer = new AppContainer($bootstrapBackendRoot, $bootstrapConfigPath);

$bootstrap = [
    'backendRoot' => $bootstrapBackendRoot,
    'configPath' => $bootstrapConfigPath,
    'configLoaded' => $bootstrapConfigLoaded,
    'container' => $bootstrapContainer,
];

unset(
    $bootstrapBackendRoot,
    $bootstrapConfigLoaded,
    $bootstrapConfigPath,
    $bootstrapContainer,
    $bootstrapMode,
    $bootstrapRelativePath,
    $bootstrapServiceMap,
    $bootstrapServiceName,
    $bootstrapServices,
    $bootstrapStartSession
);

return $bootstrap;
