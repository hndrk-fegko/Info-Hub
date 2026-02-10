/* ImageTile JavaScript - Lightbox Funktionalität */

function openLightbox(src) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
    document.body.style.overflow = '';
}

// Event-Delegation für data-lightbox-src Elemente (statt inline onclick)
document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-lightbox-src]');
    if (trigger) {
        openLightbox(trigger.dataset.lightboxSrc);
    }
});

// Keyboard-Support: Enter/Space öffnet Lightbox
document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
        const trigger = e.target.closest('[data-lightbox-src]');
        if (trigger) {
            e.preventDefault();
            openLightbox(trigger.dataset.lightboxSrc);
        }
    }
});

// Escape-Taste schließt Lightbox
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeLightbox();
    }
});
