/**
 * AccordionTile JavaScript
 * 
 * Interaktionslogik für Akkordeon:
 * - Auf-/Zuklappen von Bereichen
 * - Optional: Nur ein Bereich gleichzeitig offen
 * - Optional: Auto-Scroll zum geöffneten Bereich
 */

function initAccordions() {
    const accordions = document.querySelectorAll('.accordion');
    
    accordions.forEach(accordion => {
        const singleOpen = accordion.dataset.singleOpen === 'true';
        const autoScroll = accordion.dataset.autoScroll || 'mobile';
        const headers = accordion.querySelectorAll('.accordion-header');
        
        headers.forEach(header => {
            header.addEventListener('click', () => {
                const item = header.closest('.accordion-item');
                const isCurrentlyOpen = item.classList.contains('open');
                
                // Bei singleOpen: Alle anderen schließen
                if (singleOpen && !isCurrentlyOpen) {
                    accordion.querySelectorAll('.accordion-item.open').forEach(openItem => {
                        closeAccordionItem(openItem);
                    });
                }
                
                // Toggle aktuelles Item
                if (isCurrentlyOpen) {
                    closeAccordionItem(item);
                } else {
                    openAccordionItem(item);
                    
                    // Auto-Scroll
                    if (shouldAutoScroll(autoScroll)) {
                        setTimeout(() => {
                            item.scrollIntoView({ 
                                behavior: 'smooth', 
                                block: 'nearest'
                            });
                        }, 100);
                    }
                }
            });
            
            // Keyboard support
            header.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    header.click();
                }
            });
        });
    });
}

function openAccordionItem(item) {
    item.classList.add('open');
    const header = item.querySelector('.accordion-header');
    if (header) {
        header.setAttribute('aria-expanded', 'true');
    }
}

function closeAccordionItem(item) {
    item.classList.remove('open');
    const header = item.querySelector('.accordion-header');
    if (header) {
        header.setAttribute('aria-expanded', 'false');
    }
}

function shouldAutoScroll(setting) {
    if (setting === 'always') return true;
    if (setting === 'never') return false;
    if (setting === 'mobile') {
        return window.innerWidth < 768;
    }
    return false;
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAccordions);
} else {
    initAccordions();
}
