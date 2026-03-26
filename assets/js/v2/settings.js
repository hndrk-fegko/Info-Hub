/**
 * V2 Settings Modal - Einstellungen für den WYSIWYG Editor
 * 
 * Handles: Site title, page title, footer, header image + focus point,
 *          theme colors, narrow layout option.
 * 
 * Dependencies: V2State, V2Api, V2 (toast)
 */

window.V2Settings = (function() {
    'use strict';
    
    let _overlay = null;
    
    function init() {
        // Listen for settings changes to update canvas header/footer
        V2State.on('state:settings-changed', onSettingsChanged);
    }
    
    /**
     * Öffnet das Settings-Modal mit aktuellen Werten
     */
    function open() {
        const settings = V2State.getSettings();
        
        // Build modal HTML
        const html = buildModalHTML(settings);
        
        // Create overlay
        _overlay = document.createElement('div');
        _overlay.className = 'v2-modal-overlay';
        _overlay.innerHTML = html;
        document.body.appendChild(_overlay);
        
        // Close on overlay click
        _overlay.addEventListener('click', function(e) {
            if (e.target === _overlay) close();
        });
        
        // Close on Escape
        _overlay.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') close();
        });
        
        // Wire up events
        wireEvents(settings);
    }
    
    function buildModalHTML(settings) {
        const site = settings.site || {};
        const theme = settings.theme || {};
        
        const headerImage = site.headerImage || '';
        const focusPoint = site.headerFocusPoint || 'center center';
        const narrowLayout = theme.narrowLayout ? 'checked' : '';
        
        const focusOptions = [
            ['center center', 'Mitte'],
            ['center top', 'Oben'],
            ['center bottom', 'Unten'],
            ['left center', 'Links'],
            ['right center', 'Rechts'],
            ['left top', 'Oben Links'],
            ['right top', 'Oben Rechts'],
            ['left bottom', 'Unten Links'],
            ['right bottom', 'Unten Rechts']
        ];
        
        const focusOptionsHTML = focusOptions.map(([val, label]) =>
            `<option value="${val}" ${focusPoint === val ? 'selected' : ''}>${label}</option>`
        ).join('');
        
        return `
        <div class="v2-modal" style="max-width: 640px;">
            <div class="v2-modal-header">
                <h2>⚙️ Einstellungen</h2>
                <button class="v2-modal-close" onclick="V2Settings.close()">×</button>
            </div>
            <div class="v2-modal-body">
                <!-- Seite -->
                <fieldset class="v2-modal-fieldset">
                    <legend>Seite</legend>
                    
                    <div class="v2-field">
                        <label class="v2-label">Seitentitel</label>
                        <input type="text" class="v2-input" id="v2SetTitle" 
                               value="${escAttr(site.title || '')}" 
                               placeholder="Wird im Header angezeigt">
                        <small class="v2-hint">Leer lassen für nur Header-Bild</small>
                    </div>
                    
                    <div class="v2-field">
                        <label class="v2-label">Browser-Tab Titel</label>
                        <input type="text" class="v2-input" id="v2SetPageTitle" 
                               value="${escAttr(site.pageTitle || '')}" 
                               placeholder="${escAttr(site.title || 'Info-Hub')}">
                        <small class="v2-hint">Leer = Seitentitel wird verwendet</small>
                    </div>
                    
                    <div class="v2-field">
                        <label class="v2-label">Footer-Text</label>
                        <textarea class="v2-input" id="v2SetFooter" rows="2" 
                                  placeholder="© 2026 ...">${escHTML(site.footerText || '')}</textarea>
                        <small class="v2-hint">Mehrzeilig möglich. Leer = kein Footer</small>
                    </div>
                </fieldset>
                
                <!-- Header-Bild -->
                <fieldset class="v2-modal-fieldset">
                    <legend>Header-Bild</legend>
                    
                    <div class="v2-field">
                        <div class="v2-settings-header-preview" id="v2SetHeaderPreview">
                            ${headerImage 
                                ? `<img src="${escAttr(headerImage)}" alt="Header" style="max-width:100%;max-height:150px;border-radius:8px;object-position:${focusPoint}">
                                   <button type="button" class="v2-btn v2-btn-secondary v2-btn-small" onclick="V2Settings.removeHeader()">Entfernen</button>`
                                : '<span class="v2-hint">Kein Header-Bild</span>'
                            }
                        </div>
                        <input type="hidden" id="v2SetHeaderPath" value="${escAttr(headerImage)}">
                        <input type="file" id="v2SetHeaderFile" accept="image/*" style="margin-top:8px">
                    </div>
                    
                    <div class="v2-field">
                        <label class="v2-label">Bild-Fokuspunkt</label>
                        <select class="v2-input" id="v2SetFocusPoint">
                            ${focusOptionsHTML}
                        </select>
                        <small class="v2-hint">Bestimmt, welcher Bildbereich beim Zuschneiden sichtbar bleibt</small>
                    </div>
                </fieldset>
                
                <!-- Farben -->
                <fieldset class="v2-modal-fieldset">
                    <legend>Farben</legend>
                    <div class="v2-settings-colors">
                        <div class="v2-field">
                            <label class="v2-label">Hintergrund</label>
                            <input type="color" class="v2-color-input" id="v2SetBgColor" 
                                   value="${theme.backgroundColor || '#f5f5f5'}">
                        </div>
                        <div class="v2-field">
                            <label class="v2-label">Akzent 1</label>
                            <input type="color" class="v2-color-input" id="v2SetAccent1" 
                                   value="${theme.accentColor || '#667eea'}">
                        </div>
                        <div class="v2-field">
                            <label class="v2-label">Akzent 2</label>
                            <input type="color" class="v2-color-input" id="v2SetAccent2" 
                                   value="${theme.accentColor2 || '#48bb78'}">
                        </div>
                        <div class="v2-field">
                            <label class="v2-label">Akzent 3</label>
                            <input type="color" class="v2-color-input" id="v2SetAccent3" 
                                   value="${theme.accentColor3 || '#ed8936'}">
                        </div>
                    </div>
                </fieldset>
                
                <!-- Layout -->
                <fieldset class="v2-modal-fieldset">
                    <legend>Layout</legend>
                    <div class="v2-field">
                        <label class="v2-checkbox-label">
                            <input type="checkbox" id="v2SetNarrowLayout" ${narrowLayout}>
                            Schmales Layout (begrenzte Breite mit dunklem Hintergrund)
                        </label>
                        <small class="v2-hint">Rendert die statische Seite zentriert mit begrenzter Breite, ähnlich der Editor-Ansicht</small>
                    </div>
                </fieldset>
            </div>
            <div class="v2-modal-footer">
                <button class="v2-btn v2-btn-secondary" onclick="V2Settings.close()">Abbrechen</button>
                <button class="v2-btn v2-btn-primary" onclick="V2Settings.save()">💾 Speichern</button>
            </div>
        </div>`;
    }
    
    function wireEvents(settings) {
        // Header file upload
        const fileInput = document.getElementById('v2SetHeaderFile');
        if (fileInput) {
            fileInput.addEventListener('change', async function() {
                if (!this.files[0]) return;
                V2.toast('Header-Bild wird hochgeladen...', 'info');
                try {
                    const result = await V2Api.uploadHeader(this.files[0]);
                    if (result.success) {
                        document.getElementById('v2SetHeaderPath').value = result.path;
                        const preview = document.getElementById('v2SetHeaderPreview');
                        preview.innerHTML = `
                            <img src="${result.path}" alt="Header" style="max-width:100%;max-height:150px;border-radius:8px;">
                            <button type="button" class="v2-btn v2-btn-secondary v2-btn-small" onclick="V2Settings.removeHeader()">Entfernen</button>`;
                        V2.toast('Header-Bild hochgeladen', 'success');
                    } else {
                        V2.toast('Upload fehlgeschlagen: ' + (result.error || ''), 'error');
                    }
                } catch(err) {
                    console.error('[Settings] Header upload failed:', err);
                    V2.toast('Upload fehlgeschlagen', 'error');
                }
            });
        }
    }
    
    function removeHeader() {
        document.getElementById('v2SetHeaderPath').value = '';
        const fileInput = document.getElementById('v2SetHeaderFile');
        if (fileInput) fileInput.value = '';
        const preview = document.getElementById('v2SetHeaderPreview');
        if (preview) preview.innerHTML = '<span class="v2-hint">Kein Header-Bild</span>';
    }
    
    async function save() {
        const newSettings = {
            site: {
                title: document.getElementById('v2SetTitle').value.trim(),
                pageTitle: document.getElementById('v2SetPageTitle').value.trim(),
                headerImage: document.getElementById('v2SetHeaderPath').value || null,
                headerFocusPoint: document.getElementById('v2SetFocusPoint').value,
                footerText: document.getElementById('v2SetFooter').value.trim()
            },
            theme: {
                backgroundColor: document.getElementById('v2SetBgColor').value,
                accentColor: document.getElementById('v2SetAccent1').value,
                accentColor2: document.getElementById('v2SetAccent2').value,
                accentColor3: document.getElementById('v2SetAccent3').value,
                narrowLayout: document.getElementById('v2SetNarrowLayout').checked
            }
        };
        
        try {
            const result = await V2Api.saveSettings(newSettings);
            if (result.success) {
                V2State.setSettings(result.settings || newSettings);
                close();
                V2.toast('Einstellungen gespeichert!', 'success');
                
                // Refresh canvas to show updated header/footer/colors
                // Full page reload ensures PHP re-renders header/footer correctly
                window.location.reload();
            } else {
                V2.toast('Speichern fehlgeschlagen: ' + (result.error || ''), 'error');
            }
        } catch(err) {
            console.error('[Settings] save failed:', err);
            V2.toast('Speichern fehlgeschlagen', 'error');
        }
    }
    
    function close() {
        if (_overlay) {
            _overlay.remove();
            _overlay = null;
        }
    }
    
    function onSettingsChanged(data) {
        // Update site name in toolbar
        const siteNameEl = document.querySelector('.v2-site-name');
        if (siteNameEl && data.settings?.site?.title !== undefined) {
            siteNameEl.textContent = data.settings.site.title;
        }
        
        // Update CSS variables
        const theme = data.settings?.theme;
        if (theme) {
            const root = document.documentElement;
            if (theme.backgroundColor) root.style.setProperty('--bg-color', theme.backgroundColor);
            if (theme.accentColor) root.style.setProperty('--accent-color', theme.accentColor);
            if (theme.accentColor2) root.style.setProperty('--accent-color-2', theme.accentColor2);
            if (theme.accentColor3) root.style.setProperty('--accent-color-3', theme.accentColor3);
        }
    }
    
    // Helpers
    function escAttr(str) {
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    
    function escHTML(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    
    return {
        init,
        open,
        close,
        save,
        removeHeader
    };
})();
