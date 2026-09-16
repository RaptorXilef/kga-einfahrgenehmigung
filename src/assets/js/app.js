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
    CopyAction,
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

// 1. Service Worker Registrierung (Non-Blocking)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        const baseUrl = window.KGA_CONFIG?.baseUrl || '/';
        navigator.serviceWorker
            .register(`${baseUrl}sw.js`)
            .then((registration) => {
                console.info(
                    '[PWA] Service Worker erfolgreich registriert. Scope:',
                    registration.scope
                );
            })
            .catch((error) => {
                console.error('[PWA] Service Worker Registrierung fehlgeschlagen:', error);
            });
    });
}

// 2. DOM Hydration
document.addEventListener('DOMContentLoaded', () => {
    // Globale Mini-Logiken
    mount('form[data-confirm]', ConfirmSubmit);
    mount('[data-confirm-click]', ConfirmClick);
    mount('.js-submit-form', RemoteSubmit);
    mount('.js-trigger-click', TriggerClick);
    mount('.js-select-on-click', SelectOnClick);
    mount('.js-copy-btn', CopyAction);
    mount('.c-category-card', AccordionCard);
    mount('.c-fab-refresh', FabRefresh);
    mount('.js-close-window, .js-print-window', PrintControls);
    mount('.js-auto-submit-select', AutoSubmitSelect);
    mount('.js-track-event', EventTracker);

    // Core / UI mounten
    mountSingle('.js-session-timer', SessionTimer);
    mount('.js-password-toggle', PasswordToggle);
    mountSingle('.js-consent-banner', ConsentBanner);
    mount('.js-theme-toggle', ThemeToggle);

    // Lazy Loading - Strikte JS Hooks
    lazyMount('.js-avatar-dropzone', () => import('./ui/DragDropZone.js'), 'DragDropZone');
    lazyMount('.js-sort-table', () => import('./ui/TableSorter.js'), 'TableSorter');

    lazyMount('.js-permit-form', () => import('./modules/PermitForm.js'), 'PermitForm');
    lazyMountSingle('.js-system-tools', () => import('./modules/SystemTools.js'), 'SystemTools');
    lazyMountSingle(
        '.js-voucher-manager',
        () => import('./modules/VoucherManager.js'),
        'VoucherManager'
    );
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
        '.js-release-notes-modal',
        () => import('./modules/ReleaseNotes.js'),
        'ReleaseNotes'
    );

    // Admin-Module
    lazyMountSingle(
        '.js-permission-matrix',
        () => import('./modules/PermissionMatrix.js'),
        'PermissionMatrix'
    );
    lazyMountSingle(
        '.js-admin-dashboard',
        () => import('./modules/AdminDashboard.js'),
        'AdminDashboard'
    );
    lazyMountSingle(
        '.js-dashboard-stats',
        () => import('./modules/DashboardStats.js'),
        'DashboardStats'
    );
    lazyMountSingle('.js-bank-import', () => import('./modules/BankImport.js'), 'BankImport');

    // Akku- und Netzwerk-Schonung. Strikte BEM-Kopplung
    const publicForm = document.querySelector('.js-permit-form');
    if (publicForm) {
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
