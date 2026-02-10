/**
 * Editor Modals - Tile-Modal, Feld-Rendering, Akkordeon-Editor, File-Browser
 * 
 * Abhängigkeiten: editor-core.js, editor-tiles.js (renderTiles, quickSaveTile)
 */

// ===== Tile Modal =====
function openTileModal(tileId = null) {
    currentEditTile = tileId ? tiles.find(t => t.id === tileId) : null;
    
    const modal = document.getElementById('tileModal');
    const title = document.getElementById('tileModalTitle');
    const form = document.getElementById('tileForm');
    
    title.textContent = currentEditTile ? 'Kachel bearbeiten' : 'Neue Kachel';
    form.reset();
    
    if (currentEditTile) {
        document.getElementById('tileId').value = currentEditTile.id;
        document.getElementById('tileType').value = currentEditTile.type;
        document.getElementById('tilePosition').value = currentEditTile.position;
        document.getElementById('tileSize').value = currentEditTile.size || 'medium';
        document.getElementById('tileStyle').value = currentEditTile.style || 'card';
        document.getElementById('tileColorScheme').value = currentEditTile.colorScheme || 'default';
        
        updateTileFields();
        
        // Felder mit Daten füllen
        setTimeout(() => {
            if (currentEditTile.data) {
                Object.entries(currentEditTile.data).forEach(([key, value]) => {
                    const input = document.querySelector(`[name="data[${key}]"]`);
                    if (input) {
                        if (input.type === 'checkbox') {
                            input.checked = value;
                        } else {
                            input.value = value;
                        }
                    }
                });
            }
        }, 50);
    } else {
        document.getElementById('tileId').value = '';
        document.getElementById('tileType').value = '';
        document.getElementById('tileColorScheme').value = 'default';
        document.getElementById('tileFields').innerHTML = '<p class="hint">Wähle zuerst einen Typ aus.</p>';
        
        // Nächste Position berechnen
        const maxPos = tiles.reduce((max, t) => Math.max(max, t.position || 0), 0);
        document.getElementById('tilePosition').value = maxPos + 10;
    }
    
    // Farboptionen basierend auf Stil aktualisieren
    updateColorSchemeOptions();
    
    modal.classList.add('active');
}

function closeTileModal() {
    document.getElementById('tileModal').classList.remove('active');
    currentEditTile = null;
}

function updateColorSchemeOptions() {
    const style = document.getElementById('tileStyle').value;
    const colorSelect = document.getElementById('tileColorScheme');
    const whiteOption = colorSelect.querySelector('option[value="white"]');
    
    if (style === 'card') {
        // Bei Card-Mode: Weiß-Option anzeigen
        if (whiteOption) whiteOption.style.display = '';
    } else {
        // Bei Flat-Mode: Weiß-Option verstecken
        if (whiteOption) whiteOption.style.display = 'none';
        // Falls Weiß ausgewählt war, auf default zurücksetzen
        if (colorSelect.value === 'white') {
            colorSelect.value = 'default';
        }
    }
}

