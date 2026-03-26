/**
 * V2 Edit Modal - Dynamisches Tile-Bearbeitungsformular
 * 
 * Generiert Formulare basierend auf fieldMeta der Tile-Typen.
 * Unterstützte Feldtypen: text, textarea, checkbox, select, url, email, tel,
 *                         image, file, date, time, number
 * 
 * Gruppierung: Felder mit group-Property werden in Fieldsets zusammengefasst.
 */

window.V2EditModal = (function() {
    'use strict';

    let _overlay = null;
    let _currentTile = null;
    let _onSaveCallback = null;

    // === Initialization ===

    function init() {
        _createOverlay();
        if (V2_CONFIG.debugMode) {
            console.log('[EditModal] Initialized');
        }
    }

    function _createOverlay() {
        _overlay = document.createElement('div');
        _overlay.className = 'v2-modal-overlay';
        _overlay.style.display = 'none';
        _overlay.addEventListener('click', function(e) {
            if (e.target === _overlay) close();
        });
        document.body.appendChild(_overlay);
    }

    // === Open / Close ===

    /**
     * Öffnet den Edit-Modal für eine Tile.
     * @param {object} tile - Tile-Daten (id, type, data, size, style, ...)
     * @param {function} onSave - Callback nach erfolgreichem Speichern
     */
    function open(tile, onSave) {
        if (!tile) return;
        _currentTile = JSON.parse(JSON.stringify(tile)); // deep clone
        _onSaveCallback = onSave || null;

        const types = V2State.getTileTypes();
        const typeMeta = types[tile.type];
        if (!typeMeta) {
            V2.toast('Unbekannter Typ: ' + tile.type, 'error');
            return;
        }

        _overlay.innerHTML = '';
        const modal = _buildModal(tile, typeMeta);
        _overlay.appendChild(modal);
        _overlay.style.display = 'flex';

        // Focus first input
        requestAnimationFrame(() => {
            const first = modal.querySelector('input:not([type="hidden"]):not([type="checkbox"]):not([type="file"]), textarea, select');
            if (first) first.focus();
        });

        // Escape to close
        document.addEventListener('keydown', _onKeyDown);
    }

    function close() {
        _overlay.style.display = 'none';
        _overlay.innerHTML = '';
        _currentTile = null;
        _onSaveCallback = null;
        document.removeEventListener('keydown', _onKeyDown);
    }

    function _onKeyDown(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            close();
        }
    }

    // === Modal Builder ===

    function _buildModal(tile, typeMeta) {
        const modal = document.createElement('div');
        modal.className = 'v2-modal';

        // Header
        const header = document.createElement('div');
        header.className = 'v2-modal-header';
        header.innerHTML = `
            <h2>✏️ ${_esc(typeMeta.name)} bearbeiten</h2>
            <button type="button" class="v2-modal-close" title="Schließen">&times;</button>
        `;
        header.querySelector('.v2-modal-close').addEventListener('click', close);
        modal.appendChild(header);

        // Form
        const form = document.createElement('form');
        form.className = 'v2-modal-form';
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            _handleSave(form, typeMeta);
        });

        // Body (scrollable)
        const body = document.createElement('div');
        body.className = 'v2-modal-body';

        const fieldMeta = typeMeta.fieldMeta || {};
        const data = tile.data || {};

        // Group fields: ungrouped first, then by group
        const ungrouped = [];
        const groups = {};

        Object.entries(fieldMeta).forEach(([fieldName, meta]) => {
            if (meta.group) {
                if (!groups[meta.group]) groups[meta.group] = [];
                groups[meta.group].push({ fieldName, meta });
            } else {
                ungrouped.push({ fieldName, meta });
            }
        });

        // Render ungrouped fields
        ungrouped.forEach(({ fieldName, meta }) => {
            body.appendChild(_buildField(fieldName, meta, data[fieldName]));
        });

        // Render groups
        Object.entries(groups).forEach(([groupName, fields]) => {
            const fieldset = document.createElement('fieldset');
            fieldset.className = 'v2-modal-fieldset';
            const legend = document.createElement('legend');
            legend.textContent = _groupLabel(groupName);
            fieldset.appendChild(legend);

            // For 'sections' group: show collapsible section pairs
            if (groupName === 'sections') {
                _buildSectionFields(fieldset, fields, data);
            } else {
                fields.forEach(({ fieldName, meta }) => {
                    fieldset.appendChild(_buildField(fieldName, meta, data[fieldName]));
                });
            }

            body.appendChild(fieldset);
        });

        form.appendChild(body);

        // Footer
        const footer = document.createElement('div');
        footer.className = 'v2-modal-footer';
        footer.innerHTML = `
            <button type="button" class="v2-btn v2-btn-secondary v2-modal-cancel">Abbrechen</button>
            <button type="submit" class="v2-btn v2-btn-primary">💾 Speichern</button>
        `;
        footer.querySelector('.v2-modal-cancel').addEventListener('click', close);
        form.appendChild(footer);

        modal.appendChild(form);
        return modal;
    }

    // === Field Builders ===

    function _buildField(fieldName, meta, value) {
        const wrapper = document.createElement('div');
        wrapper.className = 'v2-field';

        if (meta.type === 'checkbox') {
            return _buildCheckbox(fieldName, meta, value);
        }

        // Label
        const label = document.createElement('label');
        label.className = 'v2-field-label';
        label.setAttribute('for', 'field-' + fieldName);
        label.textContent = meta.label || fieldName;
        if (meta.required) {
            const req = document.createElement('span');
            req.className = 'v2-field-required';
            req.textContent = ' *';
            label.appendChild(req);
        }
        wrapper.appendChild(label);

        // Input element
        let input;
        switch (meta.type) {
            case 'textarea':
                input = _buildTextarea(fieldName, meta, value);
                break;
            case 'select':
                input = _buildSelect(fieldName, meta, value);
                break;
            case 'image':
                input = _buildImageUpload(fieldName, meta, value);
                wrapper.appendChild(input);
                return wrapper;
            case 'file':
                input = _buildFileUpload(fieldName, meta, value);
                wrapper.appendChild(input);
                return wrapper;
            default:
                input = _buildInput(fieldName, meta, value);
                break;
        }

        wrapper.appendChild(input);

        // Hint
        if (meta.hint) {
            const hint = document.createElement('small');
            hint.className = 'v2-field-hint';
            hint.textContent = meta.hint;
            wrapper.appendChild(hint);
        }

        return wrapper;
    }

    function _buildInput(fieldName, meta, value) {
        const input = document.createElement('input');
        input.className = 'v2-field-input';
        input.id = 'field-' + fieldName;
        input.name = fieldName;

        // Map meta.type to HTML input type
        const typeMap = {
            'text': 'text',
            'url': 'url',
            'email': 'email',
            'tel': 'tel',
            'date': 'date',
            'time': 'time',
            'number': 'number'
        };
        input.type = typeMap[meta.type] || 'text';

        if (meta.placeholder) input.placeholder = meta.placeholder;
        if (meta.required) input.required = true;

        // Value: use current value, or default
        if (value !== undefined && value !== null) {
            input.value = value;
        } else if (meta.default !== undefined) {
            input.value = meta.default;
        }

        return input;
    }

    function _buildTextarea(fieldName, meta, value) {
        const textarea = document.createElement('textarea');
        textarea.className = 'v2-field-input v2-field-textarea';
        textarea.id = 'field-' + fieldName;
        textarea.name = fieldName;
        textarea.rows = 4;

        if (meta.placeholder) textarea.placeholder = meta.placeholder;
        if (meta.required) textarea.required = true;

        if (value !== undefined && value !== null) {
            textarea.value = value;
        } else if (meta.default !== undefined) {
            textarea.value = meta.default;
        }

        return textarea;
    }

    function _buildCheckbox(fieldName, meta, value) {
        const wrapper = document.createElement('div');
        wrapper.className = 'v2-field v2-field-checkbox-wrap';

        const label = document.createElement('label');
        label.className = 'v2-field-checkbox-label';
        label.setAttribute('for', 'field-' + fieldName);

        const input = document.createElement('input');
        input.type = 'checkbox';
        input.className = 'v2-field-checkbox';
        input.id = 'field-' + fieldName;
        input.name = fieldName;

        // Determine checked state
        if (value !== undefined && value !== null) {
            input.checked = !!value && value !== '0' && value !== 'false';
        } else if (meta.default !== undefined) {
            input.checked = !!meta.default;
        }

        const text = document.createElement('span');
        text.textContent = meta.label || fieldName;

        label.appendChild(input);
        label.appendChild(text);
        wrapper.appendChild(label);

        if (meta.hint) {
            const hint = document.createElement('small');
            hint.className = 'v2-field-hint';
            hint.textContent = meta.hint;
            wrapper.appendChild(hint);
        }

        return wrapper;
    }

    function _buildSelect(fieldName, meta, value) {
        const select = document.createElement('select');
        select.className = 'v2-field-input v2-field-select';
        select.id = 'field-' + fieldName;
        select.name = fieldName;

        const options = meta.options || {};
        Object.entries(options).forEach(([optVal, optLabel]) => {
            const opt = document.createElement('option');
            opt.value = optVal;
            opt.textContent = optLabel;
            select.appendChild(opt);
        });

        // Set current value
        const current = (value !== undefined && value !== null) ? value : (meta.default || '');
        select.value = String(current);

        return select;
    }

    function _buildImageUpload(fieldName, meta, value) {
        const container = document.createElement('div');
        container.className = 'v2-field-upload';

        // Hidden input to store path
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = fieldName;
        hidden.value = value || '';
        container.appendChild(hidden);

        // Preview area
        const preview = document.createElement('div');
        preview.className = 'v2-upload-preview';
        if (value) {
            preview.innerHTML = `<img src="${_esc(value)}" alt="Vorschau">`;
        } else {
            preview.innerHTML = '<span class="v2-upload-placeholder">📷 Kein Bild gewählt</span>';
        }
        container.appendChild(preview);

        // Controls
        const controls = document.createElement('div');
        controls.className = 'v2-upload-controls';

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = meta.accept || 'image/*';
        fileInput.className = 'v2-upload-file-input';
        fileInput.id = 'file-' + fieldName;

        const uploadBtn = document.createElement('button');
        uploadBtn.type = 'button';
        uploadBtn.className = 'v2-btn v2-btn-secondary v2-btn-sm';
        uploadBtn.textContent = '📁 Bild wählen';
        uploadBtn.addEventListener('click', () => fileInput.click());

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'v2-btn v2-btn-sm v2-btn-danger-text';
        removeBtn.textContent = '🗑️ Entfernen';
        removeBtn.style.display = value ? 'inline-flex' : 'none';
        removeBtn.addEventListener('click', () => {
            hidden.value = '';
            preview.innerHTML = '<span class="v2-upload-placeholder">📷 Kein Bild gewählt</span>';
            removeBtn.style.display = 'none';
        });

        fileInput.addEventListener('change', async () => {
            const file = fileInput.files[0];
            if (!file) return;

            uploadBtn.disabled = true;
            uploadBtn.textContent = '⏳ Hochladen...';

            try {
                const result = await V2Api.uploadImage(file);
                if (result.success) {
                    hidden.value = result.path;
                    preview.innerHTML = `<img src="${_esc(result.path)}" alt="Vorschau">`;
                    removeBtn.style.display = 'inline-flex';
                    V2.toast('Bild hochgeladen', 'success');
                } else {
                    V2.toast('Upload fehlgeschlagen: ' + (result.error || ''), 'error');
                }
            } catch (err) {
                console.error('[EditModal] Image upload failed:', err);
                V2.toast('Upload fehlgeschlagen', 'error');
            } finally {
                uploadBtn.disabled = false;
                uploadBtn.textContent = '📁 Bild wählen';
            }
        });

        controls.appendChild(fileInput);
        controls.appendChild(uploadBtn);
        controls.appendChild(removeBtn);
        container.appendChild(controls);

        return container;
    }

    function _buildFileUpload(fieldName, meta, value) {
        const container = document.createElement('div');
        container.className = 'v2-field-upload';

        // Hidden input to store path
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = fieldName;
        hidden.value = value || '';
        container.appendChild(hidden);

        // Current file display
        const fileDisplay = document.createElement('div');
        fileDisplay.className = 'v2-upload-file-display';
        if (value) {
            const fname = value.split('/').pop();
            fileDisplay.innerHTML = `<span class="v2-upload-filename">📄 ${_esc(fname)}</span>`;
        } else {
            fileDisplay.innerHTML = '<span class="v2-upload-placeholder">📄 Keine Datei gewählt</span>';
        }
        container.appendChild(fileDisplay);

        // Controls
        const controls = document.createElement('div');
        controls.className = 'v2-upload-controls';

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = meta.accept || '*';
        fileInput.className = 'v2-upload-file-input';
        fileInput.id = 'file-' + fieldName;

        const uploadBtn = document.createElement('button');
        uploadBtn.type = 'button';
        uploadBtn.className = 'v2-btn v2-btn-secondary v2-btn-sm';
        uploadBtn.textContent = '📁 Datei wählen';
        uploadBtn.addEventListener('click', () => fileInput.click());

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'v2-btn v2-btn-sm v2-btn-danger-text';
        removeBtn.textContent = '🗑️ Entfernen';
        removeBtn.style.display = value ? 'inline-flex' : 'none';
        removeBtn.addEventListener('click', () => {
            hidden.value = '';
            fileDisplay.innerHTML = '<span class="v2-upload-placeholder">📄 Keine Datei gewählt</span>';
            removeBtn.style.display = 'none';
        });

        fileInput.addEventListener('change', async () => {
            const file = fileInput.files[0];
            if (!file) return;

            uploadBtn.disabled = true;
            uploadBtn.textContent = '⏳ Hochladen...';

            try {
                const result = await V2Api.uploadDownload(file);
                if (result.success) {
                    hidden.value = result.path;
                    const fname = result.path.split('/').pop();
                    fileDisplay.innerHTML = `<span class="v2-upload-filename">📄 ${_esc(fname)}</span>`;
                    removeBtn.style.display = 'inline-flex';
                    V2.toast('Datei hochgeladen', 'success');
                } else {
                    V2.toast('Upload fehlgeschlagen: ' + (result.error || ''), 'error');
                }
            } catch (err) {
                console.error('[EditModal] File upload failed:', err);
                V2.toast('Upload fehlgeschlagen', 'error');
            } finally {
                uploadBtn.disabled = false;
                uploadBtn.textContent = '📁 Datei wählen';
            }
        });

        controls.appendChild(fileInput);
        controls.appendChild(uploadBtn);
        controls.appendChild(removeBtn);
        container.appendChild(controls);

        return container;
    }

    // === Accordion Section Fields ===
    // Mirrors the classic editor: show filled sections + 1 empty,
    // with add/remove/move buttons per section.

    function _buildSectionFields(container, fields, data) {
        const MAX_SECTIONS = 10;

        // Group fields by sectionIndex
        const sectionsMeta = {};
        fields.forEach(({ fieldName, meta }) => {
            const idx = meta.sectionIndex || 0;
            if (!sectionsMeta[idx]) sectionsMeta[idx] = [];
            sectionsMeta[idx].push({ fieldName, meta });
        });

        // Wrapper for all sections (needed for move/add/remove)
        const wrapper = document.createElement('div');
        wrapper.className = 'v2-accordion-editor';

        // Determine which sections have content
        let lastFilled = 0;
        for (let i = 1; i <= MAX_SECTIONS; i++) {
            const h = data[`section${i}_heading`];
            const c = data[`section${i}_content`];
            if ((h && h.trim()) || (c && c.trim())) lastFilled = i;
        }
        const initialVisible = Math.min(Math.max(lastFilled + 1, 1), MAX_SECTIONS);

        for (let i = 1; i <= MAX_SECTIONS; i++) {
            const sectionDiv = document.createElement('div');
            sectionDiv.className = 'v2-accordion-section';
            sectionDiv.dataset.sectionIdx = i;
            if (i > initialVisible) sectionDiv.style.display = 'none';

            // Header bar with number + actions
            const header = document.createElement('div');
            header.className = 'v2-accordion-section-header';

            const label = document.createElement('span');
            label.className = 'v2-accordion-section-label';
            label.textContent = `Bereich ${i}`;

            const actions = document.createElement('div');
            actions.className = 'v2-accordion-section-actions';

            // Move up
            const upBtn = document.createElement('button');
            upBtn.type = 'button';
            upBtn.className = 'v2-btn v2-btn-icon v2-btn-sm';
            upBtn.title = 'Nach oben';
            upBtn.textContent = '⬆️';
            upBtn.dataset.move = 'up';
            upBtn.addEventListener('click', () => _moveSection(wrapper, i, -1));

            // Move down
            const downBtn = document.createElement('button');
            downBtn.type = 'button';
            downBtn.className = 'v2-btn v2-btn-icon v2-btn-sm';
            downBtn.title = 'Nach unten';
            downBtn.textContent = '⬇️';
            downBtn.dataset.move = 'down';
            downBtn.addEventListener('click', () => _moveSection(wrapper, i, 1));

            actions.appendChild(upBtn);
            actions.appendChild(downBtn);

            // Remove (not for first section)
            if (i > 1) {
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'v2-btn v2-btn-icon v2-btn-sm v2-btn-danger-text';
                removeBtn.title = 'Bereich entfernen';
                removeBtn.textContent = '🗑️';
                removeBtn.addEventListener('click', () => _removeSection(wrapper, i));
                actions.appendChild(removeBtn);
            }

            header.appendChild(label);
            header.appendChild(actions);
            sectionDiv.appendChild(header);

            // Fields: heading + content
            const sFields = sectionsMeta[i] || [];
            sFields.forEach(({ fieldName, meta }) => {
                sectionDiv.appendChild(_buildField(fieldName, meta, data[fieldName]));
            });

            wrapper.appendChild(sectionDiv);
        }

        // Add section button
        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'v2-btn v2-btn-secondary v2-btn-sm v2-accordion-add-btn';
        addBtn.textContent = '➕ Weiteren Bereich hinzufügen';
        addBtn.addEventListener('click', () => _addSection(wrapper));
        wrapper.appendChild(addBtn);

        container.appendChild(wrapper);

        // Initial button states
        requestAnimationFrame(() => {
            _updateSectionButtons(wrapper);
        });
    }

    /** Show next hidden section */
    function _addSection(wrapper) {
        const sections = wrapper.querySelectorAll('.v2-accordion-section');
        for (const sec of sections) {
            if (sec.style.display === 'none') {
                sec.style.display = '';
                const inp = sec.querySelector('input[type="text"]');
                if (inp) inp.focus();
                break;
            }
        }
        _updateSectionButtons(wrapper);
    }

    /** Hide section and clear its fields */
    function _removeSection(wrapper, idx) {
        const sec = wrapper.querySelector(`[data-section-idx="${idx}"]`);
        if (!sec) return;
        // Clear values
        sec.querySelectorAll('input, textarea').forEach(el => { el.value = ''; });
        sec.style.display = 'none';
        _updateSectionButtons(wrapper);
    }

    /** Swap field values between two visible sections */
    function _moveSection(wrapper, idx, direction) {
        const visible = _getVisibleSections(wrapper);
        const pos = visible.indexOf(idx);
        if (pos === -1) return;
        const targetPos = pos + direction;
        if (targetPos < 0 || targetPos >= visible.length) return;
        const targetIdx = visible[targetPos];

        // Swap all input/textarea values between idx and targetIdx
        const secA = wrapper.querySelector(`[data-section-idx="${idx}"]`);
        const secB = wrapper.querySelector(`[data-section-idx="${targetIdx}"]`);
        if (!secA || !secB) return;

        const inputsA = secA.querySelectorAll('input[type="text"], textarea, input[type="hidden"]');
        const inputsB = secB.querySelectorAll('input[type="text"], textarea, input[type="hidden"]');
        inputsA.forEach((elA, i) => {
            const elB = inputsB[i];
            if (!elB) return;
            const tmp = elA.value;
            elA.value = elB.value;
            elB.value = tmp;
        });

        _updateSectionButtons(wrapper);
    }

    /** List visible section indices */
    function _getVisibleSections(wrapper) {
        const result = [];
        wrapper.querySelectorAll('.v2-accordion-section').forEach(sec => {
            if (sec.style.display !== 'none') {
                result.push(Number(sec.dataset.sectionIdx));
            }
        });
        return result;
    }

    /** Enable/disable move buttons, show/hide add button */
    function _updateSectionButtons(wrapper) {
        const visible = _getVisibleSections(wrapper);
        wrapper.querySelectorAll('.v2-accordion-section').forEach(sec => {
            const idx = Number(sec.dataset.sectionIdx);
            const pos = visible.indexOf(idx);
            const upBtn = sec.querySelector('[data-move="up"]');
            const downBtn = sec.querySelector('[data-move="down"]');
            if (upBtn) {
                upBtn.disabled = pos <= 0;
                upBtn.style.opacity = pos <= 0 ? '0.3' : '1';
            }
            if (downBtn) {
                downBtn.disabled = pos < 0 || pos >= visible.length - 1;
                downBtn.style.opacity = (pos < 0 || pos >= visible.length - 1) ? '0.3' : '1';
            }
        });
        // Add button visibility
        const addBtn = wrapper.querySelector('.v2-accordion-add-btn');
        if (addBtn) {
            const hasHidden = wrapper.querySelectorAll('.v2-accordion-section[style*="display: none"]').length > 0;
            addBtn.style.display = hasHidden ? '' : 'none';
        }
    }

    // === Save Handler ===

    async function _handleSave(form, typeMeta) {
        const formData = new FormData(form);
        const updatedData = {};

        const fieldMeta = typeMeta.fieldMeta || {};

        // Collect form values
        Object.entries(fieldMeta).forEach(([fieldName, meta]) => {
            if (meta.type === 'checkbox') {
                // Checkbox: checked → true, unchecked → not in FormData
                updatedData[fieldName] = formData.has(fieldName) ? true : false;
            } else if (meta.type === 'image' || meta.type === 'file') {
                // Image/File: value from hidden input
                const val = formData.get(fieldName);
                if (val !== null) updatedData[fieldName] = val;
            } else if (meta.type === 'number') {
                const val = formData.get(fieldName);
                updatedData[fieldName] = val !== null && val !== '' ? Number(val) : (meta.default || null);
            } else {
                const val = formData.get(fieldName);
                if (val !== null) updatedData[fieldName] = val;
            }
        });

        // Merge into tile
        const updatedTile = {
            ..._currentTile,
            data: {
                ...(_currentTile.data || {}),
                ...updatedData
            }
        };

        // Save via API
        const saveBtn = form.querySelector('button[type="submit"]');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = '⏳ Speichern...';
        }

        try {
            const result = await V2Api.saveTile(updatedTile);
            if (result.success) {
                V2.toast('Gespeichert', 'success');
                close();
                // Refresh canvas
                await V2Canvas.reloadAll();
                // Re-select tile
                if (result.tile && result.tile.id) {
                    V2State.selectTile(result.tile.id);
                } else if (_currentTile.id) {
                    V2State.selectTile(_currentTile.id);
                }
                if (_onSaveCallback) _onSaveCallback(result);
            } else {
                const errMsg = result.errors
                    ? (Array.isArray(result.errors) ? result.errors.join(', ') : result.errors)
                    : (result.error || 'Unbekannter Fehler');
                V2.toast('Fehler: ' + errMsg, 'error');
            }
        } catch (err) {
            console.error('[EditModal] Save failed:', err);
            V2.toast('Speichern fehlgeschlagen', 'error');
        } finally {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = '💾 Speichern';
            }
        }
    }

    // === Helpers ===

    function _esc(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    function _groupLabel(groupName) {
        const labels = {
            'sections': '📑 Bereiche',
            'options': '⚙️ Optionen',
            'style': '🎨 Darstellung',
            'advanced': '🔧 Erweitert'
        };
        return labels[groupName] || groupName;
    }

    // === Public API ===
    return {
        init,
        open,
        close
    };
})();
