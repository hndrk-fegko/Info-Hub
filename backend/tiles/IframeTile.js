/* IframeTile JavaScript - Modal Funktionalität */

function openIframeModal(url, title) {
    const titleEl = document.getElementById('iframe-modal-title');
    if (title && title.trim() !== '') {
        titleEl.textContent = title;
        titleEl.style.display = '';
    } else {
        titleEl.textContent = '';
        titleEl.style.display = 'none';
    }
    document.getElementById('iframe-modal-frame').src = url;
    document.getElementById('iframe-modal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeIframeModal() {
    document.getElementById('iframe-modal').classList.remove('active');
    document.getElementById('iframe-modal-frame').src = ''; // Stoppt Laden
    document.body.style.overflow = '';
}

// Event-Delegation für data-iframe-url Elemente (statt inline onclick)
document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-iframe-url]');
    if (trigger) {
        openIframeModal(trigger.dataset.iframeUrl, trigger.dataset.iframeTitle || '');
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