// ===== Dynamic Field Rendering =====
function updateTileFields() {
    const type = document.getElementById('tileType').value;
    const container = document.getElementById('tileFields');
    
    if (!type || !tileTypes[type]) {
        container.innerHTML = '<p class="hint">Wähle zuerst einen Typ aus.</p>';
        return;
    }
    
    const fields = tileTypes[type].fields || [];
    const typeInfo = tileTypes[type];
    
    let html = '';
    let i = 0;
    let processedFields = new Set();
    
    while (i < fields.length) {
        const field = fields[i];
        
        // Schon verarbeitet? Überspringen
        if (processedFields.has(field)) {
            i++;
            continue;
        }
        
        // Kombiniere title + showTitle in einer Zeile
        if (field === 'title' && fields.includes('showTitle')) {
            html += renderInlineFieldWithCheckbox('title', 'showTitle');
            processedFields.add('title');
            processedFields.add('showTitle');
            i++;
        }
        // Kombiniere caption + lightbox
        else if (field === 'caption' && fields.includes('lightbox')) {
            html += renderInlineFieldWithCheckbox('caption', 'lightbox');
            processedFields.add('caption');
            processedFields.add('lightbox');
            i++;
        }
        // Kombiniere link + external für ImageTile
        else if (field === 'link' && fields.includes('external')) {
            html += renderInlineFieldWithCheckbox('link', 'external');
            processedFields.add('link');
            processedFields.add('external');
            i++;
        }
        // Kombiniere url + external für LinkTile
        else if (field === 'url' && fields.includes('external') && type === 'link') {
            html += renderInlineFieldWithCheckbox('url', 'external');
            processedFields.add('url');
            processedFields.add('external');
            i++;
        }
        // Akkordeon-Sections spezielle Behandlung
        else if (field.startsWith('section1_heading') && type === 'accordion') {
            html += renderAccordionSections();
            // Alle section-Felder als verarbeitet markieren
            for (let s = 1; s <= 10; s++) {
                processedFields.add(`section${s}_heading`);
                processedFields.add(`section${s}_content`);
            }
            i++;
        }
        // Section-Felder überspringen (werden von renderAccordionSections behandelt)
        else if (field.match(/^section\d+_(heading|content)$/) && type === 'accordion') {
            processedFields.add(field);
            i++;
        }
        // Normale Felder rendern (wenn nicht schon verarbeitet)
        else if (!processedFields.has(field)) {
            html += renderField(field, typeInfo);
            processedFields.add(field);
            i++;
        }
        else {
            i++;
        }
    }
    
    container.innerHTML = html || '<p class="hint">Dieser Typ hat keine zusätzlichen Felder.</p>';
    
    // Accordion-Sections initialisieren
    if (type === 'accordion') {
        initAccordionEditor();
    }
}

// ===== Accordion Editor =====
function renderAccordionSections() {
    let html = '<div class="accordion-editor" id="accordionEditor">';
    html += '<label class="form-label">Bereiche:</label>';
    html += '<div class="accordion-sections" id="accordionSections">';
    
    // 10 Sections rendern (erste sichtbar, Rest hidden)
    for (let i = 1; i <= 10; i++) {
        const hidden = i > 1 ? ' hidden' : '';
        const required = i === 1 ? 'required' : '';
        
        html += `
            <div class="accordion-section${hidden}" id="accordionSection${i}" data-section="${i}">
                <div class="accordion-section-header">
                    <span class="accordion-section-number">Bereich ${i}</span>
                    <div class="accordion-section-actions">
                        <button type="button" class="btn btn-small btn-icon" onclick="moveAccordionSection(${i}, -1)" title="Nach oben" data-move="up">⬆️</button>
                        <button type="button" class="btn btn-small btn-icon" onclick="moveAccordionSection(${i}, 1)" title="Nach unten" data-move="down">⬇️</button>
                        ${i > 1 ? `<button type="button" class="btn btn-small btn-danger" onclick="removeAccordionSection(${i})" title="Bereich entfernen">🗑️</button>` : ''}
                    </div>
                </div>
                <div class="form-row-compact">
                    <label for="data_section${i}_heading">Überschrift${i === 1 ? ' *' : ''}:</label>
                    <input type="text" name="data[section${i}_heading]" id="data_section${i}_heading" 
                           placeholder="Überschrift..." ${required}>
                </div>
                <div class="form-row-compact">
                    <label for="data_section${i}_content">Inhalt${i === 1 ? ' *' : ''}:</label>
                    <textarea name="data[section${i}_content]" id="data_section${i}_content" 
                              placeholder="Inhalt..." rows="3" ${required}></textarea>
                </div>
            </div>
        `;
    }
    
    html += '</div>'; // accordion-sections
    
    // Add-Button
    html += `
        <button type="button" class="btn btn-secondary accordion-add-btn" id="addAccordionSectionBtn" onclick="addAccordionSection()">
            ➕ Weiteren Bereich hinzufügen
        </button>
    `;
    
    html += '</div>'; // accordion-editor
    
    return html;
}

function initAccordionEditor() {
    // Sichtbarkeit basierend auf gefüllten Daten aktualisieren
    setTimeout(() => {
        let lastFilledSection = 1;
        
        for (let i = 1; i <= 10; i++) {
            const heading = document.getElementById(`data_section${i}_heading`);
            const content = document.getElementById(`data_section${i}_content`);
            
            if (heading && content && (heading.value.trim() || content.value.trim())) {
                lastFilledSection = i;
            }
        }
        
        // Alle gefüllten Sections + 1 leere anzeigen
        for (let i = 1; i <= Math.min(lastFilledSection + 1, 10); i++) {
            showAccordionSection(i);
        }
        
        updateAddButtonVisibility();
        updateMoveButtonStates();
        updateFullRowHint();
    }, 100);
}

