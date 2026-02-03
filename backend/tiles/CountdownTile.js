/* CountdownTile JavaScript */

function initCountdowns() {
    const countdowns = document.querySelectorAll('.countdown-display');
    
    countdowns.forEach(countdown => {
        const target = new Date(countdown.dataset.target);
        const mode = countdown.dataset.mode || 'dynamic';
        const expiredText = countdown.dataset.expiredText || 'Abgelaufen';
        const hideOnExpiry = countdown.dataset.hideOnExpiry === 'true';
        
        function updateCountdown() {
            const now = new Date();
            const diff = target - now;
            
            // Abgelaufen?
            if (diff <= 0) {
                if (hideOnExpiry) {
                    // Ganze Tile verstecken
                    const tile = countdown.closest('.tile');
                    if (tile) tile.style.display = 'none';
                } else {
                    countdown.innerHTML = '<div class="countdown-expired">' + expiredText + '</div>';
                }
                return false; // Interval stoppen
            }
            
            // Zeit berechnen
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);
            
            let display = '';
            
            switch (mode) {
                case 'days':
                    const totalDays = Math.ceil(diff / (1000 * 60 * 60 * 24));
                    display = '<span class="countdown-value">' + totalDays + '</span> ' + 
                             (totalDays === 1 ? 'Tag' : 'Tage');
                    break;
                    
                case 'hours':
                    const totalHours = Math.ceil(diff / (1000 * 60 * 60));
                    display = '<span class="countdown-value">' + totalHours + '</span> ' + 
                             (totalHours === 1 ? 'Stunde' : 'Stunden');
                    break;
                    
                case 'timer':
                    display = '<div class="countdown-timer">' +
                        '<span class="countdown-segment"><span class="countdown-value">' + String(days).padStart(2, '0') + '</span><span class="countdown-label">Tage</span></span>' +
                        '<span class="countdown-separator">:</span>' +
                        '<span class="countdown-segment"><span class="countdown-value">' + String(hours).padStart(2, '0') + '</span><span class="countdown-label">Std</span></span>' +
                        '<span class="countdown-separator">:</span>' +
                        '<span class="countdown-segment"><span class="countdown-value">' + String(minutes).padStart(2, '0') + '</span><span class="countdown-label">Min</span></span>' +
                        '<span class="countdown-separator">:</span>' +
                        '<span class="countdown-segment"><span class="countdown-value">' + String(seconds).padStart(2, '0') + '</span><span class="countdown-label">Sek</span></span>' +
                    '</div>';
                    break;
                    
                case 'dynamic':
                default:
                    if (days > 7) {
                        display = 'noch <span class="countdown-value">' + days + '</span> Tage';
                    } else if (days >= 1) {
                        display = 'noch <span class="countdown-value">' + days + '</span> ' + 
                                 (days === 1 ? 'Tag' : 'Tage') + ' und ' + 
                                 '<span class="countdown-value">' + hours + '</span> ' + 
                                 (hours === 1 ? 'Stunde' : 'Stunden');
                    } else if (hours >= 1) {
                        display = 'noch <span class="countdown-value">' + hours + '</span> ' + 
                                 (hours === 1 ? 'Stunde' : 'Stunden') + ' und ' +
                                 '<span class="countdown-value">' + minutes + '</span> ' + 
                                 (minutes === 1 ? 'Minute' : 'Minuten');
                    } else {
                        display = 'noch <span class="countdown-value">' + minutes + '</span> ' + 
                                 (minutes === 1 ? 'Minute' : 'Minuten') + ' und ' +
                                 '<span class="countdown-value">' + seconds + '</span> ' + 
                                 (seconds === 1 ? 'Sekunde' : 'Sekunden');
                    }
                    break;
            }
            
            countdown.innerHTML = '<div class="countdown-content">' + display + '</div>';
            return true; // Weiter aktualisieren
        }
        
        // Initial ausführen
        if (updateCountdown()) {
            // Jede Sekunde aktualisieren
            const interval = setInterval(() => {
                if (!updateCountdown()) {
                    clearInterval(interval);
                }
            }, 1000);
        }
    });
}
