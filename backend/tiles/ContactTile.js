/* ContactTile JavaScript - Anti-Crawler Reveal */

function decodeContactData(encodedData) {
    // XOR-Key muss mit PHP übereinstimmen
    const XOR_KEY = 'InfoHub2026';
    
    // Base64 dekodieren
    const decoded = atob(encodedData);
    
    // XOR entschlüsseln
    let result = '';
    for (let i = 0; i < decoded.length; i++) {
        result += String.fromCharCode(
            decoded.charCodeAt(i) ^ XOR_KEY.charCodeAt(i % XOR_KEY.length)
        );
    }

    return result;
}

function revealContact(button) {
    const type = button.dataset.contactType || '';
    const encodedData = button.dataset.contactValue || '';
    if (!type || !encodedData) {
        return;
    }

    const result = decodeContactData(encodedData);
    
    // Button durch Link ersetzen
    const link = document.createElement('a');
    link.className = 'contact-revealed';
    
    if (type === 'email') {
        link.href = 'mailto:' + result;
        link.innerHTML = '<span class="contact-icon">📧</span> ' + result;
    } else if (type === 'phone') {
        // Telefonnummer für tel:-Link formatieren (nur Ziffern und +)
        const telHref = result.replace(/[^\d+]/g, '');
        link.href = 'tel:' + telHref;
        link.innerHTML = '<span class="contact-icon">📞</span> ' + result;
    }
    
    button.replaceWith(link);
}

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-contact-reveal]');
    if (!trigger) {
        return;
    }

    event.preventDefault();
    revealContact(trigger);
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') {
        return;
    }

    const trigger = event.target.closest('[data-contact-reveal]');
    if (!trigger) {
        return;
    }

    event.preventDefault();
    revealContact(trigger);
});
