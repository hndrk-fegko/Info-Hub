<?php
/**
 * Backup-Verwaltung.
 *
 * Zeigt Paket- und Legacy-Backups als Karten mit Vorschau, Export,
 * Restore und Delete an.
 */

$bootstrapMode = 'page';
$bootstrapServices = [
    'AuthService',
    'BackupService',
];
$bootstrapMissingConfigRedirect = 'setup.php';
$bootstrap = require __DIR__ . '/bootstrap.php';

$container = $bootstrap['container'];

$auth = $container->authService();
if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$backupService = $container->backupService();
$backups = $backupService->listBackups();
$packageCount = count(array_filter($backups, fn($backup) => ($backup['format'] ?? '') === 'package'));
$legacyCount = count($backups) - $packageCount;
$showTypeSummary = $packageCount > 0 && $legacyCount > 0;

$backupCssVersion = file_exists(__DIR__ . '/../assets/css/backup.css') ? (string) filemtime(__DIR__ . '/../assets/css/backup.css') : '';
$backupJsVersion = file_exists(__DIR__ . '/../assets/js/backup.js') ? (string) filemtime(__DIR__ . '/../assets/js/backup.js') : '';
$backupCssHref = '../assets/css/backup.css' . ($backupCssVersion !== '' ? '?v=' . rawurlencode($backupCssVersion) : '');
$backupJsHref = '../assets/js/backup.js' . ($backupJsVersion !== '' ? '?v=' . rawurlencode($backupJsVersion) : '');

