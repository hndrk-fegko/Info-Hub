<?php

class BackupApiActions implements ApiActionGroupInterface {

    public function register(ApiContext $context, ApiResponder $responder): array {
        return [
            'list_backups' => static function() use ($context, $responder): void {
                $responder->success(['backups' => $context->backupService()->listBackups()]);
            },

            'view_backup_html' => static function() use ($context): void {
                $id = $context->requireStringParam([
                    $_GET['id'] ?? null,
                ], 'Backup-ID erforderlich');

                header_remove('Content-Type');
                header('Content-Type: text/html; charset=utf-8');
                echo $context->backupService()->getPreviewHtml($id);
            },

            'view_backup_asset' => static function() use ($context): void {
                $id = $context->requireStringParam([
                    $_GET['id'] ?? null,
                ], 'Backup-ID und Asset-Pfad erforderlich');
                $path = $context->requireStringParam([
                    $_GET['path'] ?? null,
                ], 'Backup-ID und Asset-Pfad erforderlich');

                $assetPath = $context->backupService()->resolvePreviewAsset($id, $path);
                if ($assetPath === null || !file_exists($assetPath)) {
                    http_response_code(404);
                    header_remove('Content-Type');
                    header('Content-Type: text/plain; charset=utf-8');
                    echo 'Asset nicht gefunden';
                    return;
                }

                header_remove('Content-Type');
                header('Content-Type: ' . (mime_content_type($assetPath) ?: 'application/octet-stream'));
                header('Content-Length: ' . filesize($assetPath));
                readfile($assetPath);
            },

            'export_backup' => static function() use ($context, $responder): void {
                $id = $context->requireStringParam([
                    $_GET['id'] ?? null,
                ], 'Backup-ID erforderlich');

                $export = $context->backupService()->createExportArchive($id);
                if ($export === false || !file_exists($export['path'])) {
                    $responder->json(['success' => false, 'error' => 'Export fehlgeschlagen'], 500);
                    return;
                }

                header_remove('Content-Type');
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . basename($export['downloadName']) . '"');
                header('Content-Length: ' . filesize($export['path']));
                readfile($export['path']);
                @unlink($export['path']);
            },

            'restore_backup' => static function() use ($context, $responder): void {
                $id = $context->requireStringParam([
                    $_POST['id'] ?? null,
                ], 'Backup-ID erforderlich');
                $mode = $_POST['mode'] ?? 'all';

                $responder->result($context->backupService()->restoreBackup($id, $mode));
            },

            'quick_restore_last_publish' => static function() use ($context, $responder): void {
                $responder->result($context->backupService()->quickRestoreLastPublish());
            },

            'delete_backup' => static function() use ($context, $responder): void {
                $id = $context->requireStringParam([
                    $_POST['id'] ?? null,
                ], 'Backup-ID erforderlich');

                $responder->result($context->backupService()->deleteBackup($id));
            },

            'generate' => static function() use ($context, $responder): void {
                $result = $context->backupService()->publishCurrentState($context->generatorService());
                $responder->result($result, 500);
            },
        ];
    }
}