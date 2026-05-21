import V2State from './state.js';
import V2Api from './api-client.js';
import V2MediaPicker from './media-picker.js';
import { showToast } from './toast.js';

/**
 * V2 Settings Modal - Einstellungen für den WYSIWYG Editor
 * 
 * Handles: Site title, page title, footer, header image + focus point,
 *          theme colors, narrow layout option.
 * 
 * Dependencies: V2State, V2Api, V2 (toast)
 */

const V2Settings = (function() {
    'use strict';
    
    let _overlay = null;
    let _parallaxQueued = false;
    let _parallaxOffsetCurrent = 0;
    let _scrollLockY = 0;
    let _scrollLockPaddingRight = '';
    let _restoreFocusEl = null;
    let _isSaving = false;
    const _imageColorCache = new Map();
    let _narrowColorRequestToken = 0;
    
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
        if (_overlay) {
            focusPrimaryField();
            return;
        }

        const settings = V2State.getSettings();
        _restoreFocusEl = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        
        // Build modal HTML
        const html = buildModalHTML(settings);
        
        // Create overlay
        _overlay = document.createElement('div');
        _overlay.className = 'v2-modal-overlay';
        _overlay.innerHTML = html;
        document.body.appendChild(_overlay);
        lockPageScroll();
        
        // Close on overlay click
        _overlay.addEventListener('click', function(e) {
            if (e.target === _overlay) close();
        });

        document.addEventListener('keydown', onDocumentKeyDown);
        
        // Wire up events
        wireEvents(settings);
        switchTab('design');
        
        // Highlight header & footer on canvas
        _highlightRegions(true);

        refreshNarrowSettingsUI();
        syncNarrowRangeLabels();
        refreshLegalSettingsUI();
        requestAnimationFrame(focusPrimaryField);
    }
    
    function buildModalHTML(settings) {
        const site = settings.site || {};
        const theme = settings.theme || {};
        const system = settings.system || {};
        const legal = getLegalState(settings);
        
        const headerImage = site.headerImage || '';
        const hideHeaderTitle = site.hideHeaderTitle ? 'checked' : '';
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
        const imageMotionPercent = clampNumber(theme.narrowBackgroundMotionPercent, 0, 100, getLegacyMotionPercent(theme.narrowBackgroundImageMotion));
        const gradientAngle = clampNumber(theme.narrowGradientAngle, 0, 360, 180);
        const overlayOpacity = narrowOverlay.opacity;
        const overlayBlurStrength = narrowOverlay.blurStrength;
        const legalEnabled = legal.enabled ? 'checked' : '';
        
        return `
        <div class="v2-modal" role="dialog" aria-modal="true" aria-labelledby="v2SettingsDialogTitle" tabindex="-1" style="max-width: 640px;">
            <div class="v2-modal-header">
                <h2 id="v2SettingsDialogTitle">⚙️ Einstellungen</h2>
                <button type="button" class="v2-modal-close" data-v2-settings-action="close">×</button>
            </div>
            <div class="v2-modal-body">
                <div class="v2-settings-tabs" role="tablist" aria-label="Einstellungsbereiche">
                    <button type="button" class="v2-settings-tab active" data-v2-settings-tab="design" data-v2-settings-action="switchTab">Design</button>
                    <button type="button" class="v2-settings-tab" data-v2-settings-tab="system" data-v2-settings-action="switchTab">System</button>
                </div>

                <div class="v2-settings-panel active" data-v2-settings-panel="design">
                <!-- Seite -->
                <fieldset class="v2-modal-fieldset">
                    <legend>Seite</legend>
                    
                    <div class="v2-field">
                        <label class="v2-label">Seitentitel</label>
                        <input type="text" class="v2-input" id="v2SetTitle" 
                               value="${escAttr(site.title || '')}" 
                               placeholder="Wird im Editor und standardmäßig im Header verwendet">
                        <small class="v2-hint">Leer lassen für nur Header-Bild. Der Titel bleibt die Basis für Browser-Tab und Editor.</small>
                    </div>

                    <div class="v2-field">
                        <label class="v2-checkbox-label">
                            <input type="checkbox" id="v2SetHideHeaderTitle" ${hideHeaderTitle}>
                            Titel nicht im Header anzeigen
                        </label>
                        <small class="v2-hint">Nützlich, wenn der Name schon im Headerbild steckt. Der Titel bleibt im Editor und im HTML-Titel erhalten.</small>
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

                <fieldset class="v2-modal-fieldset">
                    <legend>Rechtliches</legend>

                    <div class="v2-field">
                        <label class="v2-checkbox-label">
                            <input type="checkbox" id="v2SetLegalEnabled" ${legalEnabled}>
                            Impressum und Datenschutz im Footer anzeigen
                        </label>
                        <small class="v2-hint">Beim ersten befüllten Rechtseintrag aktiviert sich dieser Bereich automatisch.</small>
                    </div>

                    <div class="v2-settings-stack">
                        ${buildLegalEntrySection('v2LegalImprint', 'Impressum', legal.imprint, 'Externer Link oder eigener Text im Vollbild-Modal')}
                        ${buildLegalEntrySection('v2LegalPrivacy', 'Datenschutz', legal.privacy, 'Eigener Text nutzt vereinfachtes, serverseitig sanitisiertes HTML')}
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
                                    <label class="v2-label">Bildbewegung: <span id="v2NarrowMotionPercentValue">${imageMotionPercent}</span>%</label>
                                    <input type="range" class="v2-range-input" id="v2SetNarrowMotionPercent"
                                           min="0" max="100" step="1" value="${imageMotionPercent}">
                                    <small class="v2-hint">0% = mit Inhalt gestreckt, 100% = wirkt fixiert im Viewport.</small>
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
                            <button type="button" class="v2-btn v2-btn-secondary v2-btn-small" data-v2-settings-action="inviteAdmin">➕ Einladen</button>
                        </div>
                        <small class="v2-hint">Die eingeladene Person kann sich beim nächsten Login automatisch anmelden</small>
                    </div>
                </fieldset>
                </div>
            </div>
            <div class="v2-modal-footer">
                <button type="button" class="v2-btn v2-btn-secondary" data-v2-settings-action="close">Abbrechen</button>
                <button type="button" class="v2-btn v2-btn-primary" data-v2-settings-action="save">💾 Speichern</button>
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

    function getLegalState(settings) {
        const legal = settings.legal || {};
        return {
            enabled: !!legal.enabled,
            imprint: getLegalEntryState(legal.imprint),
            privacy: getLegalEntryState(legal.privacy)
        };
    }

    function getLegalEntryState(entry) {
        const legalEntry = entry && typeof entry === 'object' ? entry : {};
        return {
            mode: normalizeOption(legalEntry.mode, ['off', 'link', 'text'], 'off'),
            link: typeof legalEntry.link === 'string' ? legalEntry.link : '',
            text: typeof legalEntry.text === 'string' ? legalEntry.text : ''
        };
    }

    function buildLegalEntrySection(baseId, label, entry, hint) {
        const mode = entry.mode;
        const linkStyle = mode === 'link' ? '' : 'display:none';
        const textStyle = mode === 'text' ? '' : 'display:none';

        return `
        <div class="v2-settings-subsection v2-settings-subsection--nested" id="${baseId}Panel">
            <div class="v2-settings-subsection__header">
                <strong>${escHTML(label)}</strong>
                <span>${escHTML(hint)}</span>
            </div>

            <div class="v2-field">
                <label class="v2-label" for="${baseId}Mode">Darstellung</label>
                <select class="v2-input" id="${baseId}Mode">
                    <option value="off" ${mode === 'off' ? 'selected' : ''}>Ausblenden</option>
                    <option value="link" ${mode === 'link' ? 'selected' : ''}>Externer Link</option>
                    <option value="text" ${mode === 'text' ? 'selected' : ''}>Eigener Text im Modal</option>
                </select>
            </div>

            <div class="v2-field" id="${baseId}LinkField" style="${linkStyle}">
                <label class="v2-label" for="${baseId}Link">Link</label>
                <input type="url" class="v2-input" id="${baseId}Link"
                       value="${escAttr(entry.link)}"
                       placeholder="https://example.org/impressum">
                <small class="v2-hint">Ideal für Verweise auf die Hauptdomain oder bestehende Rechtstexte.</small>
            </div>

            <div class="v2-field" id="${baseId}TextField" style="${textStyle}">
                <label class="v2-label" for="${baseId}Text">Eigener Text</label>
                <textarea class="v2-input" id="${baseId}Text" rows="8"
                          placeholder="Im Modal angezeigt...">${escHTML(entry.text)}</textarea>
                <small class="v2-hint">Erlaubte Tags: &lt;p&gt;, &lt;br&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;ul&gt;, &lt;ol&gt;, &lt;li&gt;, &lt;a&gt;.</small>
            </div>
        </div>`;
    }
    
    function wireEvents(settings) {
        _overlay?.addEventListener('click', handleOverlayActionClick);
        _overlay?.querySelectorAll('[data-v2-settings-action="close"]').forEach((button) => {
            button.addEventListener('click', handleCloseActionClick);
        });
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

        ['v2SetNarrowWidth', 'v2SetNarrowGradientAngle', 'v2SetNarrowMotionPercent', 'v2SetNarrowOverlayOpacity', 'v2SetNarrowOverlayBlurStrength'].forEach((id) => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('input', syncNarrowRangeLabels);
            }
        });

        ['v2LegalImprintMode', 'v2LegalPrivacyMode'].forEach((id) => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('change', () => {
                    refreshLegalSettingsUI();
                    autoEnableLegalIfNeeded();
                });
            }
        });

        ['v2LegalImprintLink', 'v2LegalImprintText', 'v2LegalPrivacyLink', 'v2LegalPrivacyText'].forEach((id) => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('input', autoEnableLegalIfNeeded);
                element.addEventListener('change', autoEnableLegalIfNeeded);
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

        function handleOverlayActionClick(event) {
            const actionTarget = event.target.closest('[data-v2-settings-action]');
            if (!actionTarget || actionTarget.disabled) {
                return;
            }

            event.preventDefault();

            switch (actionTarget.dataset.v2SettingsAction) {
                case 'close':
                    close();
                    break;
                case 'save':
                    void save();
                    break;
                case 'switchTab':
                    switchTab(actionTarget.dataset.v2SettingsTab || 'design');
                    break;
                case 'inviteAdmin':
                    void inviteAdmin();
                    break;
                case 'removeAdminEmail':
                    void removeAdminEmail(actionTarget.dataset.email || '');
                    break;
                case 'removeAdminInvite':
                    void removeAdminInvite(actionTarget.dataset.email || '');
                    break;
                default:
                    break;
            }
        }

        function handleCloseActionClick(event) {
            event.preventDefault();
            event.stopPropagation();
            close();
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
        if (_isSaving) {
            return;
        }

        const narrowBackgroundImage = document.getElementById('v2SetBackgroundPath')?.value || null;

        const newSettings = {
            site: {
                title: document.getElementById('v2SetTitle').value.trim(),
                hideHeaderTitle: document.getElementById('v2SetHideHeaderTitle').checked,
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
                narrowBackgroundMotionPercent: parseInt(document.getElementById('v2SetNarrowMotionPercent').value, 10) || 0,
                narrowBackgroundOverlayEnabled: document.getElementById('v2SetNarrowOverlayEnabled').checked,
                narrowBackgroundOverlayColorEnabled: document.getElementById('v2SetNarrowOverlayColorEnabled').checked,
                narrowBackgroundOverlayColor: document.getElementById('v2SetNarrowOverlayColor').value,
                narrowBackgroundOverlayOpacity: parseInt(document.getElementById('v2SetNarrowOverlayOpacity').value, 10) || 0,
                narrowBackgroundOverlayBlurEnabled: document.getElementById('v2SetNarrowOverlayBlurEnabled').checked,
                narrowBackgroundOverlayBlurStrength: parseInt(document.getElementById('v2SetNarrowOverlayBlurStrength').value, 10) || 0,
                narrowContentShadow: document.getElementById('v2SetNarrowShadow').checked
            },
            legal: {
                enabled: document.getElementById('v2SetLegalEnabled').checked,
                displayStyle: 'subtleButtons',
                imprint: {
                    mode: document.getElementById('v2LegalImprintMode').value,
                    link: document.getElementById('v2LegalImprintLink').value.trim(),
                    text: document.getElementById('v2LegalImprintText').value.trim()
                },
                privacy: {
                    mode: document.getElementById('v2LegalPrivacyMode').value,
                    link: document.getElementById('v2LegalPrivacyLink').value.trim(),
                    text: document.getElementById('v2LegalPrivacyText').value.trim()
                }
            },
            system: {
                mailFromAddress: document.getElementById('v2SetMailFromAddress').value.trim()
            }
        };

        setSavingState(true);
        
        try {
            const result = await V2Api.saveSettings(newSettings);
            if (result.success) {
                V2State.setSettings(result.settings || newSettings);
                showToast('Einstellungen gespeichert!', 'success');

                // Full page reload keeps PHP-rendered header/footer/legal markup in sync.
                close({ restoreFocus: false, keepScrollLocked: true });
                startReloadTransition();
                return;
            } else {
                showToast('Speichern fehlgeschlagen: ' + (result.error || ''), 'error');
            }
        } catch(err) {
            console.error('[Settings] save failed:', err);
            showToast('Speichern fehlgeschlagen', 'error');
        } finally {
            if (_overlay) {
                setSavingState(false);
            } else {
                _isSaving = false;
            }
        }
    }
    
    function close(options = {}) {
        const { restoreFocus = true, keepScrollLocked = false } = options;

        _highlightRegions(false);
        document.removeEventListener('keydown', onDocumentKeyDown);

        if (_overlay) {
            _overlay.remove();
            _overlay = null;
        }

        _isSaving = false;

        if (!keepScrollLocked) {
            unlockPageScroll();
        }

        const focusTarget = restoreFocus ? _restoreFocusEl : null;
        _restoreFocusEl = null;

        if (focusTarget && focusTarget.isConnected) {
            requestAnimationFrame(() => {
                focusTarget.focus();
            });
        }
    }

    function onDocumentKeyDown(event) {
        if (!_overlay || _isSaving) {
            return;
        }

        if (event.key !== 'Escape') {
            return;
        }

        if (document.querySelector('.v2-media-picker-overlay')) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        close();
    }

    function focusPrimaryField() {
        const modal = _overlay?.querySelector('.v2-modal');
        if (!modal) {
            return;
        }

        const firstField = modal.querySelector('input:not([type="hidden"]):not([type="checkbox"]):not([disabled]), textarea:not([disabled]), select:not([disabled]), button:not([disabled])');
        if (firstField instanceof HTMLElement) {
            firstField.focus();
            return;
        }

        modal.focus();
    }

    function lockPageScroll() {
        _scrollLockY = window.scrollY || window.pageYOffset || 0;
        _scrollLockPaddingRight = document.body.style.paddingRight || '';

        const scrollbarWidth = Math.max(0, window.innerWidth - document.documentElement.clientWidth);
        if (scrollbarWidth > 0) {
            document.body.style.paddingRight = `${scrollbarWidth}px`;
        }

        document.documentElement.classList.add('v2-modal-open');
        document.body.classList.add('v2-modal-open');
        document.body.style.top = `-${_scrollLockY}px`;
    }

    function unlockPageScroll() {
        document.documentElement.classList.remove('v2-modal-open');
        document.body.classList.remove('v2-modal-open');
        document.body.style.top = '';
        document.body.style.paddingRight = _scrollLockPaddingRight;
        window.scrollTo(0, _scrollLockY);
    }

    function setSavingState(isSaving) {
        _isSaving = !!isSaving;

        if (!_overlay) {
            return;
        }

        const saveButton = _overlay.querySelector('[data-v2-settings-action="save"]');
        if (saveButton instanceof HTMLButtonElement) {
            saveButton.disabled = _isSaving;
            saveButton.textContent = _isSaving ? 'Speichert...' : '💾 Speichern';
        }

        _overlay.querySelectorAll('[data-v2-settings-action="close"]').forEach((button) => {
            if (button instanceof HTMLButtonElement) {
                button.disabled = _isSaving;
            }
        });
    }

    function startReloadTransition() {
        document.documentElement.classList.add('v2-page-reloading');
        document.body.classList.add('v2-page-reloading');

        requestAnimationFrame(() => {
            window.setTimeout(() => {
                window.location.reload();
            }, 180);
        });
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

    function refreshLegalSettingsUI() {
        ['v2LegalImprint', 'v2LegalPrivacy'].forEach((baseId) => {
            const mode = document.getElementById(`${baseId}Mode`)?.value || 'off';
            toggleDisplay(`${baseId}LinkField`, mode === 'link');
            toggleDisplay(`${baseId}TextField`, mode === 'text');
        });
    }

    function autoEnableLegalIfNeeded() {
        const enabledToggle = document.getElementById('v2SetLegalEnabled');
        if (!enabledToggle || enabledToggle.checked) {
            return;
        }

        if (hasLegalEntryContent('v2LegalImprint') || hasLegalEntryContent('v2LegalPrivacy')) {
            enabledToggle.checked = true;
        }
    }

    function hasLegalEntryContent(baseId) {
        const mode = document.getElementById(`${baseId}Mode`)?.value || 'off';
        if (mode === 'link') {
            return !!document.getElementById(`${baseId}Link`)?.value.trim();
        }

        if (mode === 'text') {
            return !!document.getElementById(`${baseId}Text`)?.value.trim();
        }

        return false;
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

        const motionValue = document.getElementById('v2NarrowMotionPercentValue');
        const motionRange = document.getElementById('v2SetNarrowMotionPercent');
        if (motionValue && motionRange) {
            motionValue.textContent = motionRange.value;
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
        const motionPercent = clampNumber(theme.narrowBackgroundMotionPercent, 0, 100, getLegacyMotionPercent(theme.narrowBackgroundImageMotion));
        const motionFactor = motionPercent / 100;
        const overlay = getNarrowOverlayState(theme);
        const width = clampNumber(theme.narrowWidth, 600, 1400, 960);
        const hasImage = mode === 'image' && imagePath;
        const contentShadow = theme.narrowContentShadow === false ? 'none' : '0 0 60px rgba(0,0,0,0.4)';
        const blurPx = overlay.blurEnabled ? ((overlay.blurStrength / 100) * 24) : 0;
        const baseScale = overlay.blurEnabled ? (1 + (overlay.blurStrength / 1000)) : 1;
        const hasMotion = narrowLayout && hasImage;
        const effectiveSize = imageDisplay === 'tile'
            ? 'auto'
            : 'cover';

        wrapper.classList.toggle('v2-canvas-wrapper--narrow', narrowLayout);
        wrapper.classList.toggle('v2-canvas-wrapper--motion', hasMotion);
        wrapper.style.setProperty('--v2-narrow-width', `${width}px`);
        wrapper.style.setProperty('--v2-narrow-backdrop', buildNarrowBackdrop(theme));
        wrapper.style.setProperty('--v2-narrow-backdrop-fallback', sanitizeHexColor(theme.narrowBackgroundColor, '#1a1a2e'));
        wrapper.style.setProperty('--v2-narrow-background-repeat', imageDisplay === 'tile' ? 'repeat' : 'no-repeat');
        wrapper.style.setProperty('--v2-narrow-background-size', effectiveSize);
        wrapper.style.setProperty('--v2-narrow-background-attachment', 'scroll');
        wrapper.style.setProperty('--v2-narrow-background-blur', `${narrowLayout && hasImage ? blurPx : 0}px`);
        wrapper.style.setProperty('--v2-narrow-background-scale', String(narrowLayout && hasImage ? baseScale : 1));
        wrapper.style.setProperty('--v2-narrow-motion-factor', String(narrowLayout && hasImage ? motionFactor : 0));
        wrapper.style.setProperty('--v2-narrow-motion-height', '112vh');
        wrapper.style.setProperty('--v2-narrow-motion-top', '-6vh');
        wrapper.style.setProperty('--v2-narrow-overlay-display', narrowLayout && hasImage && overlay.colorEnabled ? 'block' : 'none');
        wrapper.style.setProperty('--v2-narrow-overlay-color', overlay.color);
        wrapper.style.setProperty('--v2-narrow-overlay-opacity', String(narrowLayout && hasImage && overlay.colorEnabled ? (overlay.opacity / 100) : 0));
        canvas.style.boxShadow = narrowLayout ? contentShadow : '';

        applyNarrowBackdropFallbackColor(wrapper, mode, imagePath);

        syncParallaxOffset();
    }

    function syncParallaxOffset() {
        if (_parallaxQueued) return;

        _parallaxQueued = true;
        requestAnimationFrame(() => {
            _parallaxQueued = false;
            const wrapper = document.querySelector('.v2-canvas-wrapper');
            if (!wrapper) return;

            if (!wrapper.classList.contains('v2-canvas-wrapper--motion')) {
                _parallaxOffsetCurrent = 0;
                wrapper.style.setProperty('--v2-parallax-offset', '0px');
                return;
            }

            const rect = wrapper.getBoundingClientRect();
            const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
            const motionFactor = Number.parseFloat(getComputedStyle(wrapper).getPropertyValue('--v2-narrow-motion-factor')) || 0;
            const minHeight = viewportHeight * 1.12;
            const maxHeight = rect.height + (viewportHeight * 0.12);
            const height = minHeight + ((1 - motionFactor) * Math.max(0, maxHeight - minHeight));
            const top = -(height * 0.06);
            const targetOffset = (-rect.top) * motionFactor;
            const followAlpha = motionFactor >= 0.98 ? 1 : (0.18 + (motionFactor * 0.5));
            _parallaxOffsetCurrent += (targetOffset - _parallaxOffsetCurrent) * followAlpha;
            if (motionFactor >= 0.98 || Math.abs(targetOffset - _parallaxOffsetCurrent) < 0.2) {
                _parallaxOffsetCurrent = targetOffset;
            }
            const offset = Math.round(_parallaxOffsetCurrent);
            wrapper.style.setProperty('--v2-narrow-motion-height', `${height}px`);
            wrapper.style.setProperty('--v2-narrow-motion-top', `${top}px`);
            wrapper.style.setProperty('--v2-parallax-offset', `${offset}px`);

            if (Math.abs(targetOffset - _parallaxOffsetCurrent) >= 0.2) {
                syncParallaxOffset();
            }
        });
    }

    function getLegacyMotionPercent(value) {
        if (value === 'fixed') return 100;
        if (value === 'parallax') return 60;
        return 0;
    }

    async function applyNarrowBackdropFallbackColor(wrapper, mode, imagePath) {
        if (!wrapper) return;

        const requestToken = ++_narrowColorRequestToken;
        if (mode !== 'image' || !imagePath) {
            return;
        }

        const hex = await getImageAverageHex(imagePath);
        if (requestToken !== _narrowColorRequestToken || !hex) {
            return;
        }

        wrapper.style.setProperty('--v2-narrow-backdrop-fallback', hex);
    }

    function getImageAverageHex(src) {
        if (!src) {
            return Promise.resolve(null);
        }

        if (_imageColorCache.has(src)) {
            return Promise.resolve(_imageColorCache.get(src));
        }

        return new Promise((resolve) => {
            const img = new Image();
            img.decoding = 'async';
            img.crossOrigin = 'anonymous';

            img.onload = () => {
                try {
                    const canvas = document.createElement('canvas');
                    canvas.width = 24;
                    canvas.height = 24;
                    const ctx = canvas.getContext('2d', { willReadFrequently: true });
                    if (!ctx) {
                        _imageColorCache.set(src, null);
                        resolve(null);
                        return;
                    }

                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                    const { data } = ctx.getImageData(0, 0, canvas.width, canvas.height);

                    let r = 0;
                    let g = 0;
                    let b = 0;
                    let w = 0;

                    for (let i = 0; i < data.length; i += 4) {
                        const alpha = data[i + 3] / 255;
                        if (alpha <= 0) continue;
                        r += data[i] * alpha;
                        g += data[i + 1] * alpha;
                        b += data[i + 2] * alpha;
                        w += alpha;
                    }

                    const hex = w > 0
                        ? '#' + [r / w, g / w, b / w]
                            .map((v) => Math.max(0, Math.min(255, Math.round(v))).toString(16).padStart(2, '0'))
                            .join('')
                        : null;

                    _imageColorCache.set(src, hex);
                    resolve(hex);
                } catch (err) {
                    _imageColorCache.set(src, null);
                    resolve(null);
                }
            };

            img.onerror = () => {
                _imageColorCache.set(src, null);
                resolve(null);
            };

            img.src = src;
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
                <button type="button" class="v2-admin-remove" ${disabledAttr} title="${title}" data-v2-settings-action="removeAdminEmail" data-email="${escAttr(email)}">✕</button>
            </div>`;
        }).join('');
        
        const inviteItems = _adminInvites.map(invite => {
            const email = invite.email || '';
            return `<div class="v2-admin-item v2-admin-pending" title="Ausstehend – Einladung wartet auf Login">
                <span>${escHTML(email)} (ausstehend)</span>
                <button type="button" class="v2-admin-remove" title="Einladung löschen" data-v2-settings-action="removeAdminInvite" data-email="${escAttr(email)}">✕</button>
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
                showToast(result.message || 'Einladung gesendet', 'success');
            } else {
                showToast(result.message || 'Einladung fehlgeschlagen', 'error');
            }
        } catch(err) {
            console.error('[Settings] Invite failed:', err);
            showToast('Netzwerkfehler beim Einladen', 'error');
        }
    }
    
    async function removeAdminEmail(email) {
        if (!email) return;
        if (!confirm('Admin "' + email + '" wirklich entfernen?')) return;
        
        try {
            const result = await V2Api.post('remove_admin_email', { email });
            if (result.success) {
                _adminEmails = result.emails || _adminEmails;
                _adminInvites = result.invites || _adminInvites;
                renderAdminList();
                showToast(result.message || 'Admin entfernt', 'success');
            } else {
                showToast(result.message || 'Entfernen fehlgeschlagen', 'error');
            }
        } catch(err) {
            console.error('[Settings] Remove admin failed:', err);
            showToast('Netzwerkfehler beim Entfernen', 'error');
        }
    }
    
    async function removeAdminInvite(email) {
        if (!email) return;
        try {
            const result = await V2Api.post('remove_admin_invite', { email });
            if (result.success) {
                _adminEmails = result.emails || _adminEmails;
                _adminInvites = result.invites || _adminInvites;
                renderAdminList();
                showToast(result.message || 'Einladung entfernt', 'success');
            } else {
                showToast(result.message || 'Entfernen fehlgeschlagen', 'error');
            }
        } catch(err) {
            console.error('[Settings] Remove invite failed:', err);
            showToast('Netzwerkfehler beim Entfernen', 'error');
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

export { V2Settings };
export default V2Settings;
