/**
 * Zentraler Einstiegspunkt für die KGA Frontend- und Admin-Architektur.
 * Registriert alle Komponenten und mountet sie sicher über den Bootstrapper.
 *
 * Path: src/assets/js/app.js
 */

import { mount, mountSingle } from './core/Bootstrapper.js';
import { PermitForm } from './modules/PermitForm.js';
import { SystemTools } from './modules/SystemTools.js';
import { VoucherManager } from './modules/VoucherManager.js';
import { PasswordToggle } from './ui/PasswordToggle.js';
import { SessionTimer } from './ui/SessionTimer.js';

// Stelle sicher, dass Metadaten im DOMContentLoaded rechtzeitig global verfügbar sind.
// Die Templates rendern das Array json_encode($tplMetadata) aus der Konfiguration.
if (typeof window.KGA_TEMPLATES === 'undefined') {
    // Fallback falls PHP es nicht rendert (Wird in PHTML eingebaut)
    window.KGA_TEMPLATES = {};
}

document.addEventListener('DOMContentLoaded', () => {
    // 1. Core / UI Components
    mountSingle('#ui-session-timer', SessionTimer);
    mount('.js-password-toggle', PasswordToggle);

    // 2. Modul-Initialisierungen
    mount('#permitForm, form[action*="create_voucher"]', PermitForm);
    mountSingle('#tab-system', SystemTools);
    mountSingle('#tab-vouchers', VoucherManager);

    console.info('[KGA App] Core Architektur (Phase 3) erfolgreich hochgefahren.');
});