function backupFormatBytes(int $bytes): string {
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    $units = ['KB', 'MB', 'GB'];
    $value = $bytes / 1024;
    foreach ($units as $unit) {
        if ($value < 1024 || $unit === 'GB') {
            return number_format($value, $value >= 100 ? 0 : 1, ',', '.') . ' ' . $unit;
        }
        $value /= 1024;
    }

    return $bytes . ' B';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup-Verwaltung - Info-Hub</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($backupCssHref, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <main class="backup-shell">
        <section class="backup-hero">
            <div class="backup-hero__panel">
                <div class="backup-hero__top">
                    <div>
                        <span class="backup-kicker">Archiv und Restore</span>
                        <h1>Backup-Verwaltung</h1>
                        <p>Neue Sicherungen werden als Paket mit veröffentlichter HTML, Datenbasis und referenzierten Mediendateien abgelegt. Bestehende Legacy-Sicherungen bleiben sichtbar und lassen sich für Vorschau, Export und best-effort-Restore weiter nutzen.</p>
                    </div>
                    <nav class="backup-nav">
                        <a class="backup-btn backup-btn--secondary" href="v2/editor.php">✏️ Editor</a>
                        <!-- LEGACY_CLASSIC_EDITOR: Link bleibt fuer den Wartungsmodus erreichbar. -->
                        <a class="backup-btn backup-btn--secondary" href="editor.php" data-legacy-classic-link="LEGACY_CLASSIC_EDITOR" title="Zum klassischen Editor (Legacy, nur wenn noetig)">📝 Classic Legacy</a>
                        <a class="backup-btn backup-btn--secondary" href="../index.html" target="_blank" rel="noopener">🌐 Seite</a>
                    </nav>
                </div>
                <div class="backup-summary <?= $showTypeSummary ? 'backup-summary--with-types' : 'backup-summary--compact' ?>">
                    <div class="backup-summary__card">
                        <span class="backup-summary__label">Sicherungen gesamt</span>
                        <strong class="backup-summary__value"><?= count($backups) ?></strong>
                    </div>
                    <?php if ($showTypeSummary): ?>
                        <div class="backup-summary__card">
                            <span class="backup-summary__label">Paket-Backups</span>
                            <strong class="backup-summary__value"><?= $packageCount ?></strong>
                        </div>
                        <div class="backup-summary__card">
                            <span class="backup-summary__label">Legacy-Einträge</span>
                            <strong class="backup-summary__value"><?= $legacyCount ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="backup-summary__card backup-summary__card--hint">
                        <span class="backup-summary__label">Hinweis</span>
                        <strong class="backup-summary__value">Beim Wiederherstellen eines Backups wird der aktuelle Stand zuerst automatisch gesichert.</strong>
                    </div>
                </div>
            </div>
        </section>

        <?php if (empty($backups)): ?>
            <section class="backup-empty">
                Noch keine Sicherungen gefunden. Nach der nächsten Veröffentlichung taucht hier automatisch das erste Paket-Backup auf.
            </section>
        <?php else: ?>
            <section class="backup-grid">
                <?php foreach ($backups as $backup): ?>
                    <?php
                    $backupId = $backup['id'];
                    $previewUrl = 'api/endpoints.php?action=view_backup_html&id=' . rawurlencode($backupId);
                    $exportUrl = 'api/endpoints.php?action=export_backup&id=' . rawurlencode($backupId);
                    $createdLabel = date('d.m.Y H:i', (int) $backup['createdTs']);
                    $siteTitle = trim((string) ($backup['siteTitle'] ?? ''));
                    $siteLabel = $siteTitle !== '' ? $siteTitle : 'Info-Hub Sicherung';
                    $mediaLabel = ($backup['format'] ?? '') === 'package'
                        ? ($backup['counts']['media'] ?? 0) . ' Medien gesichert'
                        : ($backup['counts']['media'] ?? 0) . ' Medien referenziert';
                    ?>
                    <article
                        class="backup-card"
                        tabindex="0"
                        data-backup-card
                        data-backup-id="<?= htmlspecialchars($backupId, ENT_QUOTES, 'UTF-8') ?>"
                        data-backup-title="<?= htmlspecialchars($createdLabel . ' · ' . $siteLabel, ENT_QUOTES, 'UTF-8') ?>"
                        data-preview-url="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>"
                        data-export-url="<?= htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <div class="backup-card__preview">
                            <?php if (!empty($backup['previewAvailable'])): ?>
                                <iframe src="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>" title="Vorschau <?= htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8') ?>" loading="lazy"></iframe>
                            <?php else: ?>
                                <div class="backup-card__preview-empty">Keine HTML-Vorschau in dieser Sicherung verfügbar.</div>
                            <?php endif; ?>
                        </div>

                        <div class="backup-card__content">
                            <div class="backup-card__head">
                                <div>
                                    <div class="backup-card__eyebrow">
                                        <span class="backup-badge backup-badge--<?= htmlspecialchars($backup['format'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($backup['formatLabel'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="backup-card__reason"><?= htmlspecialchars($backup['reasonLabel'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <h2><?= htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8') ?></h2>
                                    <p class="backup-card__subtitle"><?= htmlspecialchars($siteLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                </div>

                                <details class="backup-menu" data-stop-open>
                                    <summary class="backup-menu__toggle" aria-label="Aktionen öffnen">⋯</summary>
                                    <div class="backup-menu__panel">
                                        <button type="button" data-backup-action="restore" data-restore-mode="editor" data-backup-id="<?= htmlspecialchars($backupId, ENT_QUOTES, 'UTF-8') ?>">Im Editor übernehmen</button>
                                        <button type="button" data-backup-action="restore" data-restore-mode="site" data-backup-id="<?= htmlspecialchars($backupId, ENT_QUOTES, 'UTF-8') ?>">Website wiederherstellen</button>
                                        <button type="button" data-backup-action="restore" data-restore-mode="all" data-backup-id="<?= htmlspecialchars($backupId, ENT_QUOTES, 'UTF-8') ?>">Beides wiederherstellen</button>
                                        <a href="<?= htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') ?>" data-stop-open>Export</a>
                                        <button type="button" class="is-danger" data-backup-action="delete" data-backup-id="<?= htmlspecialchars($backupId, ENT_QUOTES, 'UTF-8') ?>">Löschen</button>
                                    </div>
                                </details>
                            </div>

                            <div class="backup-meta-grid">
                                <div class="backup-meta">
                                    <span class="backup-meta__label">Tiles</span>
                                    <strong class="backup-meta__value"><?= (int) ($backup['counts']['tiles'] ?? 0) ?></strong>
                                </div>
                                <div class="backup-meta">
                                    <span class="backup-meta__label">Medien</span>
                                    <strong class="backup-meta__value"><?= htmlspecialchars($mediaLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>
                                <div class="backup-meta">
                                    <span class="backup-meta__label">Dateien</span>
                                    <strong class="backup-meta__value"><?= (int) ($backup['counts']['files'] ?? 0) ?></strong>
                                </div>
                                <div class="backup-meta">
                                    <span class="backup-meta__label">Größe</span>
                                    <strong class="backup-meta__value"><?= htmlspecialchars(backupFormatBytes((int) ($backup['sizeBytes'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>
                            </div>

                            <?php if (!empty($backup['warnings'])): ?>
                                <p class="backup-card__warning"><?= htmlspecialchars(implode(' ', $backup['warnings']), ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <div class="backup-notice" id="backupNotice" hidden></div>

    <div class="backup-modal" id="backupModal" hidden>
        <div class="backup-modal__backdrop" data-close-modal></div>
        <div class="backup-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="backupModalTitle">
            <div class="backup-modal__header">
                <div>
                    <span class="backup-modal__eyebrow">Archivierte HTML</span>
                    <h2 id="backupModalTitle">Sicherung</h2>
                </div>
                <div class="backup-modal__actions">
                    <div class="backup-modal__restore-group">
                        <button type="button" class="backup-btn backup-btn--secondary" id="backupModalRestoreEditor">
                            <span class="backup-btn__icon">📝</span>
                            <span class="backup-btn__label">Editor</span>
                        </button>
                        <button type="button" class="backup-btn backup-btn--secondary" id="backupModalRestoreSite">
                            <span class="backup-btn__icon">🌐</span>
                            <span class="backup-btn__label">Website</span>
                        </button>
                        <button type="button" class="backup-btn backup-btn--primary" id="backupModalRestoreAll">
                            <span class="backup-btn__icon">♺</span>
                            <span class="backup-btn__label">Beides</span>
                        </button>
                    </div>
                    <button type="button" class="backup-btn backup-btn--danger" id="backupModalDelete">
                        <span class="backup-btn__icon">🗑️</span>
                        <span class="backup-btn__label">Löschen</span>
                    </button>
                    <a class="backup-btn backup-btn--secondary" id="backupModalExport" href="#">
                        <span class="backup-btn__icon">⇩</span>
                        <span class="backup-btn__label">Export</span>
                    </a>
                    <button type="button" class="backup-btn backup-btn--secondary backup-btn--icon" data-close-modal aria-label="Modal schließen">×</button>
                </div>
            </div>
            <div class="backup-modal__body">
                <iframe id="backupModalFrame" title="Backup Vorschau"></iframe>
            </div>
        </div>
    </div>

    <script>
        window.BACKUP_CONFIG = {
            apiUrl: 'api/endpoints.php',
            csrfToken: '<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>'
        };
    </script>
    <script src="<?= htmlspecialchars($backupJsHref, ENT_QUOTES, 'UTF-8') ?>" defer></script>
</body>
</html>