function showAccordionSection(index) {
    const section = document.getElementById(`accordionSection${index}`);
    if (section) {
        section.classList.remove('hidden');
    }
}

function addAccordionSection() {
    // Finde erste versteckte Section
    for (let i = 2; i <= 10; i++) {
        const section = document.getElementById(`accordionSection${i}`);
        if (section && section.classList.contains('hidden')) {
            section.classList.remove('hidden');
            section.querySelector('input')?.focus();
            updateAddButtonVisibility();
            updateMoveButtonStates();
            return;
        }
    }
}

function removeAccordionSection(index) {
    const section = document.getElementById(`accordionSection${index}`);
    if (!section) return;
    
    // Felder leeren
    const heading = document.getElementById(`data_section${index}_heading`);
    const content = document.getElementById(`data_section${index}_content`);
    if (heading) heading.value = '';
    if (content) content.value = '';
    
    // Verstecken
    section.classList.add('hidden');
    
    updateAddButtonVisibility();
    updateMoveButtonStates();
}

function moveAccordionSection(index, direction) {
    // Sichtbare Sections ermitteln
    const visibleSections = getVisibleAccordionSections();
    const currentPos = visibleSections.indexOf(index);
    
    if (currentPos === -1) return;
    
    const targetPos = currentPos + direction;
    if (targetPos < 0 || targetPos >= visibleSections.length) return;
    
    const targetIndex = visibleSections[targetPos];
    
    // Werte tauschen
    swapAccordionSectionValues(index, targetIndex);
    
    // Button-States aktualisieren
    updateMoveButtonStates();
}

function swapAccordionSectionValues(indexA, indexB) {
    const headingA = document.getElementById(`data_section${indexA}_heading`);
    const contentA = document.getElementById(`data_section${indexA}_content`);
    const headingB = document.getElementById(`data_section${indexB}_heading`);
    const contentB = document.getElementById(`data_section${indexB}_content`);
    
    if (!headingA || !contentA || !headingB || !contentB) return;
    
    // Temporär speichern
    const tempHeading = headingA.value;
    const tempContent = contentA.value;
    
    // Tauschen
    headingA.value = headingB.value;
    contentA.value = contentB.value;
    headingB.value = tempHeading;
    contentB.value = tempContent;
}

function getVisibleAccordionSections() {
    const visible = [];
    for (let i = 1; i <= 10; i++) {
        const section = document.getElementById(`accordionSection${i}`);
        if (section && !section.classList.contains('hidden')) {
            visible.push(i);
        }
    }
    return visible;
}

function updateMoveButtonStates() {
    const visibleSections = getVisibleAccordionSections();
    
    for (let i = 1; i <= 10; i++) {
        const section = document.getElementById(`accordionSection${i}`);
        if (!section) continue;
        
        const upBtn = section.querySelector('[data-move="up"]');
        const downBtn = section.querySelector('[data-move="down"]');
        
        if (!upBtn || !downBtn) continue;
        
        const posInVisible = visibleSections.indexOf(i);
        const isFirst = posInVisible === 0;
        const isLast = posInVisible === visibleSections.length - 1;
        
        // Disable/Enable Buttons
        upBtn.disabled = isFirst;
        downBtn.disabled = isLast;
        
        // Visuelles Feedback
        upBtn.style.opacity = isFirst ? '0.3' : '1';
        downBtn.style.opacity = isLast ? '0.3' : '1';
    }
}

function updateAddButtonVisibility() {
    const addBtn = document.getElementById('addAccordionSectionBtn');
    if (!addBtn) return;
    
    // Prüfen ob noch versteckte Sections vorhanden
    let hasHidden = false;
    for (let i = 2; i <= 10; i++) {
        const section = document.getElementById(`accordionSection${i}`);
        if (section && section.classList.contains('hidden')) {
            hasHidden = true;
            break;
        }
    }
    
    addBtn.style.display = hasHidden ? '' : 'none';
}

