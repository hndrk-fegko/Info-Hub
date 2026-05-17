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
    let _parallaxQueued = false;
    
    function init() {
        // Listen for settings changes to update canvas header/footer
        V2State.on('state:settings-changed', onSettingsChanged);
        window.addEventListener('scroll', syncParallaxOffset, { passive: true });
        window.addEventListener('resize', syncParallaxOffset);
        onSettingsChanged({ settings: V2State.getSettings() });
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
        switchTab('design');
        
        // Highlight header & footer on canvas
        _highlightRegions(true);

        refreshNarrowSettingsUI();
        syncNarrowRangeLabels();
    }
    
    function buildModalHTML(settings) {
        const site = settings.site || {};
        const theme = settings.theme || {};
        const system = settings.system || {};
        
        const headerImage = site.headerImage || '';
        const focusPoint = site.headerFocusPoint || 'center center';
        const narrowLayout = theme.narrowLayout ? 'checked' : '';
        const narrowMode = normalizeOption(theme.narrowBackgroundMode, ['solid', 'gradient', 'image'], 'solid');
        const narrowBackgroundImage = theme.narrowBackgroundImage || '';
        const narrowOverlay = getNarrowOverlayState(theme);
        const overlayEnabled = narrowOverlay.enabled ? 'checked' : '';
        const overlayColorEnabled = narrowOverlay.colorEnabled ? 'checked' : '';
        const overlayBlurEnabled = narrowOverlay.blurEnabled ? 'checked' : '';
        const contentShadow = theme.narrowContentShadow === false ? '' : 'checked';
        
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

        const narrowModeOptions = [
            ['solid', 'Einfarbig'],
            ['gradient', 'Farbverlauf'],
            ['image', 'Bild']
        ].map(([value, label]) =>
            `<option value="${value}" ${narrowMode === value ? 'selected' : ''}>${label}</option>`
        ).join('');

        const imageDisplay = normalizeOption(theme.narrowBackgroundImageDisplay, ['cover', 'tile'], 'cover');
        const imageMotion = normalizeOption(theme.narrowBackgroundImageMotion, ['fixed', 'parallax'], 'fixed');
        const gradientAngle = clampNumber(theme.narrowGradientAngle, 0, 360, 180);
        const overlayOpacity = narrowOverlay.opacity;
        const overlayBlurStrength = narrowOverlay.blurStrength;
        
        return `
        <div class="v2-modal" style="max-width: 640px;">
            <div class="v2-modal-header">
                <h2>⚙️ Einstellungen</h2>
                <button class="v2-modal-close" onclick="V2Settings.close()">×</button>
            </div>
            <div class="v2-modal-body">
                <div class="v2-settings-tabs" role="tablist" aria-label="Einstellungsbereiche">
                    <button type="button" class="v2-settings-tab active" data-v2-settings-tab="design" onclick="V2Settings.switchTab('design')">Design</button>
                    <button type="button" class="v2-settings-tab" data-v2-settings-tab="system" onclick="V2Settings.switchTab('system')">System</button>
                </div>

                <div class="v2-settings-panel active" data-v2-settings-panel="design">
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
                            ${buildSettingsImagePreview(headerImage, 'Header', 150, 'Kein Header-Bild', focusPoint)}
                        </div>
                        <input type="hidden" id="v2SetHeaderPath" value="${escAttr(headerImage)}">
                        <div class="v2-upload-controls" style="margin-top:8px">
                            <button type="button" class="v2-btn v2-btn-secondary v2-btn-small" id="v2SetHeaderSelectBtn">Auswählen</button>
                            <button type="button" class="v2-btn v2-btn-secondary v2-btn-small" id="v2SetHeaderRemoveBtn" style="${headerImage ? '' : 'display:none'}">Entfernen</button>
                        </div>
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
                            Schmales Layout (begrenzte Breite mit eigener Hintergrundfläche)
                        </label>
                        <small class="v2-hint">Rendert die statische Seite zentriert mit eigener Backdrop-Fläche. Diese erweiterten Optionen gibt es nur im WYSIWYG.</small>
                    </div>
                    <div class="v2-field v2-narrow-width-field" id="v2NarrowWidthField" style="${narrowLayout ? '' : 'display:none'}">
                        <label class="v2-label">Maximale Breite: <span id="v2NarrowWidthValue">${theme.narrowWidth || 960}</span>px</label>
                        <input type="range" class="v2-range-input" id="v2SetNarrowWidth"
                               min="600" max="1400" step="20"
                               value="${theme.narrowWidth || 960}">
                        <div class="v2-range-labels"><span>600px</span><span>1400px</span></div>
                    </div>
                </fieldset>

                <fieldset class="v2-modal-fieldset" id="v2NarrowBackgroundFieldset" style="${narrowLayout ? '' : 'display:none'}">
                    <legend>Schmaler Hintergrund</legend>
                    <div class="v2-field">
                        <label class="v2-label">Modus</label>
                        <select class="v2-input" id="v2SetNarrowMode">
                            ${narrowModeOptions}
                        </select>
                    </div>

                    <div class="v2-settings-grid" id="v2NarrowSolidFields" style="${narrowMode === 'solid' ? '' : 'display:none'}">
                        <div class="v2-field">
                            <label class="v2-label">Fläche</label>
                            <input type="color" class="v2-color-input" id="v2SetNarrowBgColor"
                                   value="${sanitizeHexColor(theme.narrowBackgroundColor, '#1a1a2e')}">
                        </div>
                    </div>

                    <div class="v2-settings-grid" id="v2NarrowGradientFields" style="${narrowMode === 'gradient' ? '' : 'display:none'}">
                        <div class="v2-field">
                            <label class="v2-label">Farbe 1</label>
                            <input type="color" class="v2-color-input" id="v2SetNarrowGradient1"
                                   value="${sanitizeHexColor(theme.narrowGradientColor1, '#1a1a2e')}">
                        </div>
                        <div class="v2-field">
                            <label class="v2-label">Farbe 2</label>
                            <input type="color" class="v2-color-input" id="v2SetNarrowGradient2"
                                   value="${sanitizeHexColor(theme.narrowGradientColor2, '#16213e')}">
                        </div>
                        <div class="v2-field">
                            <label class="v2-label">Winkel: <span id="v2NarrowAngleValue">${gradientAngle}</span>°</label>
                            <input type="range" class="v2-range-input" id="v2SetNarrowGradientAngle"
                                   min="0" max="360" step="5" value="${gradientAngle}">
                        </div>
                    </div>

                    <div class="v2-settings-stack" id="v2NarrowImageFields" style="${narrowMode === 'image' ? '' : 'display:none'}">
                        <div class="v2-settings-subsection">
                            <div class="v2-settings-subsection__header">
                                <strong>Bild</strong>
                                <span>Diese Optionen gelten nur für den Bildmodus des schmalen Hintergrunds.</span>
                            </div>

                            <div class="v2-field">
                                <div class="v2-settings-media-preview" id="v2SetBackgroundPreview">
                                    ${buildSettingsImagePreview(narrowBackgroundImage, 'Hintergrundbild', 160, 'Kein Hintergrundbild')}
                                </div>
                                <input type="hidden" id="v2SetBackgroundPath" value="${escAttr(narrowBackgroundImage)}">
                                <div class="v2-upload-controls" style="margin-top:8px">
                                    <button type="button" class="v2-btn v2-btn-secondary v2-btn-small" id="v2SetBackgroundSelectBtn">Auswählen</button>
                                    <button type="button" class="v2-btn v2-btn-secondary v2-btn-small" id="v2SetBackgroundRemoveBtn" style="${narrowBackgroundImage ? '' : 'display:none'}">Entfernen</button>
                                </div>
                            </div>

                            <div class="v2-settings-grid">
                                <div class="v2-field">
                                    <label class="v2-label">Bilddarstellung</label>
                                    <select class="v2-input" id="v2SetNarrowImageDisplay">
                                        <option value="cover" ${imageDisplay === 'cover' ? 'selected' : ''}>Cover</option>
                                        <option value="tile" ${imageDisplay === 'tile' ? 'selected' : ''}>Gekachelt</option>
                                    </select>
                                </div>
                                <div class="v2-field">
                                    <label class="v2-label">Bildbewegung</label>
                                    <select class="v2-input" id="v2SetNarrowImageMotion">
                                        <option value="fixed" ${imageMotion === 'fixed' ? 'selected' : ''}>Fixiert</option>
                                        <option value="parallax" ${imageMotion === 'parallax' ? 'selected' : ''}>Parallax</option>
                                    </select>
                                </div>
                            </div>

                            <div class="v2-settings-subsection v2-settings-subsection--nested" id="v2NarrowOverlayGroup">
                                <div class="v2-settings-subsection__header">
                                    <strong>Overlay</strong>
                                    <span>Gleiche Mechanik wie beim Abschnittshintergrund: Farbe und Blur lassen sich getrennt aktivieren.</span>
                                </div>

                                <div class="v2-field">
                                    <label class="v2-checkbox-label">
                                        <input type="checkbox" id="v2SetNarrowOverlayEnabled" ${overlayEnabled}>
                                        Overlay aktivieren
                                    </label>
                                </div>

                                <div class="v2-toggle-panel ${narrowOverlay.colorEnabled ? 'v2-toggle-panel--active' : ''}" id="v2NarrowColorPanel" style="${narrowOverlay.enabled ? '' : 'display:none'}">
                                    <div class="v2-toggle-panel__toggle">
                                        <div class="v2-field">
                                            <label class="v2-checkbox-label">
                                                <input type="checkbox" id="v2SetNarrowOverlayColorEnabled" ${overlayColorEnabled}>
                                                Farbe
                                            </label>
                                        </div>
                                    </div>

                                    <div class="v2-toggle-panel__body">
                                        <div class="v2-settings-grid">
                                            <div class="v2-field">
                                                <label class="v2-label">Overlay-Farbe</label>
                                                <input type="color" class="v2-color-input" id="v2SetNarrowOverlayColor"
                                                       value="${sanitizeHexColor(theme.narrowBackgroundOverlayColor, '#000000')}">
                                            </div>
                                            <div class="v2-field">
                                                <label class="v2-label">Overlay-Deckkraft: <span id="v2NarrowOverlayOpacityValue">${overlayOpacity}</span>%</label>
                                                <input type="range" class="v2-range-input" id="v2SetNarrowOverlayOpacity"
                                                       min="0" max="100" step="1" value="${overlayOpacity}">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="v2-toggle-panel ${narrowOverlay.blurEnabled ? 'v2-toggle-panel--active' : ''}" id="v2NarrowBlurPanel" style="${narrowOverlay.enabled ? '' : 'display:none'}">
                                    <div class="v2-toggle-panel__toggle">
                                        <div class="v2-field">
                                            <label class="v2-checkbox-label">
                                                <input type="checkbox" id="v2SetNarrowOverlayBlurEnabled" ${overlayBlurEnabled}>
                                                Blur
                                            </label>
                                        </div>
                                    </div>

                                    <div class="v2-toggle-panel__body">
                                        <div class="v2-field">
                                            <label class="v2-label">Blur-Stärke: <span id="v2NarrowOverlayBlurStrengthValue">${overlayBlurStrength}</span>%</label>
                                            <input type="range" class="v2-range-input" id="v2SetNarrowOverlayBlurStrength"
                                                   min="0" max="100" step="1" value="${overlayBlurStrength}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="v2-settings-subsection" id="v2NarrowGeneralFields" style="${narrowLayout ? '' : 'display:none'}">
                        <div class="v2-settings-subsection__header">
                            <strong>Allgemein</strong>
                            <span>Diese Optionen gelten für den schmalen Modus unabhängig vom gewählten Hintergrund.</span>
                        </div>

                        <div class="v2-field">
                            <label class="v2-checkbox-label">
                                <input type="checkbox" id="v2SetNarrowShadow" ${contentShadow}>
                                Schatten für Inhaltsbereich
                            </label>
                        </div>
                    </div>
                </fieldset>
                </div>
                
                <div class="v2-settings-panel" data-v2-settings-panel="system">
                <fieldset class="v2-modal-fieldset">
                    <legend>System-Mail</legend>
                    <div class="v2-field">
                        <label class="v2-label">Absender-Adresse</label>
                        <input type="email" class="v2-input" id="v2SetMailFromAddress"
                               value="${escAttr(system.mailFromAddress || '')}"
                               placeholder="noreply@example.com" required>
                        <small class="v2-hint">Für Login-Codes und Einladungen. Empfehlung: Hauptdomain ohne Subdomain verwenden.</small>
                    </div>
                </fieldset>

                <!-- Admin-Benutzer -->
                <fieldset class="v2-modal-fieldset">
                    <legend>Admin-Benutzer</legend>
                    <div class="v2-field">
                        <div class="v2-admin-list" id="v2AdminList">
                            <span class="v2-hint">Lade...</span>
                        </div>
                    </div>
                    <div class="v2-field">
                        <div class="v2-admin-invite" id="v2AdminInvite">
                            <input type="email" class="v2-input" id="v2InviteEmail" 
                                   placeholder="Email-Adresse einladen" style="flex:1">
                            <button type="button" class="v2-btn v2-btn-secondary v2-btn-small" 
                                    onclick="V2Settings.inviteAdmin()">➕ Einladen</button>
                        </div>
                        <small class="v2-hint">Die eingeladene Person kann sich beim nächsten Login automatisch anmelden</small>
                    </div>
                </fieldset>
                </div>
            </div>
            <div class="v2-modal-footer">
                <button class="v2-btn v2-btn-secondary" onclick="V2Settings.close()">Abbrechen</button>
                <button class="v2-btn v2-btn-primary" onclick="V2Settings.save()">💾 Speichern</button>
            </div>
        </div>`;
    }

    function buildSettingsImagePreview(path, alt, maxHeight, emptyText, objectPosition) {
        if (!path) {
            return `<span class="v2-hint">${escHTML(emptyText)}</span>`;
        }

        const imageStyle = [
            'max-width:100%',
            `max-height:${maxHeight}px`,
            'border-radius:8px'
        ];

        if (objectPosition) {
            imageStyle.push(`object-position:${escAttr(objectPosition)}`);
        }

        return `<img src="${escAttr(path)}" alt="${escAttr(alt)}" style="${imageStyle.join(';')}">`;
    }

    function getNarrowOverlayState(theme) {
        const overlayEnabled = !!theme.narrowBackgroundOverlayEnabled;
        const overlayColorEnabled = overlayEnabled
            && (!Object.prototype.hasOwnProperty.call(theme, 'narrowBackgroundOverlayColorEnabled')
                || !!theme.narrowBackgroundOverlayColorEnabled);
        const overlayBlurEnabled = overlayEnabled && !!theme.narrowBackgroundOverlayBlurEnabled;

        return {
            enabled: overlayEnabled,
            colorEnabled: overlayColorEnabled,
            blurEnabled: overlayBlurEnabled,
            color: sanitizeHexColor(theme.narrowBackgroundOverlayColor, '#000000'),
            opacity: clampNumber(theme.narrowBackgroundOverlayOpacity, 0, 100, 35),
            blurStrength: clampNumber(theme.narrowBackgroundOverlayBlurStrength, 0, 100, 24)
        };
    }
    
    function wireEvents(settings) {
        document.getElementById('v2SetHeaderSelectBtn')?.addEventListener('click', selectHeader);
        document.getElementById('v2SetHeaderRemoveBtn')?.addEventListener('click', removeHeader);
        document.getElementById('v2SetBackgroundSelectBtn')?.addEventListener('click', selectBackground);
        document.getElementById('v2SetBackgroundRemoveBtn')?.addEventListener('click', removeBackground);
        document.getElementById('v2SetFocusPoint')?.addEventListener('change', () => {
            updateHeaderPreview(document.getElementById('v2SetHeaderPath')?.value || '');
        });

        ['v2SetNarrowLayout', 'v2SetNarrowMode', 'v2SetNarrowOverlayEnabled', 'v2SetNarrowOverlayColorEnabled', 'v2SetNarrowOverlayBlurEnabled'].forEach((id) => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('change', refreshNarrowSettingsUI);
            }
        });

        ['v2SetNarrowWidth', 'v2SetNarrowGradientAngle', 'v2SetNarrowOverlayOpacity', 'v2SetNarrowOverlayBlurStrength'].forEach((id) => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('input', syncNarrowRangeLabels);
            }
        });
        
        // Load admin list
        loadAdminList();
        
        // Invite on Enter key
        const inviteInput = document.getElementById('v2InviteEmail');
        if (inviteInput) {
            inviteInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    inviteAdmin();
                }
            });
        }
    }

    async function selectHeader() {
        const pathInput = document.getElementById('v2SetHeaderPath');
        const selectedPath = await V2MediaPicker.pickImage({
            title: 'Header-Bild auswählen',
            mediaType: 'header',
            uploadAction: 'header',
            currentPath: pathInput?.value || ''
        });

        if (!selectedPath || !pathInput) {
            return;
        }

        pathInput.value = selectedPath;
        updateHeaderPreview(selectedPath);
    }

    async function selectBackground() {
        const pathInput = document.getElementById('v2SetBackgroundPath');
        const selectedPath = await V2MediaPicker.pickImage({
            title: 'Hintergrundbild auswählen',
            mediaType: 'backgrounds',
            uploadAction: 'background',
            currentPath: pathInput?.value || ''
        });

        if (!selectedPath || !pathInput) {
            return;
        }

        pathInput.value = selectedPath;
        updateBackgroundPreview(selectedPath);
    }

    function updateHeaderPreview(path) {
        const preview = document.getElementById('v2SetHeaderPreview');
        const removeBtn = document.getElementById('v2SetHeaderRemoveBtn');
        const focusPoint = document.getElementById('v2SetFocusPoint')?.value || 'center center';

        if (preview) {
            preview.innerHTML = buildSettingsImagePreview(path, 'Header', 150, 'Kein Header-Bild', focusPoint);
        }
        if (removeBtn) {
            removeBtn.style.display = path ? 'inline-flex' : 'none';
        }
    }

    function updateBackgroundPreview(path) {
        const preview = document.getElementById('v2SetBackgroundPreview');
        const removeBtn = document.getElementById('v2SetBackgroundRemoveBtn');

        if (preview) {
            preview.innerHTML = buildSettingsImagePreview(path, 'Hintergrundbild', 160, 'Kein Hintergrundbild');
        }
        if (removeBtn) {
            removeBtn.style.display = path ? 'inline-flex' : 'none';
        }
    }
    
    function removeHeader() {
        document.getElementById('v2SetHeaderPath').value = '';
        updateHeaderPreview('');
    }

    function removeBackground() {
        document.getElementById('v2SetBackgroundPath').value = '';
        updateBackgroundPreview('');
    }

    function switchTab(tabName) {
        document.querySelectorAll('[data-v2-settings-tab]').forEach((tab) => {
            tab.classList.toggle('active', tab.dataset.v2SettingsTab === tabName);
        });

        document.querySelectorAll('[data-v2-settings-panel]').forEach((panel) => {
            panel.classList.toggle('active', panel.dataset.v2SettingsPanel === tabName);
        });
    }
    
    async function save() {
        const narrowBackgroundImage = document.getElementById('v2SetBackgroundPath')?.value || null;

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
                narrowLayout: document.getElementById('v2SetNarrowLayout').checked,
                narrowWidth: parseInt(document.getElementById('v2SetNarrowWidth').value, 10) || 960,
                narrowBackgroundMode: document.getElementById('v2SetNarrowMode').value,
                narrowBackgroundColor: document.getElementById('v2SetNarrowBgColor').value,
                narrowGradientColor1: document.getElementById('v2SetNarrowGradient1').value,
                narrowGradientColor2: document.getElementById('v2SetNarrowGradient2').value,
                narrowGradientAngle: parseInt(document.getElementById('v2SetNarrowGradientAngle').value, 10) || 180,
                narrowBackgroundImage,
                narrowBackgroundImageDisplay: document.getElementById('v2SetNarrowImageDisplay').value,
                narrowBackgroundImageMotion: document.getElementById('v2SetNarrowImageMotion').value,
                narrowBackgroundOverlayEnabled: document.getElementById('v2SetNarrowOverlayEnabled').checked,
                narrowBackgroundOverlayColorEnabled: document.getElementById('v2SetNarrowOverlayColorEnabled').checked,
                narrowBackgroundOverlayColor: document.getElementById('v2SetNarrowOverlayColor').value,
                narrowBackgroundOverlayOpacity: parseInt(document.getElementById('v2SetNarrowOverlayOpacity').value, 10) || 0,
                narrowBackgroundOverlayBlurEnabled: document.getElementById('v2SetNarrowOverlayBlurEnabled').checked,
                narrowBackgroundOverlayBlurStrength: parseInt(document.getElementById('v2SetNarrowOverlayBlurStrength').value, 10) || 0,
                narrowContentShadow: document.getElementById('v2SetNarrowShadow').checked
            },
            system: {
                mailFromAddress: document.getElementById('v2SetMailFromAddress').value.trim()
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
        _highlightRegions(false);
        if (_overlay) {
            _overlay.remove();
            _overlay = null;
        }
    }
    
    function _highlightRegions(on) {
        document.querySelectorAll('.v2-editable-region').forEach(el => {
            el.classList.toggle('v2-settings-highlight', on);
        });
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
            applyNarrowPreview(theme);
        }
    }

    function refreshNarrowSettingsUI() {
        const narrowEnabled = !!document.getElementById('v2SetNarrowLayout')?.checked;
        const mode = document.getElementById('v2SetNarrowMode')?.value || 'solid';
        const overlayEnabled = !!document.getElementById('v2SetNarrowOverlayEnabled')?.checked;
        const overlayColorEnabled = !!document.getElementById('v2SetNarrowOverlayColorEnabled')?.checked;
        const overlayBlurEnabled = !!document.getElementById('v2SetNarrowOverlayBlurEnabled')?.checked;

        toggleDisplay('v2NarrowWidthField', narrowEnabled);
        toggleDisplay('v2NarrowBackgroundFieldset', narrowEnabled);
        toggleDisplay('v2NarrowSolidFields', narrowEnabled && mode === 'solid');
        toggleDisplay('v2NarrowGradientFields', narrowEnabled && mode === 'gradient');
        toggleDisplay('v2NarrowImageFields', narrowEnabled && mode === 'image');
        toggleDisplay('v2NarrowGeneralFields', narrowEnabled);
        toggleDisplay('v2NarrowOverlayGroup', narrowEnabled && mode === 'image');
        toggleDisplay('v2NarrowColorPanel', narrowEnabled && mode === 'image' && overlayEnabled);
        toggleDisplay('v2NarrowBlurPanel', narrowEnabled && mode === 'image' && overlayEnabled);
        togglePanelState('v2NarrowColorPanel', narrowEnabled && mode === 'image' && overlayEnabled && overlayColorEnabled);
        togglePanelState('v2NarrowBlurPanel', narrowEnabled && mode === 'image' && overlayEnabled && overlayBlurEnabled);
    }

    function togglePanelState(id, active) {
        const panel = document.getElementById(id);
        if (panel) {
            panel.classList.toggle('v2-toggle-panel--active', !!active);
        }
    }

    function syncNarrowRangeLabels() {
        const widthValue = document.getElementById('v2NarrowWidthValue');
        const widthRange = document.getElementById('v2SetNarrowWidth');
        if (widthValue && widthRange) {
            widthValue.textContent = widthRange.value;
        }

        const angleValue = document.getElementById('v2NarrowAngleValue');
        const angleRange = document.getElementById('v2SetNarrowGradientAngle');
        if (angleValue && angleRange) {
            angleValue.textContent = angleRange.value;
        }

        const opacityValue = document.getElementById('v2NarrowOverlayOpacityValue');
        const opacityRange = document.getElementById('v2SetNarrowOverlayOpacity');
        if (opacityValue && opacityRange) {
            opacityValue.textContent = opacityRange.value;
        }

        const blurValue = document.getElementById('v2NarrowOverlayBlurStrengthValue');
        const blurRange = document.getElementById('v2SetNarrowOverlayBlurStrength');
        if (blurValue && blurRange) {
            blurValue.textContent = blurRange.value;
        }
    }

    function toggleDisplay(id, visible) {
        const element = document.getElementById(id);
        if (element) {
            element.style.display = visible ? '' : 'none';
        }
    }

    function applyNarrowPreview(theme) {
        const wrapper = document.querySelector('.v2-canvas-wrapper');
        const canvas = document.getElementById('wysiwyg-canvas');
        if (!wrapper || !canvas) return;

        const narrowLayout = !!theme.narrowLayout;
        const mode = normalizeOption(theme.narrowBackgroundMode, ['solid', 'gradient', 'image'], 'solid');
        const imagePath = typeof theme.narrowBackgroundImage === 'string' ? theme.narrowBackgroundImage : '';
        const imageDisplay = normalizeOption(theme.narrowBackgroundImageDisplay, ['cover', 'tile'], 'cover');
        const imageMotion = normalizeOption(theme.narrowBackgroundImageMotion, ['fixed', 'parallax'], 'fixed');
        const overlay = getNarrowOverlayState(theme);
        const width = clampNumber(theme.narrowWidth, 600, 1400, 960);
        const hasImage = mode === 'image' && imagePath;
        const contentShadow = theme.narrowContentShadow === false ? 'none' : '0 0 60px rgba(0,0,0,0.4)';
        const blurPx = overlay.blurEnabled ? ((overlay.blurStrength / 100) * 24) : 0;
        const baseScale = overlay.blurEnabled ? (1 + (overlay.blurStrength / 1000)) : 1;
        const parallaxScale = overlay.blurEnabled ? (1.08 + (overlay.blurStrength / 1000)) : 1.08;

        wrapper.classList.toggle('v2-canvas-wrapper--narrow', narrowLayout);
        wrapper.classList.toggle('v2-canvas-wrapper--parallax', narrowLayout && hasImage && imageMotion === 'parallax');
        wrapper.style.setProperty('--v2-narrow-width', `${width}px`);
        wrapper.style.setProperty('--v2-narrow-backdrop', buildNarrowBackdrop(theme));
        wrapper.style.setProperty('--v2-narrow-background-repeat', imageDisplay === 'tile' ? 'repeat' : 'no-repeat');
        wrapper.style.setProperty('--v2-narrow-background-size', imageDisplay === 'tile' ? 'auto' : 'cover');
        wrapper.style.setProperty('--v2-narrow-background-attachment', imageMotion === 'fixed' ? 'fixed' : 'scroll');
        wrapper.style.setProperty('--v2-narrow-background-blur', `${narrowLayout && hasImage ? blurPx : 0}px`);
        wrapper.style.setProperty('--v2-narrow-background-scale', String(narrowLayout && hasImage ? baseScale : 1));
        wrapper.style.setProperty('--v2-narrow-background-parallax-scale', String(narrowLayout && hasImage ? parallaxScale : 1.08));
        wrapper.style.setProperty('--v2-narrow-overlay-display', narrowLayout && hasImage && overlay.colorEnabled ? 'block' : 'none');
        wrapper.style.setProperty('--v2-narrow-overlay-color', overlay.color);
        wrapper.style.setProperty('--v2-narrow-overlay-opacity', String(narrowLayout && hasImage && overlay.colorEnabled ? (overlay.opacity / 100) : 0));
        canvas.style.boxShadow = narrowLayout ? contentShadow : '';

        syncParallaxOffset();
    }

    function syncParallaxOffset() {
        if (_parallaxQueued) return;

        _parallaxQueued = true;
        requestAnimationFrame(() => {
            _parallaxQueued = false;
            const wrapper = document.querySelector('.v2-canvas-wrapper');
            if (!wrapper) return;

            if (!wrapper.classList.contains('v2-canvas-wrapper--parallax')) {
                wrapper.style.setProperty('--v2-parallax-offset', '0px');
                return;
            }

            const rect = wrapper.getBoundingClientRect();
            const viewportCenter = window.innerHeight / 2;
            const wrapperCenter = rect.top + rect.height / 2;
            const offset = Math.round((viewportCenter - wrapperCenter) * 0.08);
            wrapper.style.setProperty('--v2-parallax-offset', `${offset}px`);
        });
    }

    function normalizeOption(value, allowed, fallback) {
        return allowed.includes(value) ? value : fallback;
    }

    function clampNumber(value, min, max, fallback) {
        const parsed = Number.parseInt(value, 10);
        if (Number.isNaN(parsed)) return fallback;
        return Math.min(max, Math.max(min, parsed));
    }

    function sanitizeHexColor(value, fallback) {
        return /^#[0-9a-f]{6}$/i.test(String(value || '')) ? String(value) : fallback;
    }

    function buildNarrowBackdrop(theme) {
        const mode = normalizeOption(theme.narrowBackgroundMode, ['solid', 'gradient', 'image'], 'solid');
        if (mode === 'gradient') {
            const angle = clampNumber(theme.narrowGradientAngle, 0, 360, 180);
            const color1 = sanitizeHexColor(theme.narrowGradientColor1, '#1a1a2e');
            const color2 = sanitizeHexColor(theme.narrowGradientColor2, '#16213e');
            return `linear-gradient(${angle}deg, ${color1}, ${color2})`;
        }

        if (mode === 'image' && typeof theme.narrowBackgroundImage === 'string' && theme.narrowBackgroundImage) {
            const safePath = theme.narrowBackgroundImage.replace(/'/g, '%27');
            return `url('${safePath}')`;
        }

        return sanitizeHexColor(theme.narrowBackgroundColor, '#1a1a2e');
    }
    
    // ===== Admin Management =====
    
    let _adminEmails = [];
    let _adminInvites = [];
    
    async function loadAdminList() {
        const listEl = document.getElementById('v2AdminList');
        if (!listEl) return;
        
        listEl.innerHTML = '<span class="v2-hint">Lade...</span>';
        
        try {
            const result = await V2Api.post('get_admins');
            if (result.success) {
                _adminEmails = result.emails || [];
                _adminInvites = result.invites || [];
                renderAdminList();
            } else {
                listEl.innerHTML = '<span class="v2-hint">Keine Daten verfügbar</span>';
            }
        } catch(err) {
            console.error('[Settings] Admin load failed:', err);
            listEl.innerHTML = '<span class="v2-hint">Fehler beim Laden</span>';
        }
    }
    
    function renderAdminList() {
        const listEl = document.getElementById('v2AdminList');
        if (!listEl) return;
        
        if (_adminEmails.length === 0 && _adminInvites.length === 0) {
            listEl.innerHTML = '<span class="v2-hint">Keine Admins gefunden</span>';
            return;
        }
        
        const canRemove = _adminEmails.length > 1;
        
        const emailItems = _adminEmails.map(email => {
            const disabledAttr = canRemove ? '' : 'disabled';
            const title = canRemove ? 'Admin entfernen' : 'Letzte Admin-Adresse kann nicht gelöscht werden';
            return `<div class="v2-admin-item">
                <span>${escHTML(email)}</span>
                <button type="button" class="v2-admin-remove" ${disabledAttr} title="${title}"
                        onclick="V2Settings.removeAdminEmail('${escAttr(email)}')">✕</button>
            </div>`;
        }).join('');
        
        const inviteItems = _adminInvites.map(invite => {
            const email = invite.email || '';
            return `<div class="v2-admin-item v2-admin-pending" title="Ausstehend – Einladung wartet auf Login">
                <span>${escHTML(email)} (ausstehend)</span>
                <button type="button" class="v2-admin-remove" title="Einladung löschen"
                        onclick="V2Settings.removeAdminInvite('${escAttr(email)}')">✕</button>
            </div>`;
        }).join('');
        
        listEl.innerHTML = emailItems + inviteItems;
    }
    
    async function inviteAdmin() {
        const input = document.getElementById('v2InviteEmail');
        const email = input?.value?.trim();
        if (!email) return;
        
        try {
            const result = await V2Api.post('invite_admin', { email });
            if (result.success) {
                _adminEmails = result.emails || _adminEmails;
                _adminInvites = result.invites || _adminInvites;
                renderAdminList();
                input.value = '';
                V2.toast(result.message || 'Einladung gesendet', 'success');
            } else {
                V2.toast(result.message || 'Einladung fehlgeschlagen', 'error');
            }
        } catch(err) {
            console.error('[Settings] Invite failed:', err);
            V2.toast('Netzwerkfehler beim Einladen', 'error');
        }
    }
    
    async function removeAdminEmail(email) {
        if (!confirm('Admin "' + email + '" wirklich entfernen?')) return;
        
        try {
            const result = await V2Api.post('remove_admin_email', { email });
            if (result.success) {
                _adminEmails = result.emails || _adminEmails;
                _adminInvites = result.invites || _adminInvites;
                renderAdminList();
                V2.toast(result.message || 'Admin entfernt', 'success');
            } else {
                V2.toast(result.message || 'Entfernen fehlgeschlagen', 'error');
            }
        } catch(err) {
            console.error('[Settings] Remove admin failed:', err);
            V2.toast('Netzwerkfehler beim Entfernen', 'error');
        }
    }
    
    async function removeAdminInvite(email) {
        try {
            const result = await V2Api.post('remove_admin_invite', { email });
            if (result.success) {
                _adminEmails = result.emails || _adminEmails;
                _adminInvites = result.invites || _adminInvites;
                renderAdminList();
                V2.toast(result.message || 'Einladung entfernt', 'success');
            } else {
                V2.toast(result.message || 'Entfernen fehlgeschlagen', 'error');
            }
        } catch(err) {
            console.error('[Settings] Remove invite failed:', err);
            V2.toast('Netzwerkfehler beim Entfernen', 'error');
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
        switchTab,
        removeHeader,
        removeBackground,
        inviteAdmin,
        removeAdminEmail,
        removeAdminInvite
    };
})();
