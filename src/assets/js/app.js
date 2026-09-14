/**
 * Zentraler Einstiegspunkt für die KGA Frontend- und Admin-Architektur.
 * Registriert alle Komponenten und mountet sie sicher über den Bootstrapper.
 *
 * Path: src/assets/js/app.js
 */

import { api } from './core/Api.js';
import { lazyMount, lazyMountSingle, mount, mountSingle } from './core/Bootstrapper.js';
import { ConsentBanner } from './ui/ConsentBanner.js';
import {
    AccordionCard,
    AutoSubmitSelect,
    ConfirmClick,
    ConfirmSubmit,
    EventTracker,
    FabRefresh,
    PrintControls,
    RemoteSubmit,
    SelectOnClick,
    TriggerClick,
} from './ui/GlobalInteractions.js';
import { PasswordToggle } from './ui/PasswordToggle.js';
import { SessionTimer } from './ui/SessionTimer.js';
import { ThemeToggle } from './ui/ThemeToggle.js';

// Stelle sicher, dass Metadaten im DOMContentLoaded rechtzeitig global verfügbar sind.
// Die Templates rendern das Array json_encode($tplMetadata) aus der Konfiguration.
if (typeof window.KGA_TEMPLATES === 'undefined') {
    // Fallback falls PHP es nicht rendert (Wird in PHTML eingebaut)
    window.KGA_TEMPLATES = {};
}

document.addEventListener('DOMContentLoaded', () => {
    // Globale Mini-Logiken (Isolierte Klassen für striktes Single Responsibility)
    mount('form[data-confirm]', ConfirmSubmit);
    mount('[data-confirm-click]', ConfirmClick);
    mount('.js-submit-form', RemoteSubmit);
    mount('.js-trigger-click', TriggerClick);
    mount('.js-select-on-click', SelectOnClick);
    mount('.c-category-card', AccordionCard);
    mount('.c-fab-refresh', FabRefresh);
    mount('.js-close-window, .js-print-window', PrintControls);
    mount('.js-auto-submit-select', AutoSubmitSelect);
    mount('.js-track-event', EventTracker);

    // Core / UI mounten
    mountSingle('#ui-session-timer', SessionTimer);
    mount('.js-password-toggle', PasswordToggle);
    mountSingle('#kga-consent-banner', ConsentBanner);
    mount('.js-theme-toggle', ThemeToggle);

    // Lazy Loading UI Module
    lazyMount('.js-avatar-dropzone', () => import('./ui/DragDropZone.js'), 'DragDropZone');
    lazyMount('.js-sort-table', () => import('./ui/TableSorter.js'), 'TableSorter');

    // Schwere Fach-Module Lazy Loaden (Spart hunderte KB beim initialen Seitenaufruf)
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

    // Admin-Module
    lazyMountSingle('.l-admin', () => import('./modules/PermissionMatrix.js'), 'PermissionMatrix');
    lazyMountSingle('.l-admin', () => import('./modules/AdminDashboard.js'), 'AdminDashboard');
    lazyMountSingle('#tab-stats', () => import('./modules/DashboardStats.js'), 'DashboardStats');
    lazyMountSingle('#tab-bank-import', () => import('./modules/BankImport.js'), 'BankImport');

    // Akku- und Netzwerk-Schonung. Nur pingen, wenn Tab aktiv ist!
    if (document.body.classList.contains('l-public-body')) {
        setInterval(
            () => {
                if (document.visibilityState === 'visible') {
                    api.post('api/ping');
                }
            },
            3 * 60 * 1000
        );
    }

    console.info('[KGA App] Core Architektur (Lazy Loaded) erfolgreich hochgefahren.');
});
