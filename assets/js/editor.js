/**
 * Editor JavaScript - Info-Hub Visual Editor Logic
 * 
 * Modules:
 * - TileManager: Tile CRUD operations
 * - ModalManager: Modal handling
 * - ToastManager: Notifications
 * - FileManager: File uploads and browser
 */

// ===== Configuration =====
const API_URL = window.INITIAL_DATA?.apiUrl || 'api/endpoints.php';
const CSRF_TOKEN = window.INITIAL_DATA?.csrfToken || '';

// ===== Helper: API Request with CSRF =====
async function apiPost(action, data = {}) {
    const body = new URLSearchParams();
    body.append('action', action);
    body.append('csrf_token', CSRF_TOKEN);
    
    for (const [key, value] of Object.entries(data)) {
        body.append(key, typeof value === 'object' ? JSON.stringify(value) : value);
    }
    
    const response = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    });
    
    return response.json();
}

async function apiPostFormData(formData) {
    formData.append('csrf_token', CSRF_TOKEN);
    const response = await fetch(API_URL, { method: 'POST', body: formData });
    return response.json();
}

// ===== State =====
let tiles = window.INITIAL_DATA?.tiles || [];
let tileTypes = window.INITIAL_DATA?.tileTypes || {};
let settings = window.INITIAL_DATA?.settings || {};
let currentEditTile = null;
let fileBrowserCallback = null;
let currentFileType = 'images';
let previewWindow = null;

// ===== Initialization =====
document.addEventListener('DOMContentLoaded', () => {
    renderTiles();
    startSessionTimer();
});

// ===== Tile Rendering =====
function renderTiles() {
    const container = document.getElementById('tilesList');
    const countEl = document.getElementById('tileCount');
    
    if (tiles.length === 0) {
        container.innerHTML = '<div class="tiles-list empty">Noch keine Kacheln vorhanden. Füge deine erste Kachel hinzu!</div>';
        container.classList.add('empty');
    } else {
        container.classList.remove('empty');
        container.innerHTML = tiles.map(tile => renderTileCard(tile)).join('');
    }
    
    countEl.textContent = `${tiles.length} Kachel${tiles.length !== 1 ? 'n' : ''}`;
}

function renderTileCard(tile) {
    const typeInfo = tileTypes[tile.type] || { name: tile.type };
    let title = tile.data?.title || tile.data?.name || 'Ohne Titel';
    
    // Bei Iframe ohne Titel: URL anzeigen
    if (tile.type === 'iframe' && !tile.data?.title && tile.data?.url) {
        try {
            const url = new URL(tile.data.url);
            title = url.hostname;
        } catch (e) {
            title = 'Iframe';
        }
    }
    
    return `
        <div class="tile-card" data-id="${tile.id}">
            <!-- Vorbereitung für Drag and Drop -->
            <!-- <div class="tile-drag-handle" title="Ziehen zum Sortieren">⋮⋮</div> -->
            <div class="tile-info">
                <div class="tile-title">${escapeHtml(title)}</div>
                <div class="tile-meta">
                    <span class="tile-type-badge">${escapeHtml(typeInfo.name)}</span>
                    <span>📍 Pos: ${tile.position}</span>
                    <span>📐 ${getSizeLabel(tile.size)}</span>
                </div>
            </div>
            <div class="tile-actions">
                <button class="btn btn-icon" onclick="duplicateTile('${tile.id}')" title="Duplizieren">
                    📋
                </button>
                <button class="btn btn-icon" onclick="editTile('${tile.id}')" title="Bearbeiten">
                    ✏️
                </button>
                <button class="btn btn-icon" onclick="deleteTile('${tile.id}')" title="Löschen">
                    🗑️
                </button>
            </div>
        </div>
    `;
}

function getSizeLabel(size) {
    const labels = {
        'small': 'Klein',
        'medium': 'Mittel',
        'large': 'Groß',
        'full': 'Voll'
    };
    return labels[size] || size;
}

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
    
    fields.forEach(field => {
        html += renderField(field, typeInfo);
    });
    
    container.innerHTML = html || '<p class="hint">Dieser Typ hat keine zusätzlichen Felder.</p>';
}

