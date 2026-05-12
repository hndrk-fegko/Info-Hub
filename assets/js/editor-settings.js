/**
 * Editor Settings - Einstellungen, Admin-Verwaltung, Header-Upload, Publish/Preview
 * 
 * Abhängigkeiten: editor-core.js (apiPost, apiPostFormData, showToast, escapeHtml, settings, previewWindow)
 *                 editor-session.js (hideDiagnostics, showUploadDiagnostics)
 */

// ===== Settings Modal =====
function openSettingsModal() {
    document.getElementById('settingsModal').classList.add('active');
    switchSettingsTab('design');
    loadAdminEmails();
}

function closeSettingsModal() {
    document.getElementById('settingsModal').classList.remove('active');
}

function switchSettingsTab(tabName) {
    document.querySelectorAll('[data-settings-tab]').forEach((tab) => {
        tab.classList.toggle('active', tab.dataset.settingsTab === tabName);
    });

    document.querySelectorAll('[data-settings-panel]').forEach((panel) => {
        panel.classList.toggle('active', panel.dataset.settingsPanel === tabName);
    });
}

// ===== Narrow Layout Toggle =====
document.addEventListener('DOMContentLoaded', function() {
    const narrowCheck = document.getElementById('narrowLayout');
    const narrowGroup = document.getElementById('narrowWidthGroup');
    const narrowRange = document.getElementById('narrowWidth');
    const narrowValue = document.getElementById('narrowWidthValue');
    
    if (narrowCheck && narrowGroup) {
        narrowCheck.addEventListener('change', () => {
            narrowGroup.style.display = narrowCheck.checked ? '' : 'none';
        });
    }
    if (narrowRange && narrowValue) {
        narrowRange.addEventListener('input', () => {
            narrowValue.textContent = narrowRange.value;
        });
    }
});

async function saveSettings(event) {
    event.preventDefault();
    
    const form = document.getElementById('settingsForm');
    const formData = new FormData(form);
    
    // headerImage aus dem Hidden-Field lesen (nicht aus dem File-Input!)
    const headerImagePath = document.getElementById('headerImagePath')?.value || null;
    
    const newSettings = {
        site: {
            title: formData.get('title') || '',
            pageTitle: formData.get('pageTitle') || '',
            headerImage: headerImagePath || null,
            headerFocusPoint: formData.get('headerFocusPoint') || 'center center',
            footerText: formData.get('footerText') || ''
        },
        theme: {
            backgroundColor: formData.get('backgroundColor'),
            accentColor: formData.get('accentColor'),
            accentColor2: formData.get('accentColor2'),
            accentColor3: formData.get('accentColor3'),
            narrowLayout: document.getElementById('narrowLayout')?.checked || false,
            narrowWidth: parseInt(document.getElementById('narrowWidth')?.value, 10) || 960
        },
        system: {
            mailFromAddress: (formData.get('mailFromAddress') || '').trim()
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
            
            // Publish-Button-Farbe aktualisieren
            applyPublishButtonStyle();
            
            refreshPreview();
        } else {
            showToast('error', result.error || 'Speichern fehlgeschlagen');
        }
    } catch (error) {
        console.error('Settings save error:', error);
        showToast('error', 'Netzwerkfehler beim Speichern');
    }
}

// ===== Admin Email Management =====
let adminEmailsCache = [];
let adminInvitesCache = [];

window.switchSettingsTab = switchSettingsTab;

async function loadAdminEmails() {
    const listEl = document.getElementById('adminEmailList');
    if (!listEl) return;

    listEl.innerHTML = '<span class="hint">Lade...</span>';

    try {
        const result = await apiPost('get_admins');
        if (result.success) {
            syncAdminData(result);
        } else {
            listEl.innerHTML = '<span class="hint">Keine Daten verfügbar</span>';
        }
    } catch (error) {
        console.error('Admin load error:', error);
        listEl.innerHTML = '<span class="hint">Fehler beim Laden</span>';
    }
}

function syncAdminData(result) {
    adminEmailsCache = result.emails || [];
    adminInvitesCache = result.invites || [];

    settings.auth = settings.auth || {};
    settings.auth.emails = adminEmailsCache;
    settings.auth.invites = adminInvitesCache;

    renderAdminEmailList();
}

