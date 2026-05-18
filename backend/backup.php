<?php
/**
 * Backup-Verwaltung.
 *
 * Zeigt Paket- und Legacy-Backups als Karten mit Vorschau, Export,
 * Restore und Delete an.
 */

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    header('Location: setup.php');
    exit;
}

require_once __DIR__ . '/core/AuthService.php';
require_once __DIR__ . '/core/BackupService.php';

$auth = new AuthService();
if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$backupService = new BackupService();
$backups = $backupService->listBackups();
$packageCount = count(array_filter($backups, fn($backup) => ($backup['format'] ?? '') === 'package'));
$legacyCount = count($backups) - $packageCount;
$showTypeSummary = $packageCount > 0 && $legacyCount > 0;

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
    <style>
        :root {
            --backup-bg: #081120;
            --backup-bg-2: #0f172a;
            --backup-panel: rgba(15, 23, 42, 0.86);
            --backup-panel-strong: rgba(15, 23, 42, 0.96);
            --backup-border: rgba(148, 163, 184, 0.18);
            --backup-text: #e2e8f0;
            --backup-text-muted: #94a3b8;
            --backup-accent: #38bdf8;
            --backup-warning: #f59e0b;
            --backup-shadow: 0 24px 80px rgba(2, 6, 23, 0.48);
            --backup-radius: 18px;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(56, 189, 248, 0.16), transparent 34%),
                radial-gradient(circle at top right, rgba(34, 197, 94, 0.14), transparent 28%),
                linear-gradient(180deg, var(--backup-bg) 0%, var(--backup-bg-2) 100%);
            color: var(--backup-text);
        }

        a {
            color: inherit;
        }

        .backup-shell {
            width: min(1360px, calc(100vw - 32px));
            margin: 0 auto;
            padding: 28px 0 48px;
        }

        .backup-hero {
            display: grid;
            gap: 18px;
            margin-bottom: 28px;
        }

        .backup-hero__panel {
            padding: 28px;
            border: 1px solid var(--backup-border);
            border-radius: 26px;
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.9), rgba(8, 17, 32, 0.92));
            box-shadow: var(--backup-shadow);
        }

        .backup-hero__top {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .backup-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(56, 189, 248, 0.14);
            color: #7dd3fc;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .backup-hero h1 {
            margin: 14px 0 12px;
            font-size: clamp(2rem, 4vw, 3.4rem);
            line-height: 1.02;
        }

        .backup-hero p {
            margin: 0;
            max-width: 760px;
            color: var(--backup-text-muted);
            font-size: 15px;
            line-height: 1.65;
        }

        .backup-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .backup-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 38px;
            padding: 8px 14px;
            border-radius: 10px;
            border: 1px solid transparent;
            background: rgba(30, 41, 59, 0.92);
            color: var(--backup-text);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.16s ease, border-color 0.16s ease, transform 0.16s ease;
        }

        .backup-btn__icon {
            font-size: 14px;
            line-height: 1;
        }

        .backup-btn__label {
            line-height: 1;
        }

        .backup-btn:hover {
            transform: translateY(-1px);
        }

        .backup-btn--secondary {
            border-color: var(--backup-border);
        }

        .backup-btn--secondary:hover {
            background: rgba(51, 65, 85, 0.98);
            border-color: rgba(148, 163, 184, 0.32);
        }

        .backup-btn--primary {
            background: linear-gradient(135deg, #0ea5e9, #2563eb);
            color: white;
        }

        .backup-btn--primary:hover {
            background: linear-gradient(135deg, #0284c7, #1d4ed8);
        }

        .backup-btn--danger {
            background: rgba(127, 29, 29, 0.9);
            color: #ffe4e6;
        }

        .backup-btn--danger:hover {
            background: rgba(153, 27, 27, 0.98);
        }

        .backup-btn--icon {
            width: 40px;
            min-width: 40px;
            padding: 0;
        }

        .backup-summary {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 24px;
        }

        .backup-summary--with-types {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .backup-summary--with-types .backup-summary__card--hint {
            grid-column: span 2;
        }

        .backup-summary--compact {
            grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
        }

        .backup-summary__card {
            min-width: 0;
            padding: 16px 18px;
            border-radius: 16px;
            border: 1px solid var(--backup-border);
            background: rgba(15, 23, 42, 0.52);
        }

        .backup-summary__card--hint {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.16), rgba(37, 99, 235, 0.22));
            border-color: rgba(56, 189, 248, 0.28);
        }

        .backup-summary__value {
            display: block;
            margin-top: 8px;
            font-size: 30px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .backup-summary__card--hint .backup-summary__value {
            font-size: 15px;
            line-height: 1.45;
            font-weight: 600;
            margin-top: 10px;
            color: #dbeafe;
            max-width: 100%;
        }

        .backup-summary__label {
            color: var(--backup-text-muted);
            font-size: 13px;
        }

        .backup-summary__card--hint .backup-summary__label {
            color: #bae6fd;
        }

        .backup-grid {
            display: grid;
            gap: 18px;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        }

        .backup-card {
            position: relative;
            display: grid;
            gap: 0;
            border-radius: var(--backup-radius);
            border: 1px solid var(--backup-border);
            background: var(--backup-panel);
            overflow: hidden;
            box-shadow: 0 16px 42px rgba(2, 6, 23, 0.26);
            cursor: pointer;
            transition: transform 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease;
        }

        .backup-card:hover,
        .backup-card:focus-visible {
            transform: translateY(-2px);
            border-color: rgba(56, 189, 248, 0.34);
            box-shadow: 0 22px 56px rgba(2, 6, 23, 0.36);
            outline: none;
        }

        .backup-card__preview {
            position: relative;
            height: 208px;
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.55), rgba(8, 17, 32, 0.98));
            overflow: hidden;
            border-bottom: 1px solid rgba(148, 163, 184, 0.14);
        }

        .backup-card__preview iframe {
            width: 400%;
            height: 400%;
            border: 0;
            transform-origin: top left;
            transform: scale(0.25);
            pointer-events: none;
            background: white;
        }

        .backup-card__preview-empty {
            display: grid;
            place-items: center;
            height: 100%;
            padding: 20px;
            color: var(--backup-text-muted);
            text-align: center;
            font-size: 14px;
        }

        .backup-card__content {
            display: grid;
            gap: 14px;
            padding: 18px;
        }

        .backup-card__head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
        }

        .backup-card__eyebrow {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 10px;
        }

        .backup-badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .backup-badge--package {
            background: rgba(34, 197, 94, 0.16);
            color: #86efac;
        }

        .backup-badge--legacy {
            background: rgba(245, 158, 11, 0.16);
            color: #fcd34d;
        }

        .backup-card__reason {
            color: var(--backup-text-muted);
            font-size: 12px;
        }

        .backup-card h2 {
            margin: 0;
            font-size: 20px;
            line-height: 1.15;
        }

        .backup-card__subtitle {
            margin: 6px 0 0;
            color: var(--backup-text-muted);
            font-size: 13px;
        }

        .backup-meta-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .backup-meta {
            padding: 12px 13px;
            border-radius: 12px;
            background: rgba(15, 23, 42, 0.52);
            border: 1px solid rgba(148, 163, 184, 0.12);
        }

        .backup-meta__label {
            display: block;
            color: var(--backup-text-muted);
            font-size: 12px;
        }

        .backup-meta__value {
            display: block;
            margin-top: 5px;
            font-size: 14px;
            font-weight: 600;
        }

        .backup-card__warning {
            margin: 0;
            padding: 11px 12px;
            border-radius: 12px;
            background: rgba(120, 53, 15, 0.22);
            border: 1px solid rgba(245, 158, 11, 0.22);
            color: #fde68a;
            font-size: 12px;
            line-height: 1.55;
        }

        .backup-menu {
            position: relative;
            flex: 0 0 auto;
        }

        .backup-menu__toggle {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(30, 41, 59, 0.85);
            cursor: pointer;
            list-style: none;
            user-select: none;
        }

        .backup-menu__toggle::-webkit-details-marker {
            display: none;
        }

        .backup-menu__toggle::marker {
            content: '';
        }

        .backup-menu__panel {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            z-index: 10;
            display: grid;
            min-width: 170px;
            padding: 8px;
            border-radius: 14px;
            border: 1px solid var(--backup-border);
            background: var(--backup-panel-strong);
            box-shadow: 0 18px 44px rgba(2, 6, 23, 0.42);
        }

        .backup-menu__panel button,
        .backup-menu__panel a {
            display: flex;
            align-items: center;
            width: 100%;
            min-height: 36px;
            padding: 8px 10px;
            border: 0;
            border-radius: 10px;
            background: transparent;
            color: var(--backup-text);
            text-decoration: none;
            font-size: 13px;
            text-align: left;
            cursor: pointer;
        }

        .backup-menu__panel button:hover,
        .backup-menu__panel a:hover {
            background: rgba(30, 41, 59, 0.92);
        }

        .backup-menu__panel .is-danger {
            color: #fecdd3;
        }

        .backup-empty {
            padding: 28px;
            border-radius: 24px;
            border: 1px dashed rgba(148, 163, 184, 0.28);
            background: rgba(15, 23, 42, 0.5);
            color: var(--backup-text-muted);
            text-align: center;
        }

        .backup-notice {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 1200;
            max-width: min(460px, calc(100vw - 36px));
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid var(--backup-border);
            background: rgba(15, 23, 42, 0.96);
            box-shadow: 0 20px 44px rgba(2, 6, 23, 0.4);
            font-size: 13px;
            line-height: 1.55;
        }

        .backup-notice--success {
            border-color: rgba(34, 197, 94, 0.3);
            color: #bbf7d0;
        }

        .backup-notice--error {
            border-color: rgba(251, 113, 133, 0.32);
            color: #fecdd3;
        }

        .backup-modal[hidden] {
            display: none;
        }

        .backup-modal {
            position: fixed;
            inset: 0;
            z-index: 1100;
        }

        .backup-modal__backdrop {
            position: absolute;
            inset: 0;
            background: rgba(2, 6, 23, 0.76);
            backdrop-filter: blur(10px);
        }

        .backup-modal__dialog {
            position: relative;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            width: min(1320px, calc(100vw - 32px));
            height: min(88vh, 940px);
            margin: 28px auto;
            border-radius: 24px;
            border: 1px solid var(--backup-border);
            background: rgba(8, 17, 32, 0.98);
            box-shadow: 0 28px 96px rgba(2, 6, 23, 0.58);
            overflow: hidden;
        }

        .backup-modal__header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            padding: 18px 20px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.14);
            background: rgba(15, 23, 42, 0.88);
        }

        .backup-modal__eyebrow {
            display: block;
            color: var(--backup-text-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .backup-modal__header h2 {
            margin: 8px 0 0;
            font-size: 24px;
            line-height: 1.15;
        }

        .backup-modal__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        .backup-modal__restore-group {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            padding: 6px;
            border-radius: 14px;
            border: 1px solid rgba(56, 189, 248, 0.18);
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.12), rgba(15, 23, 42, 0.82));
            box-shadow: inset 0 1px 0 rgba(125, 211, 252, 0.08);
        }

        .backup-modal__restore-group .backup-btn {
            min-height: 36px;
        }

        .backup-modal__body {
            min-height: 0;
            background: white;
        }

        .backup-modal__body iframe {
            width: 100%;
            height: 100%;
            border: 0;
        }

        @media (max-width: 900px) {
            .backup-shell {
                width: min(100vw, calc(100vw - 24px));
            }

            .backup-hero__panel {
                padding: 22px;
            }

            .backup-summary--with-types {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .backup-summary--with-types .backup-summary__card--hint,
            .backup-summary--compact .backup-summary__card--hint {
                grid-column: 1 / -1;
            }

            .backup-card__preview {
                height: 180px;
            }

            .backup-modal__dialog {
                width: calc(100vw - 20px);
                height: calc(100vh - 20px);
                margin: 10px auto;
            }
        }

        @media (max-width: 640px) {
            .backup-shell {
                width: min(100vw, calc(100vw - 16px));
                padding-top: 16px;
            }

            .backup-hero__panel {
                padding: 18px;
                border-radius: 22px;
            }

            .backup-summary {
                grid-template-columns: 1fr;
            }

            .backup-summary__card--hint {
                grid-column: auto;
            }

            .backup-grid {
                grid-template-columns: 1fr;
            }

            .backup-meta-grid {
                grid-template-columns: 1fr 1fr;
            }

            .backup-modal__header {
                padding: 16px;
            }

            .backup-modal__header h2 {
                font-size: 20px;
            }

            .backup-modal__actions {
                width: 100%;
                justify-content: flex-start;
            }

            .backup-modal__restore-group {
                width: 100%;
            }
        }
    </style>
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
                        <a class="backup-btn backup-btn--secondary" href="editor.php">📝 Classic</a>
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
                    $createdLabel = date('d.m.Y H:i', (int)$backup['createdTs']);
                    $siteTitle = trim((string)($backup['siteTitle'] ?? ''));
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
                                    <summary class="backup-menu__toggle" onclick="event.stopPropagation()" aria-label="Aktionen öffnen">⋯</summary>
                                    <div class="backup-menu__panel" onclick="event.stopPropagation()">
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
                                    <strong class="backup-meta__value"><?= (int)($backup['counts']['tiles'] ?? 0) ?></strong>
                                </div>
                                <div class="backup-meta">
                                    <span class="backup-meta__label">Medien</span>
                                    <strong class="backup-meta__value"><?= htmlspecialchars($mediaLabel, ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>
                                <div class="backup-meta">
                                    <span class="backup-meta__label">Dateien</span>
                                    <strong class="backup-meta__value"><?= (int)($backup['counts']['files'] ?? 0) ?></strong>
                                </div>
                                <div class="backup-meta">
                                    <span class="backup-meta__label">Größe</span>
                                    <strong class="backup-meta__value"><?= htmlspecialchars(backupFormatBytes((int)($backup['sizeBytes'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></strong>
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

        (function() {
            const modal = document.getElementById('backupModal');
            const modalFrame = document.getElementById('backupModalFrame');
            const modalTitle = document.getElementById('backupModalTitle');
            const modalExport = document.getElementById('backupModalExport');
            const modalRestoreEditor = document.getElementById('backupModalRestoreEditor');
            const modalRestoreSite = document.getElementById('backupModalRestoreSite');
            const modalRestoreAll = document.getElementById('backupModalRestoreAll');
            const modalDelete = document.getElementById('backupModalDelete');
            const notice = document.getElementById('backupNotice');

            let currentBackup = null;

            function showNotice(message, type) {
                notice.hidden = false;
                notice.className = 'backup-notice backup-notice--' + type;
                notice.textContent = message;
                window.clearTimeout(showNotice.timeoutId);
                showNotice.timeoutId = window.setTimeout(() => {
                    notice.hidden = true;
                }, 5200);
            }

            function closeMenus() {
                document.querySelectorAll('.backup-menu[open]').forEach((menu) => {
                    menu.removeAttribute('open');
                });
            }

            function closeModal() {
                modal.hidden = true;
                modalFrame.src = 'about:blank';
                currentBackup = null;
            }

            function openModal(card) {
                currentBackup = {
                    id: card.dataset.backupId,
                    title: card.dataset.backupTitle,
                    previewUrl: card.dataset.previewUrl,
                    exportUrl: card.dataset.exportUrl
                };

                modalTitle.textContent = currentBackup.title;
                modalFrame.src = currentBackup.previewUrl;
                modalExport.href = currentBackup.exportUrl;
                modal.hidden = false;
            }

            function getRestoreModeMeta(mode) {
                switch (mode) {
                    case 'editor':
                        return {
                            label: 'Editor',
                            confirmText: 'Aktueller Stand wird zuerst als neues Paket-Backup gesichert. Diese Sicherung danach nur in den Editor übernehmen? Die veröffentlichte Website bleibt unverändert.'
                        };
                    case 'site':
                        return {
                            label: 'Website',
                            confirmText: 'Aktueller Stand wird zuerst als neues Paket-Backup gesichert. Diese Sicherung danach nur als veröffentlichte Website wiederherstellen? Der Editor-Stand bleibt unverändert.'
                        };
                    default:
                        return {
                            label: 'Beides',
                            confirmText: 'Aktueller Stand wird zuerst als neues Paket-Backup gesichert. Diese Sicherung danach für Editor und veröffentlichte Website wiederherstellen?'
                        };
                }
            }

            async function runAction(action, backupId, restoreMode = 'all') {
                const restoreMeta = getRestoreModeMeta(restoreMode);
                const confirmText = action === 'restore'
                    ? restoreMeta.confirmText
                    : 'Diese Sicherung wirklich löschen?';

                if (!window.confirm(confirmText)) {
                    return;
                }

                const formData = new FormData();
                formData.append('action', action === 'restore' ? 'restore_backup' : 'delete_backup');
                formData.append('id', backupId);
                if (action === 'restore') {
                    formData.append('mode', restoreMode);
                }
                formData.append('csrf_token', window.BACKUP_CONFIG.csrfToken);

                try {
                    const response = await fetch(window.BACKUP_CONFIG.apiUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });

                    const result = await response.json();
                    if (!response.ok || !result.success) {
                        throw new Error(result.error || 'Aktion fehlgeschlagen');
                    }

                    if (action === 'restore') {
                        const safetyInfo = result.safetyBackupId ? ' Sicherheitskopie: ' + result.safetyBackupId + '.' : '';
                        const modeLabel = result.modeLabel || restoreMeta.label;
                        showNotice(modeLabel + '-Restore abgeschlossen.' + safetyInfo, 'success');
                    } else {
                        showNotice('Backup gelöscht.', 'success');
                    }

                    closeModal();
                    window.setTimeout(() => window.location.reload(), 700);
                } catch (error) {
                    showNotice(error.message || 'Aktion fehlgeschlagen', 'error');
                }
            }

            document.addEventListener('click', (event) => {
                if (event.target.closest('[data-close-modal]')) {
                    closeModal();
                    return;
                }

                const actionButton = event.target.closest('[data-backup-action]');
                if (actionButton) {
                    event.preventDefault();
                    event.stopPropagation();
                    closeMenus();
                    runAction(actionButton.dataset.backupAction, actionButton.dataset.backupId, actionButton.dataset.restoreMode || 'all');
                    return;
                }

                if (event.target.closest('[data-stop-open]')) {
                    return;
                }

                const card = event.target.closest('[data-backup-card]');
                if (card) {
                    closeMenus();
                    openModal(card);
                    return;
                }

                if (!event.target.closest('.backup-menu')) {
                    closeMenus();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeModal();
                    closeMenus();
                    return;
                }

                if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('[data-backup-card]')) {
                    event.preventDefault();
                    openModal(event.target);
                }
            });

            modalRestoreEditor.addEventListener('click', () => {
                if (currentBackup) {
                    runAction('restore', currentBackup.id, 'editor');
                }
            });

            modalRestoreSite.addEventListener('click', () => {
                if (currentBackup) {
                    runAction('restore', currentBackup.id, 'site');
                }
            });

            modalRestoreAll.addEventListener('click', () => {
                if (currentBackup) {
                    runAction('restore', currentBackup.id, 'all');
                }
            });

            modalDelete.addEventListener('click', () => {
                if (currentBackup) {
                    runAction('delete', currentBackup.id);
                }
            });
        })();
    </script>
</body>
</html>