function renderField(fieldName, typeInfo) {
    // Standard-Feldkonfigurationen
    const fieldConfigs = {
        title: { type: 'text', label: 'Titel', required: true },
        showTitle: { type: 'checkbox', label: 'Titel auf Seite anzeigen', required: false, default: true },
        description: { type: 'textarea', label: 'Beschreibung', required: false },
        image: { type: 'image', label: 'Bild', required: true },
        file: { type: 'file', label: 'Datei', required: true },
        url: { type: 'url', label: 'URL', required: true },
        linkText: { type: 'text', label: 'Link-Text', required: false, default: 'Mehr erfahren' },
        buttonText: { type: 'text', label: 'Button-Text', required: false, default: 'Download' },
        caption: { type: 'text', label: 'Bildunterschrift', required: false },
        lightbox: { type: 'checkbox', label: 'Lightbox aktivieren', required: false, default: true },
        external: { type: 'checkbox', label: 'In neuem Tab öffnen', required: false, default: true },
        name: { type: 'text', label: 'Name', required: true },
        email: { type: 'email', label: 'E-Mail', required: false },
        phone: { type: 'text', label: 'Telefon', required: false },
        // Iframe-Felder
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
        }
    };
    
    const config = fieldConfigs[fieldName] || { type: 'text', label: fieldName, required: false };
    const required = config.required ? 'required' : '';
    const defaultValue = config.default || '';
    
    switch (config.type) {
        case 'textarea':
            return `
                <div class="form-group">
                    <label for="data_${fieldName}">${config.label}${config.required ? ' *' : ''}</label>
                    <textarea name="data[${fieldName}]" id="data_${fieldName}" ${required}
                              placeholder="${config.placeholder || ''}">${defaultValue}</textarea>
                </div>
            `;
            
        case 'image':
            return `
                <div class="form-group">
                    <label for="data_${fieldName}">${config.label}${config.required ? ' *' : ''}</label>
                    <div class="file-input-group">
                        <input type="text" name="data[${fieldName}]" id="data_${fieldName}" 
                               ${required} readonly placeholder="Bild auswählen...">
                        <button type="button" class="btn btn-small" 
                                onclick="openFileBrowser('images', 'data_${fieldName}')">
                            📷 Auswählen
                        </button>
                    </div>
                    <div class="image-preview" id="preview_${fieldName}"></div>
                </div>
            `;
            
        case 'file':
            return `
                <div class="form-group">
                    <label for="data_${fieldName}">${config.label}${config.required ? ' *' : ''}</label>
                    <div class="file-input-group">
                        <input type="text" name="data[${fieldName}]" id="data_${fieldName}" 
                               ${required} readonly placeholder="Datei auswählen...">
                        <button type="button" class="btn btn-small" 
                                onclick="openFileBrowser('downloads', 'data_${fieldName}')">
                            📄 Auswählen
                        </button>
                    </div>
                </div>
            `;
            
        case 'checkbox':
            const checked = defaultValue ? 'checked' : '';
            return `
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="data[${fieldName}]" id="data_${fieldName}" ${checked}>
                        ${config.label}
                    </label>
                </div>
            `;
            
        case 'url':
            return `
                <div class="form-group">
                    <label for="data_${fieldName}">${config.label}${config.required ? ' *' : ''}</label>
                    <input type="url" name="data[${fieldName}]" id="data_${fieldName}" 
                           ${required} placeholder="https://example.com" value="${defaultValue}">
                </div>
            `;
            
        case 'select':
            const options = Object.entries(config.options || {})
                .map(([value, label]) => `<option value="${value}" ${value === defaultValue ? 'selected' : ''}>${label}</option>`)
                .join('');
            return `
                <div class="form-group">
                    <label for="data_${fieldName}">${config.label}${config.required ? ' *' : ''}</label>
                    <select name="data[${fieldName}]" id="data_${fieldName}" ${required}>
                        ${options}
                    </select>
                    ${config.hint ? `<small class="hint">${config.hint}</small>` : ''}
                </div>
            `;
            
        case 'number':
            return `
                <div class="form-group">
                    <label for="data_${fieldName}">${config.label}${config.required ? ' *' : ''}</label>
                    <input type="number" name="data[${fieldName}]" id="data_${fieldName}" 
                           ${required} placeholder="${config.placeholder || ''}" value="${defaultValue}"
                           min="${config.min || ''}" max="${config.max || ''}">
                    ${config.hint ? `<small class="hint">${config.hint}</small>` : ''}
                </div>
            `;
            
        default:
            return `
                <div class="form-group">
                    <label for="data_${fieldName}">${config.label}${config.required ? ' *' : ''}</label>
                    <input type="${config.type}" name="data[${fieldName}]" id="data_${fieldName}" 
                           ${required} placeholder="${config.placeholder || ''}" value="${defaultValue}">
                </div>
            `;
    }
}