function renderAdminEmailList() {
    const listEl = document.getElementById('adminEmailList');
    if (!listEl) return;

    if (adminEmailsCache.length === 0 && adminInvitesCache.length === 0) {
        listEl.innerHTML = '<span class="hint">Keine Admins gefunden</span>';
        return;
    }

    const canRemoveAdmin = adminEmailsCache.length > 1;

    const adminItems = adminEmailsCache.map(email => {
        const disabled = canRemoveAdmin ? '' : 'disabled';
        const isSelf = CONFIG?.currentEmail && email.toLowerCase() === CONFIG.currentEmail.toLowerCase();
        const title = !canRemoveAdmin
            ? 'Letzte Admin-Adresse kann nicht gelöscht werden'
            : isSelf ? 'Eigenen Zugang entfernen (Abmeldung!)' : 'Admin entfernen';
        const label = isSelf ? `${escapeHtml(email)} (du)` : escapeHtml(email);
        return `
            <div class="admin-email-item${isSelf ? ' is-self' : ''}">
                <span>${label}</span>
                <button class="admin-remove" ${disabled} title="${title}" onclick="removeAdminEmail('${escapeHtml(email)}')">✕</button>
            </div>
        `;
    }).join('');

    const inviteItems = adminInvitesCache.map(invite => {
        const email = invite.email || '';
        return `
            <div class="admin-email-item pending" title="Ausstehend – Einladung wartet auf Login">
                <span>${escapeHtml(email)} (ausstehend)</span>
                <button class="admin-remove" title="Einladung löschen" onclick="removeAdminInvite('${escapeHtml(email)}')">✕</button>
            </div>
        `;
    }).join('');

    listEl.innerHTML = adminItems + inviteItems;
}

function openInviteAdminModal() {
    document.getElementById('inviteAdminModal')?.classList.add('active');
    document.getElementById('inviteEmail')?.focus();
}

function closeInviteAdminModal() {
    document.getElementById('inviteAdminModal')?.classList.remove('active');
    const input = document.getElementById('inviteEmail');
    if (input) input.value = '';
}

async function submitInviteAdmin(event) {
    event.preventDefault();
    const email = document.getElementById('inviteEmail')?.value?.trim();
    if (!email) return;

    try {
        const result = await apiPost('invite_admin', { email });
        if (result.success) {
            syncAdminData(result);
            closeInviteAdminModal();
            showToast('success', result.message || 'Einladung gesendet');
        } else {
            showToast('error', result.message || 'Einladung fehlgeschlagen');
        }
    } catch (error) {
        console.error('Invite error:', error);
        showToast('error', 'Netzwerkfehler beim Einladen');
    }
}

async function removeAdminEmail(email) {
    const isSelf = CONFIG?.currentEmail && email.toLowerCase() === CONFIG.currentEmail.toLowerCase();
    
    const confirmMsg = isSelf
        ? 'Du entfernst dich selbst als Admin!\n\nDu wirst sofort abgemeldet und kannst dich nicht mehr einloggen, bis ein anderer Admin dich erneut einlädt.\n\nWirklich fortfahren?'
        : 'Admin wirklich entfernen?';
    
    if (!confirm(confirmMsg)) return;

    try {
        const result = await apiPost('remove_admin_email', { email });
        if (result.success) {
            if (isSelf) {
                // Self-delete: sofort abmelden
                window.location.href = 'login.php?logout=1';
                return;
            }
            syncAdminData(result);
            showToast('success', result.message || 'Admin entfernt');
        } else {
            showToast('error', result.message || 'Entfernen fehlgeschlagen');
        }
    } catch (error) {
        console.error('Remove admin error:', error);
        showToast('error', 'Netzwerkfehler beim Entfernen');
    }
}

async function removeAdminInvite(email) {
    try {
        const result = await apiPost('remove_admin_invite', { email });
        if (result.success) {
            syncAdminData(result);
            showToast('success', result.message || 'Einladung entfernt');
        } else {
            showToast('error', result.message || 'Entfernen fehlgeschlagen');
        }
    } catch (error) {
        console.error('Remove invite error:', error);
        showToast('error', 'Netzwerkfehler beim Entfernen');
    }
}

// Admin-Funktionen global verfügbar machen
window.openInviteAdminModal = openInviteAdminModal;
window.closeInviteAdminModal = closeInviteAdminModal;
window.submitInviteAdmin = submitInviteAdmin;
window.removeAdminEmail = removeAdminEmail;
window.removeAdminInvite = removeAdminInvite;

