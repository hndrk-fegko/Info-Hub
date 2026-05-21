/* IframeTile JavaScript - Modal Funktionalitaet */

function applyIframeModalConfig(trigger) {
    const modal = document.getElementById('iframe-modal');
    const content = document.querySelector('.iframe-modal-content');
    const header = document.querySelector('.iframe-modal-header');
    if (!modal || !content || !header || !trigger) {
        return;
    }

    const presentation = trigger.dataset.iframePresentation || 'dialog';
    const headerScheme = trigger.dataset.iframeHeaderScheme || 'default';
    const backgroundMode = trigger.dataset.iframeBgMode || 'default';
    const backgroundColorOverride = trigger.dataset.iframeBgColorOverride || '';
    const backgroundImage = trigger.dataset.iframeBgImage || '';
    const backgroundDisplay = trigger.dataset.iframeBgDisplay || 'cover';
    const legacyMotion = trigger.dataset.iframeBgMotion || 'fixed';
    const legacyPercent = legacyMotion === 'fixed' ? 100 : (legacyMotion === 'parallax' ? 60 : 0);
    const backgroundMotionPercent = Number.parseInt(trigger.dataset.iframeBgMotionPercent || String(legacyPercent), 10);
    const motionPercent = Number.isFinite(backgroundMotionPercent)
        ? Math.max(0, Math.min(100, backgroundMotionPercent))
        : legacyPercent;
    const overlayEnabled = trigger.dataset.iframeOverlayEnabled === '1';
    const overlayColorEnabled = trigger.dataset.iframeOverlayColorEnabled === '1';
    const overlayColor = trigger.dataset.iframeOverlayColor || '#000000';
    const overlayOpacity = Number.parseInt(trigger.dataset.iframeOverlayOpacity || '35', 10);
    const overlayBlurEnabled = trigger.dataset.iframeOverlayBlurEnabled === '1';
    const overlayBlurStrength = Number.parseInt(trigger.dataset.iframeOverlayBlurStrength || '24', 10);

    modal.classList.toggle('iframe-modal--fullscreen-mobile', presentation === 'fullscreen-mobile');

    header.classList.remove(
        'iframe-modal-header--default',
        'iframe-modal-header--accent1',
        'iframe-modal-header--accent2',
        'iframe-modal-header--accent3'
    );
    header.classList.add(`iframe-modal-header--${headerScheme}`);

    const hasImageBackground = backgroundMode === 'image' && backgroundImage !== '';
    const effectiveBackgroundMode = hasImageBackground
        ? 'image'
        : (backgroundColorOverride !== ''
            ? 'custom'
            : (backgroundMode === 'image' ? 'default' : backgroundMode));

    content.classList.remove(
        'iframe-modal-content--bg-default',
        'iframe-modal-content--bg-custom',
        'iframe-modal-content--bg-accent1',
        'iframe-modal-content--bg-accent2',
        'iframe-modal-content--bg-accent3',
        'iframe-modal-content--bg-image'
    );
    content.classList.add(`iframe-modal-content--bg-${effectiveBackgroundMode}`);

    content.classList.remove(
        'iframe-modal-content--motion-fixed',
        'iframe-modal-content--motion-parallax',
        'iframe-modal-content--motion-stretch'
    );
    const motionClass = motionPercent >= 100
        ? 'iframe-modal-content--motion-fixed'
        : (motionPercent > 0 ? 'iframe-modal-content--motion-parallax' : 'iframe-modal-content--motion-stretch');
    content.classList.add(motionClass);

    if (backgroundColorOverride) {
        content.style.setProperty('--iframe-modal-bg-color-override', backgroundColorOverride);
    } else {
        content.style.removeProperty('--iframe-modal-bg-color-override');
    }

    if (hasImageBackground) {
        const escapedPath = backgroundImage.replace(/"/g, '%22');
        content.style.setProperty('--iframe-modal-bg-image', `url("${escapedPath}")`);
        const size = backgroundDisplay === 'tile' ? 'auto' : (motionPercent === 0 ? '100% 100%' : 'cover');
        content.style.setProperty('--iframe-modal-bg-size', size);
        content.style.setProperty('--iframe-modal-bg-repeat', backgroundDisplay === 'tile' ? 'repeat' : 'no-repeat');
        const motionScale = 1 + ((motionPercent / 100) * 0.2);
        content.style.setProperty('--iframe-modal-bg-parallax-scale', String(motionScale));
        content.style.setProperty('--iframe-modal-bg-fixed-scale', String(1 + ((motionPercent / 100) * 0.08)));
    } else {
        content.style.removeProperty('--iframe-modal-bg-image');
        content.style.removeProperty('--iframe-modal-bg-size');
        content.style.removeProperty('--iframe-modal-bg-repeat');
        content.style.removeProperty('--iframe-modal-bg-parallax-scale');
        content.style.removeProperty('--iframe-modal-bg-fixed-scale');
    }

    content.style.setProperty('--iframe-modal-overlay-color', overlayColor);
    content.style.setProperty(
        '--iframe-modal-overlay-opacity',
        overlayEnabled && overlayColorEnabled
            ? String(Math.max(0, Math.min(100, overlayOpacity)) / 100)
            : '0'
    );
    content.style.setProperty(
        '--iframe-modal-bg-blur',
        overlayEnabled && overlayBlurEnabled
            ? `${(Math.max(0, Math.min(100, overlayBlurStrength)) / 100) * 24}px`
            : '0px'
    );
}

function resetIframeModalConfig() {
    const modal = document.getElementById('iframe-modal');
    const content = document.querySelector('.iframe-modal-content');
    const header = document.querySelector('.iframe-modal-header');
    if (!modal || !content || !header) {
        return;
    }

    modal.classList.remove('iframe-modal--fullscreen-mobile');

    header.classList.remove(
        'iframe-modal-header--accent1',
        'iframe-modal-header--accent2',
        'iframe-modal-header--accent3'
    );
    header.classList.add('iframe-modal-header--default');

    content.classList.remove(
        'iframe-modal-content--bg-custom',
        'iframe-modal-content--bg-accent1',
        'iframe-modal-content--bg-accent2',
        'iframe-modal-content--bg-accent3',
        'iframe-modal-content--bg-image'
    );
    content.classList.add('iframe-modal-content--bg-default');
    content.classList.remove(
        'iframe-modal-content--motion-parallax',
        'iframe-modal-content--motion-stretch'
    );
    content.classList.add('iframe-modal-content--motion-fixed');

    content.style.removeProperty('--iframe-modal-bg-color-override');
    content.style.removeProperty('--iframe-modal-bg-image');
    content.style.removeProperty('--iframe-modal-bg-size');
    content.style.removeProperty('--iframe-modal-bg-repeat');
    content.style.removeProperty('--iframe-modal-bg-parallax-scale');
    content.style.removeProperty('--iframe-modal-bg-fixed-scale');
    content.style.removeProperty('--iframe-modal-overlay-color');
    content.style.removeProperty('--iframe-modal-overlay-opacity');
    content.style.removeProperty('--iframe-modal-bg-blur');
}

function openIframeModal(url, title, trigger) {
    const titleEl = document.getElementById('iframe-modal-title');
    if (title && title.trim() !== '') {
        titleEl.textContent = title;
        titleEl.style.display = '';
    } else {
        titleEl.textContent = '';
        titleEl.style.display = 'none';
    }

    applyIframeModalConfig(trigger);

    document.getElementById('iframe-modal-frame').src = url;
    document.getElementById('iframe-modal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeIframeModal() {
    document.getElementById('iframe-modal').classList.remove('active');
    document.getElementById('iframe-modal-frame').src = ''; // Stoppt Laden
    resetIframeModalConfig();
    document.body.style.overflow = '';
}

// Event-Delegation für data-iframe-url Elemente (statt inline onclick)
document.addEventListener('click', (e) => {
    const closeTrigger = e.target.closest('[data-iframe-close]');
    if (closeTrigger) {
        e.preventDefault();
        closeIframeModal();
        return;
    }

    const trigger = e.target.closest('[data-iframe-url]');
    if (trigger) {
        openIframeModal(trigger.dataset.iframeUrl, trigger.dataset.iframeTitle || '', trigger);
    }
});

// Click outside to close
document.getElementById('iframe-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'iframe-modal') {
        closeIframeModal();
    }
});

// Escape-Taste schließt Modal
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeIframeModal();
    }
});
