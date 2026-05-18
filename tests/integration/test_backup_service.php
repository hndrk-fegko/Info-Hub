<?php

require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/../../backend/core/BackupService.php';
require_once __DIR__ . '/../../backend/core/GeneratorService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function backupAssert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function backupHash(?string $path): ?string {
    return (is_string($path) && file_exists($path)) ? md5_file($path) : null;
}

$service = new BackupService(
    new StorageService('tiles.json'),
    new StorageService('settings.json'),
    new FileSystemService()
);

$indexPath = __DIR__ . '/../index.html';
$tilesPath = __DIR__ . '/../../backend/data/tiles.json';
$settingsPath = __DIR__ . '/../../backend/data/settings.json';

$beforeHashes = [
    'index' => backupHash($indexPath),
    'tiles' => backupHash($tilesPath),
    'settings' => backupHash($settingsPath)
];

$snapshot = $service->createSnapshot('test');
backupAssert(is_array($snapshot) && !empty($snapshot['id']), 'Snapshot was not created');

$listedIds = array_column($service->listBackups(), 'id');
backupAssert(in_array($snapshot['id'], $listedIds, true), 'Snapshot is not visible in listBackups()');

$previewHtml = $service->getPreviewHtml($snapshot['id']);
backupAssert(strpos($previewHtml, '<base href="../../">') !== false, 'Preview HTML does not inject a base tag');

$export = $service->createExportArchive($snapshot['id']);
backupAssert(is_array($export) && !empty($export['path']) && file_exists($export['path']), 'ZIP export was not created');
@unlink($export['path']);

$cleanupBackupIds = [];

$publishResult = $service->publishCurrentState(new GeneratorService());
backupAssert(!empty($publishResult['success']), 'Publish workflow did not succeed');
backupAssert(!empty($publishResult['backupId']), 'Publish workflow did not return a backup id');
backupAssert(!empty($publishResult['quickRestore']['available']), 'Publish workflow did not expose quick restore state');
$cleanupBackupIds[] = $publishResult['backupId'];

$quickRestoreView = $service->getQuickRestoreViewData();
backupAssert(!empty($quickRestoreView['available']), 'Quick restore view data is not available after publish');

$quickRestore = $service->quickRestoreLastPublish();
backupAssert(!empty($quickRestore['success']), 'Quick restore did not succeed');
backupAssert(!empty($quickRestore['safetyBackupId']), 'Quick restore did not create a safety backup');
$cleanupBackupIds[] = $quickRestore['safetyBackupId'];

$quickRestoreAfterUse = $service->getQuickRestoreViewData();
backupAssert(empty($quickRestoreAfterUse['available']), 'Quick restore state was not cleared after use');

$afterQuickRestoreHashes = [
    'index' => backupHash($indexPath),
    'tiles' => backupHash($tilesPath),
    'settings' => backupHash($settingsPath)
];
backupAssert($beforeHashes === $afterQuickRestoreHashes, 'Publish and quick restore changed file contents for the current state');

$restoreEditor = $service->restoreBackup($snapshot['id'], 'editor');
backupAssert(!empty($restoreEditor['success']), 'Editor restore did not succeed');
backupAssert(($restoreEditor['mode'] ?? '') === 'editor', 'Editor restore did not report its mode');
backupAssert(!in_array('html', $restoreEditor['restored'] ?? [], true), 'Editor restore unexpectedly touched published HTML');
backupAssert(!empty($restoreEditor['safetyBackupId']), 'Editor restore did not create a safety backup');
$cleanupBackupIds[] = $restoreEditor['safetyBackupId'];

$afterEditorHashes = [
    'index' => backupHash($indexPath),
    'tiles' => backupHash($tilesPath),
    'settings' => backupHash($settingsPath)
];
backupAssert($beforeHashes === $afterEditorHashes, 'Editor restore changed file contents for the current-state snapshot');

$restoreSite = $service->restoreBackup($snapshot['id'], 'site');
backupAssert(!empty($restoreSite['success']), 'Site restore did not succeed');
backupAssert(($restoreSite['mode'] ?? '') === 'site', 'Site restore did not report its mode');
backupAssert(!in_array('tiles', $restoreSite['restored'] ?? [], true), 'Site restore unexpectedly touched tiles');
backupAssert(!in_array('settings', $restoreSite['restored'] ?? [], true), 'Site restore unexpectedly touched settings');
backupAssert(!empty($restoreSite['safetyBackupId']), 'Site restore did not create a safety backup');
$cleanupBackupIds[] = $restoreSite['safetyBackupId'];

$afterSiteHashes = [
    'index' => backupHash($indexPath),
    'tiles' => backupHash($tilesPath),
    'settings' => backupHash($settingsPath)
];
backupAssert($beforeHashes === $afterSiteHashes, 'Site restore changed file contents for the current-state snapshot');

$restoreAll = $service->restoreBackup($snapshot['id'], 'all');
backupAssert(!empty($restoreAll['success']), 'Full restore did not succeed');
backupAssert(($restoreAll['mode'] ?? '') === 'all', 'Full restore did not report its mode');
backupAssert(!empty($restoreAll['safetyBackupId']), 'Full restore did not create a safety backup');
$cleanupBackupIds[] = $restoreAll['safetyBackupId'];

$afterHashes = [
    'index' => backupHash($indexPath),
    'tiles' => backupHash($tilesPath),
    'settings' => backupHash($settingsPath)
];

backupAssert($beforeHashes === $afterHashes, 'Restoring a snapshot of the current state changed file contents');

$deleteSnapshot = $service->deleteBackup($snapshot['id']);
backupAssert(!empty($deleteSnapshot['success']), 'Created snapshot could not be deleted');

foreach ($cleanupBackupIds as $cleanupBackupId) {
    $deleteSafety = $service->deleteBackup($cleanupBackupId);
    backupAssert(!empty($deleteSafety['success']), 'Safety backup could not be deleted');
}

echo "Backup service smoke test passed\n";