// ===== Tile CRUD =====
async function saveTile(event) {
    event.preventDefault();
    
    const form = document.getElementById('tileForm');
    const formData = new FormData(form);
    
    // Tile-Daten zusammenstellen
    const tileData = {
        id: formData.get('id') || null,
        type: formData.get('type'),
        position: parseInt(formData.get('position')) || 10,
        size: formData.get('size') || 'medium',
        style: formData.get('style') || 'card',
        colorScheme: formData.get('colorScheme') || 'default',
        data: {}
    };
    
    // Data-Felder extrahieren
    for (const [key, value] of formData.entries()) {
        if (key.startsWith('data[')) {
            const fieldName = key.match(/data\[(.+)\]/)[1];
            tileData.data[fieldName] = value;
        }
    }
    
    // Checkboxen behandeln (nicht ausgewählt = false)
    const checkboxes = form.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(cb => {
        const match = cb.name.match(/data\[(.+)\]/);
        if (match) {
            tileData.data[match[1]] = cb.checked;
        }
    });
    
    try {
        const result = await apiPost('save_tile', { tile: tileData });
        
        if (result.success) {
            // Lokale Liste aktualisieren
            if (tileData.id) {
                const index = tiles.findIndex(t => t.id === tileData.id);
                if (index !== -1) {
                    tiles[index] = result.tile;
                }
            } else {
                tiles.push(result.tile);
            }
            
            // Sortieren
            tiles.sort((a, b) => (a.position || 0) - (b.position || 0));
            
            renderTiles();
            closeTileModal();
            showToast('success', 'Kachel gespeichert!');
            refreshPreview();
        } else {
            const errors = result.errors?.join(', ') || 'Speichern fehlgeschlagen';
            showToast('error', errors);
        }
    } catch (error) {
        console.error('Save error:', error);
        showToast('error', 'Netzwerkfehler beim Speichern');
    }
}

function editTile(id) {
    openTileModal(id);
}

async function duplicateTile(id) {
    const originalTile = tiles.find(t => t.id === id);
    if (!originalTile) return;
    
    // Neue Tile-Daten erstellen
    const duplicatedTile = {
        type: originalTile.type,
        position: (originalTile.position || 0) + 10,
        size: originalTile.size || 'medium',
        style: originalTile.style || 'card',
        colorScheme: originalTile.colorScheme || 'default',
        data: { ...originalTile.data }
    };
    
    // Titel anpassen falls vorhanden
    if (duplicatedTile.data.title) {
        duplicatedTile.data.title += ' (Kopie)';
    }
    
    try {
        const result = await apiPost('save_tile', { tile: duplicatedTile });
        
        if (result.success) {
            tiles.push(result.tile);
            tiles.sort((a, b) => (a.position || 0) - (b.position || 0));
            
            renderTiles();
            showToast('success', 'Kachel dupliziert!');
            refreshPreview();
        } else {
            const errors = result.errors?.join(', ') || 'Duplizieren fehlgeschlagen';
            showToast('error', errors);
        }
    } catch (error) {
        console.error('Duplicate error:', error);
        showToast('error', 'Netzwerkfehler beim Duplizieren');
    }
}

async function deleteTile(id) {
    if (!confirm('Kachel wirklich löschen?')) return;
    
    try {
        const result = await apiPost('delete_tile', { id });
        
        if (result.success) {
            tiles = tiles.filter(t => t.id !== id);
            renderTiles();
            showToast('success', 'Kachel gelöscht');
            refreshPreview();
        } else {
            showToast('error', result.error || 'Löschen fehlgeschlagen');
        }
    } catch (error) {
        console.error('Delete error:', error);
        showToast('error', 'Netzwerkfehler beim Löschen');
    }
}

// ===== Settings Modal =====
function openSettingsModal() {
    document.getElementById('settingsModal').classList.add('active');
}

function closeSettingsModal() {
    document.getElementById('settingsModal').classList.remove('active');
}

async function saveSettings(event) {
    event.preventDefault();
    
    const form = document.getElementById('settingsForm');
    const formData = new FormData(form);
    
    const newSettings = {
        site: {
            title: formData.get('title'),
            headerImage: formData.get('headerImage') || null,
            footerText: formData.get('footerText')
        },
        theme: {
            backgroundColor: formData.get('backgroundColor'),
            primaryColor: formData.get('primaryColor'),
            accentColor2: formData.get('accentColor2'),
            accentColor3: formData.get('accentColor3')
        }
    };
    
    try {
        const result = await apiPost('save_settings', { settings: newSettings });
        
        if (result.success) {
            settings = result.settings;
            closeSettingsModal();
            showToast('success', 'Einstellungen gespeichert!');
            
            // Site-Name im Header aktualisieren
            document.querySelector('.site-name').textContent = settings.site?.title || '';
            refreshPreview();
        } else {
            showToast('error', result.error || 'Speichern fehlgeschlagen');
        }
    } catch (error) {
        console.error('Settings save error:', error);
        showToast('error', 'Netzwerkfehler beim Speichern');
    }
}