function updateFullRowHint() {
    const singleOpenCheckbox = document.getElementById('data_singleOpen');
    const fullRowCheckbox = document.getElementById('data_fullRow');
    
    if (!singleOpenCheckbox || !fullRowCheckbox) return;
    
    // Hinweis anzeigen wenn singleOpen=false und fullRow=false
    const showHint = !singleOpenCheckbox.checked && !fullRowCheckbox.checked;
    
    let hint = document.getElementById('fullRowHint');
    if (showHint && !hint) {
        hint = document.createElement('div');
        hint.id = 'fullRowHint';
        hint.className = 'field-hint warning';
        hint.textContent = '⚠️ Bei mehreren offenen Bereichen wird "Volle Zeile im Grid" empfohlen.';
        fullRowCheckbox.parentElement.appendChild(hint);
    } else if (!showHint && hint) {
        hint.remove();
    }
}

// Akkordeon-Funktionen global verfügbar machen (für onclick-Handler)
window.addAccordionSection = addAccordionSection;
window.removeAccordionSection = removeAccordionSection;
window.moveAccordionSection = moveAccordionSection;

// ===== Field Configuration & Rendering =====

// Feld mit Checkbox in einer Zeile
function renderInlineFieldWithCheckbox(fieldName, checkboxName) {
    const fieldConfigs = getFieldConfigs();
    const fieldConfig = fieldConfigs[fieldName] || { type: 'text', label: fieldName };
    const checkboxConfig = fieldConfigs[checkboxName] || { label: checkboxName, default: false };
    
    const required = fieldConfig.required ? 'required' : '';
    const checked = checkboxConfig.default ? 'checked' : '';
    
    return `
        <div class="form-field-inline">
            <label for="data_${fieldName}">${fieldConfig.label}${fieldConfig.required ? ' *' : ''}:</label>
            <input type="${fieldConfig.type === 'url' ? 'url' : 'text'}" name="data[${fieldName}]" id="data_${fieldName}" 
                   ${required} placeholder="${fieldConfig.placeholder || ''}" value="${fieldConfig.default || ''}">
            <label class="checkbox-label">
                <input type="checkbox" name="data[${checkboxName}]" id="data_${checkboxName}" ${checked}>
                ${checkboxConfig.label}
            </label>
        </div>
    `;
}

