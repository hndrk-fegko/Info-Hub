<?php
/**
 * BackupService - Paket-Backups, Export und Restore.
 *
 * Verwaltet neue Snapshot-Pakete unter backend/archive/packages und
 * gruppiert bestehende Legacy-Dateien aus backend/archive für die UI.
 */

require_once __DIR__ . '/LogService.php';
require_once __DIR__ . '/StorageService.php';

class BackupService {

    private const LEGACY_PAIR_WINDOW = 2;
    private const RESTORE_MODES = ['editor', 'site', 'all'];

    private string $projectRoot;
    private string $backendRoot;
    private string $archiveDir;
    private string $packageDir;
    private string $indexPath;
    private StorageService $tilesStorage;
    private StorageService $settingsStorage;

    public function __construct() {
        $this->backendRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: dirname(__DIR__));
        $this->projectRoot = str_replace('\\', '/', realpath($this->backendRoot . '/..') ?: dirname($this->backendRoot));
        $this->archiveDir = $this->backendRoot . '/archive';
        $this->packageDir = $this->archiveDir . '/packages';
        $this->indexPath = $this->projectRoot . '/index.html';
        $this->tilesStorage = new StorageService('tiles.json');
        $this->settingsStorage = new StorageService('settings.json');

        $this->ensureDirectory($this->archiveDir);
        $this->ensureDirectory($this->packageDir);
    }

    /**
     * Erstellt ein vollständiges Paket-Backup des aktuellen Stands.
     */
    public function createSnapshot(string $reason = 'manual', array $context = []): array|false {
        $snapshotId = 'snapshot_' . date('Y-m-d_H-i-s') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $snapshotDir = $this->packageDir . '/' . $snapshotId;
        $dataDir = $snapshotDir . '/data';
        $publishedDir = $snapshotDir . '/published';

        try {
            $this->ensureDirectory($snapshotDir);
            $this->ensureDirectory($dataDir);
            $this->ensureDirectory($publishedDir);

            $tiles = file_exists($this->tilesStorage->getFilePath()) ? $this->tilesStorage->read() : [];
            $settings = file_exists($this->settingsStorage->getFilePath()) ? $this->settingsStorage->read() : [];
            $html = file_exists($this->indexPath) ? file_get_contents($this->indexPath) : false;
            $html = is_string($html) ? $html : null;

            $manifestFiles = [
                'html' => null,
                'tiles' => null,
                'settings' => null,
                'media' => [],
                'missingMedia' => []
            ];

            if (file_exists($this->tilesStorage->getFilePath())) {
                $this->copyFile($this->tilesStorage->getFilePath(), $dataDir . '/tiles.json');
                $manifestFiles['tiles'] = 'data/tiles.json';
            }

            if (file_exists($this->settingsStorage->getFilePath())) {
                $this->copyFile($this->settingsStorage->getFilePath(), $dataDir . '/settings.json');
                $manifestFiles['settings'] = 'data/settings.json';
            }

            if ($html !== null) {
                file_put_contents($publishedDir . '/index.html', $html);
                $manifestFiles['html'] = 'published/index.html';
            }

            $mediaPaths = $this->collectMediaPaths($tiles, $settings, $html ?? '');
            foreach ($mediaPaths as $mediaPath) {
                $copiedMediaPath = $this->copyMediaIntoSnapshot($snapshotDir, $mediaPath);
                if ($copiedMediaPath !== null) {
                    $manifestFiles['media'][] = $copiedMediaPath;
                } else {
                    $manifestFiles['missingMedia'][] = $mediaPath;
                }
            }

            sort($manifestFiles['media']);
            sort($manifestFiles['missingMedia']);

            $manifest = [
                'id' => $snapshotId,
                'format' => 'package',
                'version' => 1,
                'createdAt' => date('c'),
                'createdTs' => time(),
                'reason' => $reason,
                'context' => $context,
                'siteTitle' => $settings['site']['title'] ?? '',
                'counts' => [
                    'tiles' => is_array($tiles) ? count($tiles) : 0,
                    'media' => count($manifestFiles['media']),
                    'missingMedia' => count($manifestFiles['missingMedia']),
                    'files' => count(array_filter([$manifestFiles['html'], $manifestFiles['tiles'], $manifestFiles['settings']])) + count($manifestFiles['media'])
                ],
                'files' => $manifestFiles,
                'sizeBytes' => $this->getDirectorySize($snapshotDir)
            ];

            $this->writeJsonFile($snapshotDir . '/manifest.json', $manifest);

            LogService::info('BackupService', 'Snapshot created', [
                'id' => $snapshotId,
                'reason' => $reason,
                'media' => $manifest['counts']['media']
            ]);

            return $this->describePackageBackup($snapshotDir);
        } catch (Throwable $e) {
            LogService::error('BackupService', 'Snapshot creation failed', [
                'reason' => $reason,
                'error' => $e->getMessage()
            ]);

            if (is_dir($snapshotDir)) {
                $this->deleteDirectory($snapshotDir);
            }

            return false;
        }
    }

    /**
     * Gibt alle Backups zurück, neueste zuerst.
     */
    public function listBackups(): array {
        $backups = array_merge($this->listPackageBackups(), $this->listLegacyBackups());

        usort($backups, function(array $left, array $right) {
            return ($right['createdTs'] ?? 0) <=> ($left['createdTs'] ?? 0);
        });

        return $backups;
    }

    /**
     * Gibt ein einzelnes Backup zurück.
     */
    public function getBackup(string $id): ?array {
        foreach ($this->listBackups() as $backup) {
            if (($backup['id'] ?? '') === $id) {
                return $backup;
            }
        }

        return null;
    }

    /**
     * Löscht ein Backup.
     */
    public function deleteBackup(string $id): array {
        $backup = $this->getBackup($id);
        if ($backup === null) {
            return ['success' => false, 'error' => 'Backup nicht gefunden'];
        }

        if (($backup['format'] ?? '') === 'package') {
            $root = $backup['paths']['root'] ?? null;
            if (!is_string($root) || !is_dir($root)) {
                return ['success' => false, 'error' => 'Backup-Verzeichnis fehlt'];
            }

            $this->deleteDirectory($root);
        } else {
            foreach ($backup['paths']['files'] ?? [] as $filePath) {
                if (is_string($filePath) && file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
        }

        LogService::info('BackupService', 'Backup deleted', ['id' => $id]);

        return ['success' => true, 'message' => 'Backup gelöscht'];
    }

    /**
     * Stellt ein Backup wieder her. Vorher wird der aktuelle Stand gesichert.
     */
    public function restoreBackup(string $id, string $mode = 'all'): array {
        $mode = $this->normalizeRestoreMode($mode);
        if ($mode === null) {
            return ['success' => false, 'error' => 'Ungültiger Restore-Modus'];
        }

        $backup = $this->getBackup($id);
        if ($backup === null) {
            return ['success' => false, 'error' => 'Backup nicht gefunden'];
        }

        $safetyBackup = $this->createSnapshot('pre-restore', [
            'restoreTarget' => $id,
            'restoreMode' => $mode
        ]);
        if ($safetyBackup === false) {
            return ['success' => false, 'error' => 'Aktueller Stand konnte vor dem Restore nicht gesichert werden'];
        }

        $restoreEditorState = in_array($mode, ['editor', 'all'], true);
        $restorePublishedSite = in_array($mode, ['site', 'all'], true);
        $restoreSharedMedia = ($restoreEditorState || $restorePublishedSite)
            && ($backup['format'] ?? '') === 'package'
            && !empty($backup['paths']['mediaRoot'])
            && is_dir($backup['paths']['mediaRoot']);

        $restored = [];
        $warnings = [];

        try {
            if ($restoreEditorState && !empty($backup['paths']['tiles']) && file_exists($backup['paths']['tiles'])) {
                $this->copyFile($backup['paths']['tiles'], $this->tilesStorage->getFilePath());
                $restored[] = 'tiles';
            }

            if ($restoreEditorState && !empty($backup['paths']['settings']) && file_exists($backup['paths']['settings'])) {
                $this->copyFile($backup['paths']['settings'], $this->settingsStorage->getFilePath());
                $restored[] = 'settings';
            }

            if ($restoreSharedMedia) {
                $restoredMedia = $this->restoreMediaDirectory($backup['paths']['mediaRoot']);
                if ($restoredMedia > 0) {
                    $restored[] = 'media';
                }
            }

            if ($restorePublishedSite && !empty($backup['paths']['html']) && file_exists($backup['paths']['html'])) {
                $this->copyFile($backup['paths']['html'], $this->indexPath);
                $restored[] = 'html';
            }

            if (($backup['format'] ?? '') === 'legacy') {
                $warnings[] = 'Legacy-Backup wurde best effort wiederhergestellt. Historische Settings und gepackte Medien standen dort noch nicht zur Verfügung.';
            }

            LogService::info('BackupService', 'Backup restored', [
                'id' => $id,
                'mode' => $mode,
                'safetyBackup' => $safetyBackup['id'] ?? null,
                'restored' => $restored
            ]);

            return [
                'success' => true,
                'message' => 'Backup wiederhergestellt',
                'mode' => $mode,
                'modeLabel' => $this->mapRestoreModeLabel($mode),
                'safetyBackupId' => $safetyBackup['id'] ?? null,
                'restored' => $restored,
                'warnings' => $warnings
            ];
        } catch (Throwable $e) {
            LogService::error('BackupService', 'Restore failed', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'error' => 'Restore fehlgeschlagen: ' . $e->getMessage()];
        }
    }

    /**
     * Erstellt ein ZIP-Export für ein Backup.
     */
    public function createExportArchive(string $id): array|false {
        if (!class_exists('ZipArchive')) {
            LogService::error('BackupService', 'ZIP export unavailable', ['id' => $id]);
            return false;
        }

        $backup = $this->getBackup($id);
        if ($backup === null) {
            return false;
        }

        $tempBase = tempnam(sys_get_temp_dir(), 'infohub_backup_');
        if ($tempBase === false) {
            return false;
        }

        @unlink($tempBase);
        $zipPath = $tempBase . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        if (($backup['format'] ?? '') === 'package') {
            $root = $backup['paths']['root'] ?? null;
            if (!is_string($root) || !is_dir($root)) {
                $zip->close();
                return false;
            }

            $this->addDirectoryToZip($zip, $root, basename($root));
        } else {
            foreach ($backup['paths']['files'] ?? [] as $filePath) {
                if (is_string($filePath) && file_exists($filePath)) {
                    $zip->addFile($filePath, 'legacy/' . basename($filePath));
                }
            }

            $zip->addFromString(
                'legacy/manifest.json',
                json_encode($this->buildExportManifest($backup), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }

        $zip->close();

        LogService::info('BackupService', 'Backup exported', ['id' => $id, 'zip' => $zipPath]);

        return [
            'path' => $zipPath,
            'downloadName' => 'infohub-backup-' . date('Y-m-d_H-i-s', $backup['createdTs'] ?? time()) . '.zip'
        ];
    }

    /**
     * Bereitet archiviertes HTML für Preview-Embeds auf.
     */
    public function getPreviewHtml(string $id): string {
        $backup = $this->getBackup($id);
        if ($backup === null) {
            return $this->renderPlaceholderHtml('Backup nicht gefunden');
        }

        $htmlPath = $backup['paths']['html'] ?? null;
        if (!is_string($htmlPath) || !file_exists($htmlPath)) {
            return $this->renderPlaceholderHtml('Für diese Sicherung ist keine HTML-Vorschau verfügbar.');
        }

        $html = file_get_contents($htmlPath);
        if (!is_string($html) || $html === '') {
            return $this->renderPlaceholderHtml('Archivierte HTML-Datei konnte nicht gelesen werden.');
        }

        $html = preg_replace_callback(
            '#/backend/media/[A-Za-z0-9/_\-.]+#',
            function(array $matches) use ($id) {
                return 'backend/api/endpoints.php?action=view_backup_asset&id=' . rawurlencode($id) . '&path=' . rawurlencode($matches[0]);
            },
            $html
        );

        if (stripos($html, '<base ') === false) {
            $html = preg_replace('/<head([^>]*)>/i', '<head$1><base href="../../">', $html, 1, $count);
            if (($count ?? 0) === 0) {
                $html = '<base href="../../">' . $html;
            }
        }

        return $html;
    }

    /**
     * Löst ein Medien-Asset für die Preview auf.
     */
    public function resolvePreviewAsset(string $id, string $requestedPath): ?string {
        $requestedPath = $this->normalizeMediaPath($requestedPath);
        if ($requestedPath === null) {
            return null;
        }

        $backup = $this->getBackup($id);
        if ($backup === null) {
            return null;
        }

        if (($backup['format'] ?? '') === 'package' && !empty($backup['paths']['mediaRoot'])) {
            $relative = substr($requestedPath, strlen('/backend/media/'));
            $candidate = ($backup['paths']['mediaRoot'] ?? '') . '/' . $relative;
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        $livePath = $this->projectRoot . '/' . ltrim($requestedPath, '/');
        return file_exists($livePath) ? $livePath : null;
    }

    private function listPackageBackups(): array {
        $dirs = glob($this->packageDir . '/*', GLOB_ONLYDIR) ?: [];
        $backups = [];

        foreach ($dirs as $dir) {
            $backup = $this->describePackageBackup(str_replace('\\', '/', $dir));
            if ($backup !== null) {
                $backups[] = $backup;
            }
        }

        return $backups;
    }

    private function describePackageBackup(string $snapshotDir): ?array {
        $manifestPath = $snapshotDir . '/manifest.json';
        if (!file_exists($manifestPath)) {
            return null;
        }

        $manifest = $this->readJsonFile($manifestPath);
        if (!is_array($manifest)) {
            return null;
        }

        $htmlRelative = $manifest['files']['html'] ?? null;
        $tilesRelative = $manifest['files']['tiles'] ?? null;
        $settingsRelative = $manifest['files']['settings'] ?? null;
        $mediaRelative = $manifest['files']['media'] ?? [];
        $missingMedia = $manifest['files']['missingMedia'] ?? [];

        $htmlPath = is_string($htmlRelative) ? $snapshotDir . '/' . $htmlRelative : null;
        $tilesPath = is_string($tilesRelative) ? $snapshotDir . '/' . $tilesRelative : null;
        $settingsPath = is_string($settingsRelative) ? $snapshotDir . '/' . $settingsRelative : null;
        $createdTs = (int)($manifest['createdTs'] ?? strtotime($manifest['createdAt'] ?? 'now') ?: filemtime($manifestPath));
        $sizeBytes = (int)($manifest['sizeBytes'] ?? $this->getDirectorySize($snapshotDir));

        $warnings = [];
        if (!empty($missingMedia)) {
            $warnings[] = count($missingMedia) . ' referenzierte Mediendatei(en) konnten beim Sichern nicht mitkopiert werden.';
        }

        return [
            'id' => $manifest['id'] ?? basename($snapshotDir),
            'format' => 'package',
            'formatLabel' => 'Paket',
            'createdAt' => $manifest['createdAt'] ?? date('c', $createdTs),
            'createdTs' => $createdTs,
            'reason' => $manifest['reason'] ?? 'manual',
            'reasonLabel' => $this->mapReasonLabel($manifest['reason'] ?? 'manual'),
            'siteTitle' => $manifest['siteTitle'] ?? '',
            'previewAvailable' => is_string($htmlPath) && file_exists($htmlPath),
            'counts' => [
                'tiles' => (int)($manifest['counts']['tiles'] ?? 0),
                'media' => count(is_array($mediaRelative) ? $mediaRelative : []),
                'files' => (int)($manifest['counts']['files'] ?? 0),
                'missingMedia' => count(is_array($missingMedia) ? $missingMedia : [])
            ],
            'sizeBytes' => $sizeBytes,
            'warnings' => $warnings,
            'paths' => [
                'root' => $snapshotDir,
                'html' => $htmlPath,
                'tiles' => $tilesPath,
                'settings' => $settingsPath,
                'mediaRoot' => $snapshotDir . '/media',
                'files' => array_values(array_filter([
                    $manifestPath,
                    $htmlPath,
                    $tilesPath,
                    $settingsPath
                ]))
            ]
        ];
    }

    private function listLegacyBackups(): array {
        $legacyFiles = [];

        foreach (glob($this->archiveDir . '/index_*.html') ?: [] as $path) {
            $parsed = $this->parseLegacyFile($path, 'html');
            if ($parsed !== null) {
                $legacyFiles[] = $parsed;
            }
        }

        foreach (glob($this->archiveDir . '/tiles_*.json') ?: [] as $path) {
            $parsed = $this->parseLegacyFile($path, 'tiles');
            if ($parsed !== null) {
                $legacyFiles[] = $parsed;
            }
        }

        foreach (glob($this->archiveDir . '/settings_*.json') ?: [] as $path) {
            $parsed = $this->parseLegacyFile($path, 'settings');
            if ($parsed !== null) {
                $legacyFiles[] = $parsed;
            }
        }

        usort($legacyFiles, function(array $left, array $right) {
            return ($right['timestamp'] ?? 0) <=> ($left['timestamp'] ?? 0);
        });

        $groups = [];
        $used = [];

        foreach ($legacyFiles as $index => $file) {
            if (isset($used[$index]) || ($file['kind'] ?? '') !== 'html') {
                continue;
            }

            $used[$index] = true;
            $group = [$file];
            $seenKinds = ['html' => true];

            foreach ($legacyFiles as $candidateIndex => $candidate) {
                if (isset($used[$candidateIndex]) || isset($seenKinds[$candidate['kind'] ?? ''])) {
                    continue;
                }

                if (abs(($candidate['timestamp'] ?? 0) - ($file['timestamp'] ?? 0)) <= self::LEGACY_PAIR_WINDOW) {
                    $used[$candidateIndex] = true;
                    $seenKinds[$candidate['kind']] = true;
                    $group[] = $candidate;
                }
            }

            $groups[] = $group;
        }

        foreach ($legacyFiles as $index => $file) {
            if (!isset($used[$index])) {
                $groups[] = [$file];
            }
        }

        $backups = [];
        foreach ($groups as $group) {
            $backup = $this->describeLegacyBackup($group);
            if ($backup !== null) {
                $backups[] = $backup;
            }
        }

        return $backups;
    }

    private function describeLegacyBackup(array $group): ?array {
        if (empty($group)) {
            return null;
        }

        $paths = [
            'html' => null,
            'tiles' => null,
            'settings' => null,
            'files' => []
        ];

        $createdTs = 0;
        foreach ($group as $file) {
            $kind = $file['kind'] ?? '';
            if (array_key_exists($kind, $paths) && $paths[$kind] === null) {
                $paths[$kind] = $file['path'];
            }
            $paths['files'][] = $file['path'];
            $createdTs = max($createdTs, (int)($file['timestamp'] ?? 0));
        }

        $tiles = is_string($paths['tiles']) && file_exists($paths['tiles']) ? $this->readJsonFile($paths['tiles']) : [];
        $settings = is_string($paths['settings']) && file_exists($paths['settings']) ? $this->readJsonFile($paths['settings']) : [];
        $html = is_string($paths['html']) && file_exists($paths['html']) ? file_get_contents($paths['html']) : '';
        $html = is_string($html) ? $html : '';
        $mediaPaths = $this->collectMediaPaths($tiles, $settings, $html);
        $sizeBytes = 0;
        foreach ($paths['files'] as $filePath) {
            if (file_exists($filePath)) {
                $sizeBytes += (int)filesize($filePath);
            }
        }

        $warnings = ['Legacy-Backup ohne vollständig gepackte Settings- und Mediendateien.'];
        $siteTitle = is_array($settings) ? ($settings['site']['title'] ?? '') : '';

        return [
            'id' => 'legacy_' . substr(sha1(implode('|', $paths['files'])), 0, 12),
            'format' => 'legacy',
            'formatLabel' => 'Legacy',
            'createdAt' => date('c', $createdTs),
            'createdTs' => $createdTs,
            'reason' => 'legacy',
            'reasonLabel' => 'Legacy-Archiv',
            'siteTitle' => $siteTitle,
            'previewAvailable' => is_string($paths['html']) && file_exists($paths['html']),
            'counts' => [
                'tiles' => is_array($tiles) ? count($tiles) : 0,
                'media' => count($mediaPaths),
                'files' => count($paths['files']),
                'missingMedia' => 0
            ],
            'sizeBytes' => $sizeBytes,
            'warnings' => $warnings,
            'paths' => $paths
        ];
    }

    private function parseLegacyFile(string $path, string $kind): ?array {
        $filename = basename($path);
        if (!preg_match('/_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.(html|json)$/', $filename, $matches)) {
            return null;
        }

        $timestamp = DateTime::createFromFormat('Y-m-d_H-i-s', $matches[1]);
        if ($timestamp instanceof DateTime) {
            $timestamp = $timestamp->getTimestamp();
        } else {
            $timestamp = filemtime($path);
        }

        return [
            'path' => str_replace('\\', '/', $path),
            'kind' => $kind,
            'timestamp' => (int)$timestamp,
            'filename' => $filename
        ];
    }

    private function mapReasonLabel(string $reason): string {
        return match ($reason) {
            'publish' => 'Vor Veröffentlichung',
            'pre-restore' => 'Sicherungsnetz vor Restore',
            'manual' => 'Manuell',
            'legacy' => 'Legacy-Archiv',
            'test' => 'Smoke-Test',
            default => ucfirst(str_replace('-', ' ', $reason))
        };
    }

    private function normalizeRestoreMode(string $mode): ?string {
        $mode = strtolower(trim($mode));
        return in_array($mode, self::RESTORE_MODES, true) ? $mode : null;
    }

    private function mapRestoreModeLabel(string $mode): string {
        return match ($mode) {
            'editor' => 'Editor',
            'site' => 'Website',
            'all' => 'Beides',
            default => ucfirst($mode)
        };
    }

    private function collectMediaPaths(array $tiles, array $settings, string $html): array {
        $paths = [];
        $this->collectMediaPathsFromValue($tiles, $paths);
        $this->collectMediaPathsFromValue($settings, $paths);
        $this->collectMediaPathsFromValue($html, $paths);

        ksort($paths);
        return array_keys($paths);
    }

    private function collectMediaPathsFromValue(mixed $value, array &$paths): void {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->collectMediaPathsFromValue($item, $paths);
            }
            return;
        }

        if (!is_string($value) || $value === '') {
            return;
        }

        if (preg_match_all('#/backend/media/[A-Za-z0-9/_\-.]+#', $value, $matches)) {
            foreach ($matches[0] as $match) {
                $normalized = $this->normalizeMediaPath($match);
                if ($normalized !== null) {
                    $paths[$normalized] = true;
                }
            }
        }
    }

    private function normalizeMediaPath(string $path): ?string {
        $path = trim(str_replace('\\', '/', $path));
        if (!preg_match('#^/backend/media/[A-Za-z0-9/_\-.]+$#', $path)) {
            return null;
        }

        return str_contains($path, '..') ? null : $path;
    }

    private function copyMediaIntoSnapshot(string $snapshotDir, string $mediaPath): ?string {
        $relative = substr($mediaPath, strlen('/backend/media/'));
        $source = $this->projectRoot . '/' . ltrim($mediaPath, '/');
        $destination = $snapshotDir . '/media/' . $relative;

        if (!file_exists($source)) {
            return null;
        }

        $this->copyFile($source, $destination);
        return 'media/' . $relative;
    }

    private function restoreMediaDirectory(string $mediaRoot): int {
        $count = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($mediaRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($mediaRoot) + 1));
            $target = $this->backendRoot . '/media/' . $relative;
            $this->copyFile($item->getPathname(), $target);
            $count++;
        }

        return $count;
    }

    private function copyFile(string $source, string $target): void {
        $this->ensureDirectory(dirname($target));
        $temp = $target . '.tmp.' . getmypid();

        if (!copy($source, $temp)) {
            throw new RuntimeException('Konnte Datei nicht kopieren: ' . basename($source));
        }

        if (!rename($temp, $target)) {
            @unlink($temp);
            throw new RuntimeException('Konnte Datei nicht finalisieren: ' . basename($target));
        }
    }

    private function writeJsonFile(string $path, array $data): void {
        $this->ensureDirectory(dirname($path));
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Manifest konnte nicht serialisiert werden');
        }

        file_put_contents($path, $json);
    }

    private function readJsonFile(string $path): array {
        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        $decoded = json_decode((string)$content, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function ensureDirectory(string $path): void {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    private function getDirectorySize(string $directory): int {
        if (!is_dir($directory)) {
            return 0;
        }

        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $size += (int)$item->getSize();
            }
        }

        return $size;
    }

    private function deleteDirectory(string $directory): void {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($directory);
    }

    private function addDirectoryToZip(ZipArchive $zip, string $directory, string $prefix): void {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($directory) + 1));
            $localName = trim($prefix . '/' . $relative, '/');

            if ($item->isDir()) {
                $zip->addEmptyDir($localName);
            } else {
                $zip->addFile($item->getPathname(), $localName);
            }
        }
    }

    private function buildExportManifest(array $backup): array {
        return [
            'id' => $backup['id'] ?? '',
            'format' => $backup['format'] ?? '',
            'createdAt' => $backup['createdAt'] ?? '',
            'reason' => $backup['reason'] ?? '',
            'siteTitle' => $backup['siteTitle'] ?? '',
            'counts' => $backup['counts'] ?? [],
            'warnings' => $backup['warnings'] ?? []
        ];
    }

    private function renderPlaceholderHtml(string $message): string {
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{margin:0;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#0f172a;color:#e2e8f0;display:grid;place-items:center;min-height:100vh;padding:24px;text-align:center}div{max-width:460px;padding:24px;border:1px solid rgba(148,163,184,.2);border-radius:16px;background:rgba(15,23,42,.92)}</style></head><body><div><h1 style="margin-top:0;font-size:24px;">Keine HTML-Vorschau</h1><p>' . $safeMessage . '</p></div></body></html>';
    }
}