async function uploadHeaderImage(input) {
    if (!input.files[0]) return;
    
    const formData = new FormData();
    formData.append('action', 'upload_header');
    formData.append('file', input.files[0]);
    
    try {
        const result = await apiPostFormData(formData);
        
        if (result.success) {
            document.getElementById('headerImage').value = result.path;
            document.getElementById('headerPreview').innerHTML = 
                `<img src="${result.path}" alt="Header Preview">`;
            showToast('success', 'Header-Bild hochgeladen');
        } else {
            showToast('error', result.error || 'Upload fehlgeschlagen');
        }
    } catch (error) {
        console.error('Upload error:', error);
        showToast('error', 'Netzwerkfehler beim Upload');
    }
}

function removeHeaderImage() {
    document.getElementById('headerImage').value = '';
    document.getElementById('headerPreview').innerHTML = '';
}

// ===== File Browser =====
function openFileBrowser(type, targetInputId) {
    currentFileType = type;
    fileBrowserCallback = targetInputId;
    
    document.getElementById('fileBrowserModal').classList.add('active');
    
    // Accept-Attribut für Upload setzen und Button-Text aktualisieren
    updateFileUploadUI(type);
    
    // Tabs aktualisieren
    document.querySelectorAll('.file-browser-tabs .tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.type === type);
    });
    
    loadFiles(type);
}