function getFieldConfigs() {
    return {
        title: { type: 'text', label: 'Titel', required: true },
        showTitle: { type: 'checkbox', label: 'anzeigen', required: false, default: true },
        description: { type: 'textarea', label: 'Beschreibung', required: false },
        image: { type: 'image', label: 'Bild', required: true },
        file: { type: 'file', label: 'Datei', required: true },
        url: { type: 'url', label: 'URL', required: true },
        link: { type: 'url', label: 'Link', required: false, placeholder: 'https://...' },
        linkText: { type: 'text', label: 'Link-Text', required: false, default: 'Mehr erfahren' },
        buttonText: { type: 'text', label: 'Button-Text', required: false, default: 'Download' },
        caption: { type: 'text', label: 'Untertitel', required: false },
        lightbox: { type: 'checkbox', label: 'Lightbox', required: false, default: true },
        external: { type: 'checkbox', label: 'neuer Tab', required: false, default: true },
        name: { type: 'text', label: 'Name', required: true },
        email: { type: 'email', label: 'E-Mail', required: false },
        phone: { type: 'text', label: 'Telefon', required: false },
        displayMode: { 
            type: 'select', 
            label: 'Anzeigemodus', 
            required: false, 
            default: 'inline',
            options: {
                'inline': 'Inline (direkt eingebettet)',
                'modal': 'Modal (öffnet bei Klick)'
            }
        },
        aspectRatio: { 
            type: 'select', 
            label: 'Seitenverhältnis', 
            required: false, 
            default: '16:9',
            options: {
                '16:9': '16:9 (Breitbild)',
                '4:3': '4:3 (Standard)',
                '1:1': '1:1 (Quadrat)',
                'custom': 'Benutzerdefinierte Höhe'
            }
        },
        customHeight: { 
            type: 'number', 
            label: 'Höhe in Pixel', 
            required: false, 
            default: 500,
            placeholder: '500',
            hint: 'Nur bei benutzerdefinierter Höhe'
        },
        // Countdown-Felder
        targetDate: { type: 'date', label: 'Zieldatum', required: true },
        targetTime: { type: 'time', label: 'Uhrzeit', required: false, default: '00:00' },
        countMode: {
            type: 'select',
            label: 'Anzeigemodus',
            required: false,
            default: 'dynamic',
            options: {
                'dynamic': 'Dynamisch (passt sich an)',
                'days': 'Tage (noch X Tage)',
                'hours': 'Stunden (noch X Stunden)',
                'timer': 'Timer (DD:HH:MM:SS)'
            }
        },
        expiredText: {
            type: 'text',
            label: 'Text nach Ablauf',
            required: false,
            default: 'Abgelaufen',
            placeholder: 'z.B. Jetzt anmelden!'
        },
        hideOnExpiry: { type: 'checkbox', label: 'Nach Ablauf ausblenden', required: false, default: false },
        // Quote-Felder
        quote: {
            type: 'textarea',
            label: 'Zitat / Bibelvers',
            required: true,
            placeholder: 'Denn also hat Gott die Welt geliebt...'
        },
        source: {
            type: 'text',
            label: 'Quelle',
            required: false,
            placeholder: 'z.B. "Johannes 3,16" oder "Martin Luther"'
        },
        // Separator-Felder
        height: { type: 'number', label: 'Höhe (px)', required: false, default: 40, placeholder: '40' },
        showLine: { type: 'checkbox', label: 'Linie anzeigen', required: false, default: false },
        lineWidth: {
            type: 'select',
            label: 'Linienbreite',
            required: false,
            default: 'medium',
            options: {
                'small': 'Kurz (30%)',
                'medium': 'Mittel (60%)',
                'large': 'Voll (100%)'
            }
        },
        lineStyle: {
            type: 'select',
            label: 'Linienstil',
            required: false,
            default: 'solid',
            options: {
                'solid': 'Durchgezogen',
                'dashed': 'Gestrichelt',
                'dotted': 'Gepunktet'
            }
        },
        // Link-Tile Felder
        showDomain: { type: 'checkbox', label: 'Link-Vorschau anzeigen', required: false, default: true },
        // Accordion-Felder
        singleOpen: { type: 'checkbox', label: 'Nur ein Bereich gleichzeitig offen', required: false, default: true },
        autoScroll: {
            type: 'select',
            label: 'Auto-Scroll zum geöffneten Bereich',
            required: false,
            default: 'mobile',
            options: {
                'always': 'Immer',
                'mobile': 'Nur auf Mobilgeräten',
                'never': 'Nie'
            }
        },
        defaultOpen: {
            type: 'select',
            label: 'Initial geöffneter Bereich',
            required: false,
            default: '-1',
            options: {
                '-1': 'Alle geschlossen',
                '0': 'Bereich 1',
                '1': 'Bereich 2',
                '2': 'Bereich 3',
                '3': 'Bereich 4',
                '4': 'Bereich 5'
            }
        },
        fullRow: {
            type: 'checkbox',
            label: 'Volle Zeile im Grid',
            required: false,
            default: false,
            hint: 'Empfohlen wenn mehrere Bereiche gleichzeitig offen sein können'
        }
    };
}

