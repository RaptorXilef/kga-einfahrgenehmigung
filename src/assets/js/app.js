/**
 * Zentraler Einstiegspunkt für die KGA Frontend- und Admin-Architektur.
 * Registriert alle Komponenten und mountet sie sicher über den Bootstrapper.
 *
 * Path: src/assets/js/app.js
 */

import { mount, mountSingle } from './core/Bootstrapper.js';
import { PermitForm } from './modules/PermitForm.js';
import { SessionTimer } from './ui/SessionTimer.js';

// Stelle sicher, dass Metadaten im DOMContentLoaded rechtzeitig global verfügbar sind.
// Die Templates rendern das Array json_encode($tplMetadata) aus der Konfiguration.
if (typeof window.KGA_TEMPLATES === 'undefined') {
    // Fallback falls PHP es nicht rendert (Wird in PHTML eingebaut)
    window.KGA_TEMPLATES = {};
}

document.addEventListener('DOMContentLoaded', () => {
    // 1. Session Timer mounten
    mountSingle('#ui-session-timer', SessionTimer);

    // 2. Formularkomponenten mounten
    // Sucht das Frontend-Formular, das Manuelle-Admin Formular und das Gutschein-Formular.
    mount('#permitForm, form[action*="create_voucher"]', PermitForm);

    console.info('[KGA App] Core Architektur (Phase 2) erfolgreich hochgefahren.');
});
