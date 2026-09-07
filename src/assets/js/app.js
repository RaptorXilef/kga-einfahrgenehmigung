/**
 * Zentraler Einstiegspunkt für die KGA Frontend- und Admin-Architektur.
 * Registriert alle Komponenten und mountet sie sicher über den Bootstrapper.
 *
 * Path: src/assets/js/app.js
 */

import { api } from './core/Api.js';
import { lazyMount, lazyMountSingle, mount, mountSingle } from './core/Bootstrapper.js';
import { ConsentBanner } from './ui/ConsentBanner.js';
// Kritische Core-UI Komponenten laden wir weiterhin synchron (UX-Priorität)
import { PasswordToggle } from './ui/PasswordToggle.js';
import { SessionTimer } from './ui/SessionTimer.js';

// Stelle sicher, dass Metadaten im DOMContentLoaded rechtzeitig global verfügbar sind.
// Die Templates rendern das Array json_encode($tplMetadata) aus der Konfiguration.
if (typeof window.KGA_TEMPLATES === 'undefined') {
    // Fallback falls PHP es nicht rendert (Wird in PHTML eingebaut)
    window.KGA_TEMPLATES = {};
}

document.addEventListener('DOMContentLoaded', () => {
    // 1. Core / UI mounten (Synchron)
    mountSingle('#ui-session-timer', SessionTimer);
    mount('.js-password-toggle', PasswordToggle);
    mountSingle('#kga-consent-banner', ConsentBanner);

    // 2. Komplexe UI-Elemente Lazy Loaden (Nur wenn sie im DOM existieren)
    lazyMount('.js-avatar-dropzone', () => import('./ui/DragDropZone.js'), 'DragDropZone');
    lazyMount('.js-sort-table', () => import('./ui/TableSorter.js'), 'TableSorter');

    // 3. Schwere Module Lazy Loaden (Spart hunderte KB beim initialen Seitenaufruf)
    lazyMount(
        '#permitForm, form[action*="create_voucher"]',
        () => import('./modules/PermitForm.js'),
        'PermitForm'
    );
    lazyMountSingle('#tab-system', () => import('./modules/SystemTools.js'), 'SystemTools');
    lazyMountSingle('#tab-vouchers', () => import('./modules/VoucherManager.js'), 'VoucherManager');
    lazyMountSingle(
        '.js-checkout-payment',
        () => import('./modules/CheckoutPayment.js'),
        'CheckoutPayment'
    );
    lazyMountSingle(
        '.js-changelog-renderer',
        () => import('./modules/ChangelogRenderer.js'),
        'ChangelogRenderer'
    );
    lazyMountSingle(
        '#release-notes-modal',
        () => import('./modules/ReleaseNotes.js'),
        'ReleaseNotes'
    );

    lazyMountSingle('.l-admin', () => import('./modules/PermissionMatrix.js'), 'PermissionMatrix');
    lazyMountSingle('.l-admin', () => import('./modules/AdminDashboard.js'), 'AdminDashboard');
    lazyMountSingle('#tab-stats', () => import('./modules/DashboardStats.js'), 'DashboardStats');
    lazyMountSingle('#tab-bank-import', () => import('./modules/BankImport.js'), 'BankImport');

    // 4. Mini-Logiken (Events & Pings) zentralisieren
    document.querySelectorAll('.js-track-event').forEach((el) => {
        if (el.dataset.event && typeof window.dataLayer !== 'undefined') {
            window.dataLayer.push({ event: el.dataset.event });
        }
    });

    // Akku- und Netzwerk-Schonung. Nur pingen, wenn Tab aktiv ist!
    if (document.body.classList.contains('l-public-body')) {
        setInterval(
            () => {
                if (document.visibilityState === 'visible') {
                    // Redundanten catch() Block entfernt, da api.post nicht rejected
                    api.post('api/ping');
                }
            },
            3 * 60 * 1000
        );
    }

    console.info('[KGA App] Core Architektur (Lazy Loaded) erfolgreich hochgefahren.');
});