function renderField(fieldName, typeInfo) {
    const fieldConfigs = getFieldConfigs();
    
    const config = fieldConfigs[fieldName] || { type: 'text', label: fieldName, required: false };
    const required = config.required ? 'required' : '';
    const defaultValue = config.default || '';
    const reqMark = config.required ? ' *' : '';
    
    switch (config.type) {
        case 'textarea':
            return `
                <div class="form-row-compact">
                    <label for="data_${fieldName}">${config.label}${reqMark}:</label>
                    <textarea name="data[${fieldName}]" id="data_${fieldName}" ${required}
                              placeholder="${config.placeholder || ''}">${defaultValue}</textarea>
                </div>
            `;
            
        case 'image':
            return `
                <div class="form-row-compact">
                    <label>${config.label}${reqMark}:</label>
                    <div class="input-with-button">
                        <input type="text" name="data[${fieldName}]" id="data_${fieldName}" 
                               ${required} readonly placeholder="Bild auswählen...">
                        <button type="button" class="btn btn-small" 
                                onclick="openFileBrowser('images', 'data_${fieldName}')">
                            Auswählen
                        </button>
                    </div>
                </div>
                <div class="image-preview" id="preview_${fieldName}"></div>
            `;
            
        case 'file':
            return `
                <div class="form-row-compact">
                    <label>${config.label}${reqMark}:</label>
                    <div class="input-with-button">
                        <input type="text" name="data[${fieldName}]" id="data_${fieldName}" 
                               ${required} readonly placeholder="Datei auswählen...">
                        <button type="button" class="btn btn-small" 
                                onclick="openFileBrowser('downloads', 'data_${fieldName}')">
                            Auswählen
                        </button>
                    </div>
                </div>
            `;
            
        case 'checkbox':
            const checked = defaultValue ? 'checked' : '';
            return `
                <div class="form-row-compact checkbox-only">
                    <label></label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="data[${fieldName}]" id="data_${fieldName}" ${checked}>
                        ${config.label}
                    </label>
                </div>
            `;
            
        case 'url':
            return `
                <div class="form-row-compact">
                    <label for="data_${fieldName}">${config.label}${reqMark}:</label>
                    <input type="url" name="data[${fieldName}]" id="data_${fieldName}" 
                           ${required} placeholder="https://example.com" value="${defaultValue}">
                </div>
            `;
            
        case 'select':
            const options = Object.entries(config.options || {})
                .map(([value, label]) => `<option value="${value}" ${value === defaultValue ? 'selected' : ''}>${label}</option>`)
                .join('');
            return `
                <div class="form-row-compact">
                    <label for="data_${fieldName}">${config.label}${reqMark}:</label>
                    <select name="data[${fieldName}]" id="data_${fieldName}" ${required}>
                        ${options}
                    </select>
                </div>
                ${config.hint ? `<small class="hint">${config.hint}</small>` : ''}
            `;
            
        case 'number':
            return `
                <div class="form-row-compact">
                    <label for="data_${fieldName}">${config.label}${reqMark}:</label>
                    <input type="number" name="data[${fieldName}]" id="data_${fieldName}" 
                           ${required} placeholder="${config.placeholder || ''}" value="${defaultValue}"
                           min="${config.min || ''}" max="${config.max || ''}">
                </div>
                ${config.hint ? `<small class="hint">${config.hint}</small>` : ''}
            `;
            
        case 'date':
            return `
                <div class="form-row-compact">
                    <label for="data_${fieldName}">${config.label}${reqMark}:</label>
                    <input type="date" name="data[${fieldName}]" id="data_${fieldName}" 
                           ${required} value="${defaultValue}">
                </div>
                ${config.hint ? `<small class="hint">${config.hint}</small>` : ''}
            `;
            
        case 'time':
            return `
                <div class="form-row-compact">
                    <label for="data_${fieldName}">${config.label}${reqMark}:</label>
                    <input type="time" name="data[${fieldName}]" id="data_${fieldName}" 
                           ${required} value="${defaultValue}">
                </div>
                ${config.hint ? `<small class="hint">${config.hint}</small>` : ''}
            `;
            
        default:
            return `
                <div class="form-row-compact">
                    <label for="data_${fieldName}">${config.label}${reqMark}:</label>
                    <input type="${config.type}" name="data[${fieldName}]" id="data_${fieldName}" 
                           ${required} placeholder="${config.placeholder || ''}" value="${defaultValue}">
                </div>
            `;
    }
}

// ===== File Browser =====
function openFileBrowser(type, targetInputId) {
    currentFileType = type;
    fileBrowserCallback = targetInputId;
    
    document.getElementById('fileBrowserModal').classList.add('active');
    
    // Accept-Attribut für Upload setzen
    updateFileUploadUI(type);
    
    // Tabs aktualisieren
    document.querySelectorAll('.file-browser-tabs .tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.type === type);
    });
    
    // Dropzone initialisieren
    initDropzone();
    
    loadFiles(type);
}

function initDropzone() {
    const dropzone = document.getElementById('fileDropzone');
    const uploadInput = document.getElementById('fileBrowserUpload');
    
    if (!dropzone || dropzone.dataset.initialized) return;
    dropzone.dataset.initialized = 'true';
    
    // Klick auf Dropzone öffnet Dateiauswahl
    dropzone.addEventListener('click', () => uploadInput.click());
    
    // Drag & Drop Events
    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.add('dragover');
        e.dataTransfer.dropEffect = 'copy';
    });
    
    dropzone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.remove('dragover');
    });
    
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileUpload(files[0]);
        }
    });
}

function updateFileUploadUI(type) {
    const uploadInput = document.getElementById('fileBrowserUpload');
    const dropzoneIcon = document.querySelector('.dropzone-icon');
    
    if (!uploadInput) return;
    
    if (type === 'images') {
        uploadInput.accept = 'image/jpeg,image/png,image/gif,image/webp';
        if (dropzoneIcon) dropzoneIcon.textContent = '🖼️';
    } else {
        uploadInput.accept = '.pdf,.docx,.xlsx,.zip,.pptx,.txt';
        if (dropzoneIcon) dropzoneIcon.textContent = '📄';
    }
}

