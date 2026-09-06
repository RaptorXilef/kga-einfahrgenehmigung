/**
 * Zentraler Einstiegspunkt für die KGA Frontend- und Admin-Architektur.
 * Registriert alle Komponenten und mountet sie sicher über den Bootstrapper.
 *
 * Path: src/assets/js/app.js
 */

import { api } from './core/Api.js';
import { mount, mountSingle } from './core/Bootstrapper.js';
import { AdminDashboard } from './modules/AdminDashboard.js';
import { BankImport } from './modules/BankImport.js';
import { ChangelogRenderer } from './modules/ChangelogRenderer.js';
import { CheckoutPayment } from './modules/CheckoutPayment.js';
import { DashboardStats } from './modules/DashboardStats.js';
import { PermissionMatrix } from './modules/PermissionMatrix.js';
import { PermitForm } from './modules/PermitForm.js';
import { ReleaseNotes } from './modules/ReleaseNotes.js';
import { SystemTools } from './modules/SystemTools.js';
import { VoucherManager } from './modules/VoucherManager.js';
import { ConsentBanner } from './ui/ConsentBanner.js';
import { DragDropZone } from './ui/DragDropZone.js';
import { PasswordToggle } from './ui/PasswordToggle.js';
import { SessionTimer } from './ui/SessionTimer.js';
import { TableSorter } from './ui/TableSorter.js';

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
    mount('.js-sort-table', TableSorter);
    mountSingle('#kga-consent-banner', ConsentBanner);

    // 2. Komplexe Module mounten
    mount('#permitForm, form[action*="create_voucher"]', PermitForm);
    mountSingle('#tab-system', SystemTools);
    mountSingle('#tab-vouchers', VoucherManager);
    mountSingle('.js-checkout-payment', CheckoutPayment);
    mountSingle('.js-changelog-renderer', ChangelogRenderer);
    mountSingle('#release-notes-modal', ReleaseNotes);

    // Die Rechteverwaltung mountet sich auf das Element, das Admin-Tab-Users umschließt
    mountSingle('.l-admin', PermissionMatrix);
    mountSingle('.l-admin', AdminDashboard);
    mountSingle('#tab-stats', DashboardStats);
    mountSingle('#tab-bank-import', BankImport);

    // 3. Mini-Logiken (Events & Pings) zentralisieren
    document.querySelectorAll('.js-track-event').forEach((el) => {
        if (el.dataset.event && typeof window.dataLayer !== 'undefined') {
            window.dataLayer.push({ event: el.dataset.event });
        }
    });

    if (document.body.classList.contains('l-public-body')) {
        setInterval(() => api.post('api/ping').catch(() => {}), 3 * 60 * 1000);
    }

    console.info('[KGA App] Core Architektur (Finale Phase) erfolgreich hochgefahren.');
});
