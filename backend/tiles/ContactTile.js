/* ContactTile JavaScript - Anti-Crawler Reveal */

function revealContact(button, type, encodedData) {
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
