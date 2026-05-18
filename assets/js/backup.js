(function() {
    'use strict';

    function initBackupPage() {
        const config = window.BACKUP_CONFIG;
        if (!config) {
            return;
        }

        const modal = document.getElementById('backupModal');
        const modalFrame = document.getElementById('backupModalFrame');
        const modalTitle = document.getElementById('backupModalTitle');
        const modalExport = document.getElementById('backupModalExport');
        const modalRestoreEditor = document.getElementById('backupModalRestoreEditor');
        const modalRestoreSite = document.getElementById('backupModalRestoreSite');
        const modalRestoreAll = document.getElementById('backupModalRestoreAll');
        const modalDelete = document.getElementById('backupModalDelete');
        const notice = document.getElementById('backupNotice');

        if (!modal || !modalFrame || !modalTitle || !modalExport || !modalRestoreEditor || !modalRestoreSite || !modalRestoreAll || !modalDelete || !notice) {
            return;
        }

        let currentBackup = null;
        let noticeTimeoutId = null;

        function showNotice(message, type) {
            notice.hidden = false;
            notice.className = 'backup-notice backup-notice--' + type;
            notice.textContent = message;
            window.clearTimeout(noticeTimeoutId);
            noticeTimeoutId = window.setTimeout(() => {
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
            formData.append('csrf_token', config.csrfToken);

            try {
                const response = await fetch(config.apiUrl, {
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
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBackupPage);
    } else {
        initBackupPage();
    }
})();