function closeFileBrowser() {
    document.getElementById('fileBrowserModal').classList.remove('active');
    fileBrowserCallback = null;
}

async function loadFiles(type) {
    currentFileType = type;
    
    // Tabs aktualisieren
    document.querySelectorAll('.file-browser-tabs .tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.type === type);
    });
    
    // Upload-Button aktualisieren
    updateFileUploadUI(type);
    
    const container = document.getElementById('fileList');
    container.innerHTML = '<div class="spinner"></div>';
    
    try {
        const response = await fetch(`${API_URL}?action=list_files&type=${type}`);
        const result = await response.json();
        
        if (result.success) {
            if (result.files.length === 0) {
                container.innerHTML = '<p class="hint">Keine Dateien vorhanden</p>';
            } else {
                container.innerHTML = result.files.map(file => renderFileItem(file, type)).join('');
            }
        } else {
            container.innerHTML = '<p class="hint">Fehler beim Laden</p>';
        }
    } catch (error) {
        console.error('Load files error:', error);
        container.innerHTML = '<p class="hint">Netzwerkfehler</p>';
    }
}

function renderFileItem(file, type) {
    if (type === 'images') {
        return `
            <div class="file-item" onclick="selectFile('${file.path}')">
                <img src="${file.path}" alt="${file.filename}">
                <div class="filename">${file.filename}</div>
            </div>
        `;
    } else {
        const ext = file.filename.split('.').pop().toLowerCase();
        const icon = getFileIcon(ext);
        return `
            <div class="file-item" onclick="selectFile('${file.path}')">
                <div class="file-item-icon">${icon}</div>
                <div class="filename">${file.filename}</div>
            </div>
        `;
    }
}

function getFileIcon(ext) {
    const icons = {
        'pdf': '📄',
        'docx': '📝', 'doc': '📝',
        'xlsx': '📊', 'xls': '📊',
        'pptx': '📽️', 'ppt': '📽️',
        'zip': '📦', 'rar': '📦',
        'txt': '📃'
    };
    return icons[ext] || '📎';
}

function selectFile(path) {
    if (fileBrowserCallback) {
        const input = document.getElementById(fileBrowserCallback);
        if (input) {
            input.value = path;
            
            // Für Bilder: Preview aktualisieren
            const previewId = 'preview_' + fileBrowserCallback.replace('data_', '');
            const preview = document.getElementById(previewId);
            if (preview && currentFileType === 'images') {
                preview.innerHTML = `<img src="${path}" alt="Preview">`;
            }
        }
    }
    
    closeFileBrowser();
}

async function uploadFile(input) {
    if (!input || !input.files || !input.files[0]) return;
    handleFileUpload(input.files[0]);
    // Input zurücksetzen für erneuten Upload
    input.value = '';
}

// Gemeinsame Upload-Logik für Input und Dropzone
async function handleFileUpload(file) {
    if (!file) return;
    
    // Dateivalidierung
    const isImage = currentFileType === 'images';
    const allowedImages = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const allowedDocs = ['.pdf', '.docx', '.xlsx', '.zip', '.pptx', '.txt'];
    
    if (isImage && !allowedImages.includes(file.type)) {
        showToast('error', 'Nur Bilder erlaubt (JPG, PNG, GIF, WebP)');
        return;
    }
    
    const ext = '.' + file.name.split('.').pop().toLowerCase();
    if (!isImage && !allowedDocs.includes(ext)) {
        showToast('error', 'Dateityp nicht erlaubt');
        return;
    }
    
    const action = isImage ? 'upload_image' : 'upload_download';
    
    const formData = new FormData();
    formData.append('action', action);
    formData.append('file', file);
    
    // Upload-Indikator
    const dropzone = document.getElementById('fileDropzone');
    if (dropzone) dropzone.classList.add('uploading');
    
    try {
        const result = await apiPostFormData(formData);
        
        if (result.success) {
            // Dateiliste neu laden und dann Datei auswählen
            await loadFiles(currentFileType);
            selectFile(result.path);
            showToast('success', 'Datei hochgeladen');
        } else {
            showToast('error', result.error || 'Upload fehlgeschlagen');
        }
    } catch (error) {
        console.error('Upload error:', error);
        showToast('error', 'Netzwerkfehler beim Upload');
    } finally {
        if (dropzone) dropzone.classList.remove('uploading');
    }
}