// ===== Header Image Upload =====
async function generateHeaderPlaceholder(file) {
    if (!file || !file.type.startsWith('image/')) {
        return null;
    }

    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onerror = () => resolve(null);
        reader.onload = () => {
            const image = new Image();
            image.onerror = () => resolve(null);
            image.onload = () => {
                const targetWidth = Math.min(32, image.naturalWidth || 32);
                const targetHeight = Math.max(1, Math.round((image.naturalHeight || 1) * (targetWidth / Math.max(1, image.naturalWidth || 1))));

                const canvas = document.createElement('canvas');
                canvas.width = targetWidth;
                canvas.height = targetHeight;

                const context = canvas.getContext('2d', { alpha: false });
                if (!context) {
                    resolve(null);
                    return;
                }

                context.drawImage(image, 0, 0, targetWidth, targetHeight);

                let placeholder = null;
                try {
                    placeholder = canvas.toDataURL('image/webp', 0.35);
                    if (!placeholder.startsWith('data:image/webp')) {
                        placeholder = canvas.toDataURL('image/jpeg', 0.35);
                    }
                } catch (error) {
                    placeholder = canvas.toDataURL('image/jpeg', 0.35);
                }

                resolve({
                    placeholder,
                    width: image.naturalWidth || null,
                    height: image.naturalHeight || null
                });
            };
            image.src = reader.result;
        };
        reader.readAsDataURL(file);
    });
}

async function uploadHeaderImage(input) {
    if (!input.files[0]) return;

    const file = input.files[0];
    const clientPlaceholder = await generateHeaderPlaceholder(file);
    
    const formData = new FormData();
    formData.append('action', 'upload_header');
    formData.append('file', file);
    if (clientPlaceholder?.placeholder) {
        formData.append('headerPlaceholder', clientPlaceholder.placeholder);
    }
    if (clientPlaceholder?.width) {
        formData.append('headerImageWidth', String(clientPlaceholder.width));
    }
    if (clientPlaceholder?.height) {
        formData.append('headerImageHeight', String(clientPlaceholder.height));
    }
    
    showToast('info', 'Header-Bild wird hochgeladen...');
    
    try {
        const result = await apiPostFormData(formData);
        
        if (result.success) {
            // Pfad im Hidden-Field speichern
            document.getElementById('headerImagePath').value = result.path;

            settings.site = settings.site || {};
            settings.site.headerImage = result.path;
            settings.site.headerImagePlaceholder = result.placeholder || null;
            settings.site.headerImageWidth = result.width || null;
            settings.site.headerImageHeight = result.height || null;
            
            // Preview aktualisieren
            document.getElementById('headerPreview').innerHTML = 
                `<img src="${result.path}" alt="Header Preview">
                 <button type="button" class="btn btn-small" onclick="removeHeaderImage()">Entfernen</button>`;
            
            showToast('success', 'Header-Bild hochgeladen');
            hideDiagnostics();
        } else {
            // Fehlerbehandlung mit Diagnostics
            console.error('Upload failed:', result);
            showToast('error', result.error || 'Upload fehlgeschlagen');
            
            // Wenn Details vorhanden sind (Permission-Probleme), zeige Diagnose
            if (result.details || (result.error && (
                result.error.includes('Schreibrechte') || 
                result.error.includes('Berechtigungen') ||
                result.error.includes('konnte nicht')
            ))) {
                showUploadDiagnostics(result);
            }
        }
    } catch (error) {
        console.error('Upload error:', error);
        showToast('error', 'Netzwerkfehler beim Upload');
    }
}

function removeHeaderImage() {
    document.getElementById('headerImagePath').value = '';
    document.getElementById('headerImageFile').value = '';
    document.getElementById('headerPreview').innerHTML = '<span class="no-image">Kein Header-Bild</span>';

    settings.site = settings.site || {};
    settings.site.headerImage = null;
    settings.site.headerImagePlaceholder = null;
    settings.site.headerImageWidth = null;
    settings.site.headerImageHeight = null;
}

// ===== Publish & Preview =====

// Publish-Button mit Akzentfarbe und Kontrast-Text
function applyPublishButtonStyle() {
    const btn = document.getElementById('publishBtn');
    if (!btn) return;
    
    // Akzentfarbe aus Settings holen (accentColor = Seitentitel-Farbe)
    const accentColor = settings?.theme?.accentColor || '#667eea';
    btn.style.background = accentColor;
    btn.style.borderColor = accentColor;
    
    // Kontrast berechnen (YIQ-Formel)
    const hex = accentColor.replace('#', '');
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);
    const yiq = (r * 299 + g * 587 + b * 114) / 1000;
    
    btn.style.color = yiq >= 128 ? '#000000' : '#ffffff';
}

async function publishSite() {
    if (!confirm('Seite jetzt veröffentlichen? Die aktuelle Version wird überschrieben.')) return;
    
    const btn = document.getElementById('publishBtn');
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

// Logout
function logout() {
    if (confirm('Möchtest du dich abmelden?')) {
        window.location.href = 'login.php?logout=1';
    }
}