// Aktualisiert Upload-Input und Button basierend auf Dateityp
function updateFileUploadUI(type) {
    const uploadInput = document.getElementById('fileBrowserUpload');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (type === 'images') {
        uploadInput.accept = 'image/jpeg,image/png,image/gif,image/webp';
        uploadBtn.textContent = '+ Neues Bild hochladen';
    } else {
        uploadInput.accept = '.pdf,.docx,.xlsx,.zip,.pptx,.txt';
        uploadBtn.textContent = '+ Neue Datei hochladen';
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
    if (!input.files[0]) return;
    
    const action = currentFileType === 'images' ? 'upload_image' : 'upload_download';
    
    const formData = new FormData();
    formData.append('action', action);
    formData.append('file', input.files[0]);
    
    try {
        const result = await apiPostFormData(formData);
        
        if (result.success) {
            // Datei direkt auswählen
            selectFile(result.path);
            showToast('success', 'Datei hochgeladen');
        } else {
            showToast('error', result.error || 'Upload fehlgeschlagen');
        }
    } catch (error) {
        console.error('Upload error:', error);
        showToast('error', 'Netzwerkfehler beim Upload');
    }
    
    // Input zurücksetzen für erneuten Upload
    input.value = '';
}

// ===== Publish & Preview =====
async function publishSite() {
    if (!confirm('Seite jetzt veröffentlichen? Die aktuelle Version wird überschrieben.')) return;
    
    const btn = document.querySelector('[onclick="publishSite()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner"></span> Wird veröffentlicht...';
    btn.disabled = true;
    
    try {
        const result = await apiPost('generate');
        
        if (result.success) {
            showToast('success', `Seite veröffentlicht! (${result.tilesCount} Kacheln)`);
            refreshPreview();
        } else {
            showToast('error', result.message || 'Veröffentlichen fehlgeschlagen');
        }
    } catch (error) {
        console.error('Publish error:', error);
        showToast('error', 'Netzwerkfehler beim Veröffentlichen');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

function openPreview() {
    // Wenn Preview bereits offen, fokussieren und refreshen
    if (previewWindow && !previewWindow.closed) {
        previewWindow.focus();
        previewWindow.location.reload();
        showToast('info', 'Preview aktualisiert');
    } else {
        // Neues Preview-Fenster öffnen
        previewWindow = window.open(`${API_URL}?action=preview`, 'InfoHubPreview');
        
        if (previewWindow) {
            showToast('info', 'Preview geöffnet - wird automatisch bei Änderungen aktualisiert');
        }
    }
}

// Refresh Preview-Window falls offen
function refreshPreview() {
    if (previewWindow && !previewWindow.closed) {
        try {
            previewWindow.location.reload();
        } catch (e) {
            // Cross-origin oder Window geschlossen
            previewWindow = null;
        }
    }
}

// ===== Toast Notifications =====
function showToast(type, message, duration = 4000) {
    const container = document.getElementById('toastContainer');
    
    const icons = {
        success: '✅',
        error: '❌',
        info: 'ℹ️'
    };
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${icons[type] || 'ℹ️'}</span>
        <span class="toast-message">${escapeHtml(message)}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100px)';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// ===== Session Timer mit Auto-Extend =====
let sessionWarningShown = false;
let sessionDialogActive = false;
let lastActivity = Date.now();

function startSessionTimer() {
    const timerEl = document.getElementById('sessionTimeDisplay');
    
    // Timeout-Werte aus CONFIG (in Sekunden)
    const sessionTimeout = (CONFIG?.sessionTimeout || 60) * 60; // Standard: 60 Minuten
    const warningBefore = (CONFIG?.sessionWarning || 5) * 60;   // Standard: 5 Minuten vorher
    const warningAt = sessionTimeout - warningBefore;
    
    console.log('Session Timer gestartet:', {
        timeout: sessionTimeout + 's',
        warningAt: warningAt + 's',
        warningBefore: warningBefore + 's'
    });
    
    // Activity Tracking - bei jeder Aktivität Session-Timer zurücksetzen
    const activityEvents = ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'];
    activityEvents.forEach(event => {
        document.addEventListener(event, () => {
            if (!sessionDialogActive) {
                lastActivity = Date.now();
                // Session via API verlängern (max alle 5 Minuten)
                extendSessionIfNeeded();
            }
        }, { passive: true });
    });
    
    setInterval(() => {
        // Prüfen ob kürzlich Aktivität war
        const inactiveSeconds = Math.floor((Date.now() - lastActivity) / 1000);
        
        // Warndialog anzeigen wenn Inaktivität den Warning-Threshold erreicht
        if (inactiveSeconds >= warningAt && !sessionDialogActive && !sessionWarningShown) {
            showSessionExpiryDialog();
            sessionWarningShown = true;
        }
        
        // Timer-Anzeige aktualisieren (basierend auf Inaktivität)
        const remainingFromInactivity = sessionTimeout - inactiveSeconds;
        const minutes = Math.max(0, Math.floor(remainingFromInactivity / 60));
        const seconds = Math.max(0, remainingFromInactivity % 60);
        
        if (timerEl) {
            // Unter 5 Minuten: auch Sekunden anzeigen
            if (remainingFromInactivity <= 300 && remainingFromInactivity > 0) {
                timerEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            } else {
                timerEl.textContent = `${minutes}min`;
            }
        }
        
        // Wenn Session komplett abgelaufen (+ 2 Min Puffer) und Dialog wurde ignoriert -> Logout
        if (inactiveSeconds >= sessionTimeout + 120 && sessionDialogActive) {
            forceLogout();
        }
    }, 1000);
}

let lastExtendCall = 0;
async function extendSessionIfNeeded() {
    // Max alle 5 Minuten
    if (Date.now() - lastExtendCall < 300000) return;
    lastExtendCall = Date.now();
    
    try {
        await apiPost('extend_session');
        sessionWarningShown = false;
    } catch (e) {
        console.warn('Session extend failed:', e);
    }
}

function showSessionExpiryDialog() {
    sessionDialogActive = true;
    let countdown = 120;
    
    // Dialog erstellen
    const dialog = document.createElement('div');
    dialog.className = 'session-dialog-overlay';
    dialog.innerHTML = `
        <div class="session-dialog">
            <h3>⏰ Session läuft ab</h3>
            <p>Deine Session läuft in <strong id="sessionCountdown">${countdown}</strong> Sekunden ab.</p>
            <p>Möchtest du eingeloggt bleiben?</p>
            <div class="session-dialog-actions">
                <button class="btn btn-secondary" onclick="forceLogout()">Ausloggen</button>
                <button class="btn btn-primary" onclick="continueSession()">Eingeloggt bleiben</button>
            </div>
        </div>
    `;
    document.body.appendChild(dialog);
    
    // Countdown
    const countdownEl = document.getElementById('sessionCountdown');
    const countdownInterval = setInterval(() => {
        countdown--;
        countdownEl.textContent = countdown;
        
        if (countdown <= 0) {
            clearInterval(countdownInterval);
            forceLogout();
        }
    }, 1000);
    
    dialog.dataset.interval = countdownInterval;
}

function continueSession() {
    const dialog = document.querySelector('.session-dialog-overlay');
    if (dialog) {
        clearInterval(Number(dialog.dataset.interval));
        dialog.remove();
    }
    sessionDialogActive = false;
    sessionWarningShown = false;
    lastActivity = Date.now();
    extendSessionIfNeeded();
    showToast('success', 'Session verlängert');
}

function forceLogout() {
    window.location.href = 'login.php?expired=1';
}

// ===== Utilities =====
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
