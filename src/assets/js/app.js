/**
 * Zentraler Einstiegspunkt für die KGA Frontend- und Admin-Architektur.
 * Registriert alle Komponenten und mountet sie sicher über den Bootstrapper.
 *
 * Path: src/assets/js/app.js
 */

import { mount, mountSingle } from './core/Bootstrapper.js';
import { PermissionMatrix } from './modules/PermissionMatrix.js';
import { PermitForm } from './modules/PermitForm.js';
import { SystemTools } from './modules/SystemTools.js';
import { VoucherManager } from './modules/VoucherManager.js';
import { DragDropZone } from './ui/DragDropZone.js';
import { PasswordToggle } from './ui/PasswordToggle.js';
import { SessionTimer } from './ui/SessionTimer.js';

// Stelle sicher, dass Metadaten im DOMContentLoaded rechtzeitig global verfügbar sind.
// Die Templates rendern das Array json_encode($tplMetadata) aus der Konfiguration.
if (typeof window.KGA_TEMPLATES === 'undefined') {
    // Fallback falls PHP es nicht rendert (Wird in PHTML eingebaut)
    window.KGA_TEMPLATES = {};
}

document.addEventListener('DOMContentLoaded', () => {
    // 1. Core / UI mounten
    mountSingle('#ui-session-timer', SessionTimer);
    mount('.js-password-toggle', PasswordToggle);
    mount('.js-avatar-dropzone', DragDropZone);

    // 2. Komplexe Module mounten
    mount('#permitForm, form[action*="create_voucher"]', PermitForm);
    mountSingle('#tab-system', SystemTools);
    mountSingle('#tab-vouchers', VoucherManager);

    // Die Rechteverwaltung mountet sich auf das Element, das Admin-Tab-Users umschließt
    mountSingle('.l-admin', PermissionMatrix);

    console.info('[KGA App] Core Architektur (Phase 4) erfolgreich hochgefahren.');
});
