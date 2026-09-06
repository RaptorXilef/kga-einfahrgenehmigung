/**
 * Sammlung universeller Hilfsfunktionen ohne externe Abhängigkeiten.
 */

/**
 * Verhindert, dass eine Funktion zu oft in kurzer Zeit aufgerufen wird (z.B. bei Such-Inputs).
 * @param {Function} func Die auszuführende Funktion
 * @param {number} wait Verzögerung in Millisekunden
 */
export function debounce(func, wait = 250) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

/**
 * Drosselt eine Funktion so, dass sie maximal einmal pro Zeitintervall ausgeführt wird (z.B. für Scroll-Events).
 * @param {Function} func Die auszuführende Funktion
 * @param {number} limit Zeitintervall in Millisekunden
 */
export function throttle(func, limit) {
    let inThrottle;
    return function (...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => (inThrottle = false), limit);
        }
    };
}
