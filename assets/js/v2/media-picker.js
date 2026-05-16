window.V2MediaPicker = (function() {
    'use strict';

    const LIBRARIES = {
        images: {
            title: 'Bild auswählen',
            emptyLabel: 'Noch keine Bilder vorhanden.'
        },
        backgrounds: {
            title: 'Hintergrundbild auswählen',
            emptyLabel: 'Noch keine Hintergrundbilder vorhanden.'
        },
        header: {
            title: 'Header-Bild auswählen',
            emptyLabel: 'Noch keine Header-Bilder vorhanden.'
        }
    };

    const UPLOADERS = {
        image: (file) => V2Api.uploadImage(file),
        background: (file) => V2Api.uploadBackground(file),
        header: (file) => V2Api.uploadHeader(file)
    };

    let _overlay = null;
    let _resolver = null;
    let _config = null;
    let _loadToken = 0;

    function pickImage(options = {}) {
        _settle(null);

        _config = _normalizeOptions(options);

        return new Promise((resolve) => {
            _resolver = resolve;
            _overlay = _buildOverlay(_config);
            document.body.appendChild(_overlay);
            document.addEventListener('keydown', _onKeyDown);
            _loadFiles(_config.currentPath);
        });
    }

    function close() {
        _settle(null);
    }

    function _normalizeOptions(options) {
        const mediaType = LIBRARIES[options.mediaType] ? options.mediaType : 'images';
        const uploadAction = UPLOADERS[options.uploadAction] ? options.uploadAction : 'image';
        const library = LIBRARIES[mediaType];

        return {
            title: options.title || library.title,
            mediaType,
            uploadAction,
            currentPath: options.currentPath || '',
            accept: options.accept || 'image/*',
            emptyLabel: library.emptyLabel
        };
    }

    function _buildOverlay(config) {
        const overlay = document.createElement('div');
        overlay.className = 'v2-media-picker-overlay';
        overlay.innerHTML = `
            <div class="v2-media-picker" role="dialog" aria-modal="true" aria-label="${_esc(config.title)}">
                <div class="v2-media-picker__header">
                    <div>
                        <h2>${_esc(config.title)}</h2>
                        <p>Bestehende Uploads verwenden oder direkt hier neue Datei hochladen.</p>
                    </div>
                    <button type="button" class="v2-media-picker__close" aria-label="Schließen">&times;</button>
                </div>
                <div class="v2-media-picker__body">
                    <div class="v2-media-picker__dropzone" data-v2-picker-dropzone>
                        <div class="v2-media-picker__dropcopy">
                            <strong>Datei hier ablegen</strong>
                            <span>oder Upload aus dem Picker starten</span>
                        </div>
                        <div class="v2-media-picker__dropactions">
                            <input type="file" accept="${_esc(config.accept)}" class="v2-media-picker__file-input" data-v2-picker-file>
                            <button type="button" class="v2-btn v2-btn-secondary" data-v2-picker-upload>Upload</button>
                            <button type="button" class="v2-btn v2-btn-secondary" data-v2-picker-refresh>Aktualisieren</button>
                        </div>
                    </div>
                    <div class="v2-media-picker__status" data-v2-picker-status>Lade Dateien...</div>
                    <div class="v2-media-picker__grid" data-v2-picker-grid></div>
                </div>
                <div class="v2-media-picker__footer">
                    <span>Drag and Drop laedt sofort hoch und uebernimmt die Datei direkt.</span>
                    <button type="button" class="v2-btn v2-btn-secondary" data-v2-picker-cancel>Abbrechen</button>
                </div>
            </div>
        `;

        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) {
                close();
            }
        });

        overlay.querySelector('[data-v2-picker-cancel]')?.addEventListener('click', close);
        overlay.querySelector('.v2-media-picker__close')?.addEventListener('click', close);
        overlay.querySelector('[data-v2-picker-upload]')?.addEventListener('click', () => {
            overlay.querySelector('[data-v2-picker-file]')?.click();
        });
        overlay.querySelector('[data-v2-picker-refresh]')?.addEventListener('click', () => {
            _loadFiles(_config?.currentPath || '');
        });
        overlay.querySelector('[data-v2-picker-file]')?.addEventListener('change', async (event) => {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (file) {
                await _uploadFile(file);
            }
        });

        const dropzone = overlay.querySelector('[data-v2-picker-dropzone]');
        if (dropzone) {
            ['dragenter', 'dragover'].forEach((eventName) => {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropzone.classList.add('is-dragover');
                });
            });

            ['dragleave', 'dragend', 'drop'].forEach((eventName) => {
                dropzone.addEventListener(eventName, (event) => {
                    event.preventDefault();
                    dropzone.classList.remove('is-dragover');
                });
            });

            dropzone.addEventListener('drop', async (event) => {
                const file = event.dataTransfer?.files?.[0];
                if (file) {
                    await _uploadFile(file);
                }
            });
        }

        return overlay;
    }

    async function _loadFiles(selectedPath) {
        if (!_overlay || !_config) {
            return;
        }

        const token = ++_loadToken;
        _setStatus('Lade Dateien...', 'info');

        try {
            const result = await V2Api.listFiles(_config.mediaType);
            if (token !== _loadToken || !_overlay) {
                return;
            }

            if (!result.success) {
                throw new Error(result.error || 'Dateien konnten nicht geladen werden');
            }

            _renderFiles(Array.isArray(result.files) ? result.files : [], selectedPath || _config.currentPath);
        } catch (error) {
            console.error('[MediaPicker] Load failed:', error);
            _setStatus('Dateien konnten nicht geladen werden.', 'error');
            const grid = _overlay.querySelector('[data-v2-picker-grid]');
            if (grid) {
                grid.innerHTML = '';
            }
        }
    }

    function _renderFiles(files, selectedPath) {
        const grid = _overlay?.querySelector('[data-v2-picker-grid]');
        if (!grid) {
            return;
        }

        grid.innerHTML = '';

        if (!files.length) {
            _setStatus(_config?.emptyLabel || 'Keine Dateien vorhanden.', 'info');
            return;
        }

        _setStatus(`${files.length} Datei${files.length === 1 ? '' : 'en'} gefunden`, 'success');

        files.forEach((file) => {
            const card = document.createElement('article');
            card.className = 'v2-media-card';
            if (file.path === selectedPath) {
                card.classList.add('is-current');
            }

            const modified = typeof file.modified === 'number'
                ? new Date(file.modified * 1000).toLocaleString('de-DE')
                : '';

            card.innerHTML = `
                <div class="v2-media-card__preview">
                    <img src="${_esc(file.path)}" alt="${_esc(file.filename || 'Bild')}" loading="lazy">
                </div>
                <div class="v2-media-card__body">
                    <strong class="v2-media-card__name">${_esc(file.filename || '')}</strong>
                    <span class="v2-media-card__meta">${_formatSize(file.size)}${modified ? ' · ' + _esc(modified) : ''}</span>
                    <button type="button" class="v2-btn v2-btn-primary v2-btn-small">Verwenden</button>
                </div>
            `;

            card.querySelector('button')?.addEventListener('click', () => {
                _settle(file.path || '');
            });

            grid.appendChild(card);
        });
    }

    async function _uploadFile(file) {
        if (!_config || !_overlay) {
            return;
        }

        const uploadButton = _overlay.querySelector('[data-v2-picker-upload]');
        const refreshButton = _overlay.querySelector('[data-v2-picker-refresh]');
        const uploader = UPLOADERS[_config.uploadAction] || UPLOADERS.image;

        if (uploadButton) uploadButton.disabled = true;
        if (refreshButton) refreshButton.disabled = true;
        _setStatus('Datei wird hochgeladen...', 'info');

        try {
            const result = await uploader(file);
            if (!result.success || !result.path) {
                throw new Error(result.error || 'Upload fehlgeschlagen');
            }

            if (window.V2?.toast) {
                window.V2.toast('Datei hochgeladen', 'success');
            }

            _settle(result.path);
        } catch (error) {
            console.error('[MediaPicker] Upload failed:', error);
            _setStatus(error.message || 'Upload fehlgeschlagen', 'error');
            if (window.V2?.toast) {
                window.V2.toast('Upload fehlgeschlagen', 'error');
            }
        } finally {
            if (uploadButton) uploadButton.disabled = false;
            if (refreshButton) refreshButton.disabled = false;
        }
    }

    function _setStatus(message, tone) {
        const status = _overlay?.querySelector('[data-v2-picker-status]');
        if (!status) {
            return;
        }

        status.textContent = message;
        status.dataset.tone = tone || 'info';
    }

    function _onKeyDown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }
    }

    function _settle(value) {
        if (_overlay) {
            _overlay.remove();
            _overlay = null;
        }

        document.removeEventListener('keydown', _onKeyDown);
        _config = null;
        _loadToken = 0;

        if (_resolver) {
            const resolve = _resolver;
            _resolver = null;
            resolve(value || null);
        }
    }

    function _formatSize(bytes) {
        const value = Number(bytes || 0);
        if (value < 1024) {
            return `${value} B`;
        }
        if (value < 1024 * 1024) {
            return `${(value / 1024).toFixed(1)} KB`;
        }
        return `${(value / (1024 * 1024)).toFixed(1)} MB`;
    }

    function _esc(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    return {
        pickImage,
        close
    };
})();