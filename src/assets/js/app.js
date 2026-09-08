/**
 * Zentraler Einstiegspunkt für die KGA Frontend- und Admin-Architektur.
 * Registriert alle Komponenten und mountet sie sicher über den Bootstrapper.
 *
 * Path: src/assets/js/app.js
 */

import { api } from './core/Api.js';
import { lazyMount, lazyMountSingle, mount, mountSingle } from './core/Bootstrapper.js';
import { ConsentBanner } from './ui/ConsentBanner.js';
import { PasswordToggle } from './ui/PasswordToggle.js';
import { SessionTimer } from './ui/SessionTimer.js';

// Stelle sicher, dass Metadaten im DOMContentLoaded rechtzeitig global verfügbar sind.
// Die Templates rendern das Array json_encode($tplMetadata) aus der Konfiguration.
if (typeof window.KGA_TEMPLATES === 'undefined') {
    // Fallback falls PHP es nicht rendert (Wird in PHTML eingebaut)
    window.KGA_TEMPLATES = {};
}

document.addEventListener('DOMContentLoaded', () => {
    // --- 1. GLOBAL UNOBTRUSIVE UI BEHAVIORS (CSP-COMPLIANT) ---
    document.body.addEventListener('submit', (e) => {
        const form = e.target;
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            e.preventDefault();
        }
    });

    document.body.addEventListener('click', (e) => {
        // Klick-Bestätigungen für Buttons
        const confirmBtn = e.target.closest('[data-confirm-click]');
        if (confirmBtn && !window.confirm(confirmBtn.dataset.confirmClick)) {
            e.preventDefault();
            return;
        }

        // Remote Form Submit (Löst ein Formular anhand seiner ID aus)
        const submitBtn = e.target.closest('.js-submit-form');
        if (submitBtn) {
            e.preventDefault();
            const targetId = submitBtn.dataset.target;
            const form = document.getElementById(targetId);
            if (form) form.requestSubmit();
        }

        // Click-Weiterleitung (z.B. für versteckte File-Inputs)
        const triggerBtn = e.target.closest('.js-trigger-click');
        if (triggerBtn) {
            e.preventDefault();
            const target = document.getElementById(triggerBtn.dataset.target);
            if (target) target.click();
        }

        // Text-Feld bei Klick markieren
        if (e.target.classList.contains('js-select-on-click')) {
            e.target.select();
        }

        // Accordion-Karten einklappen (Admin-Rollen)
        const toggleBtn = e.target.closest('.js-toggle-parent');
        if (toggleBtn) {
            toggleBtn.parentElement.parentElement.classList.toggle('is-closed');
        }

        // Globaler Reload-Button
        const refreshBtn = e.target.closest('.c-fab-refresh');
        if (refreshBtn) {
            e.preventDefault();
            window.location.href =
                window.location.origin + window.location.pathname + window.location.search;
        }

        // Globaler Close-Window Button (Druckansicht)
        const closeWindowBtn = e.target.closest('.js-close-window');
        if (closeWindowBtn) {
            e.preventDefault();
            window.close();
        }

        // Globaler Print-Window Button (Druckansicht)
        const printWindowBtn = e.target.closest('.js-print-window');
        if (printWindowBtn) {
            e.preventDefault();
            window.print();
        }
    });

    // Auto-Submit für Select-Boxen (Admin Dashboard Filter)
    document.body.addEventListener('change', (e) => {
        if (e.target.classList.contains('js-auto-submit-select')) {
            e.target.closest('form').requestSubmit();
        }
    });
    // ----------------------------------------------------------

    // 2. Core / UI mounten (Synchron)
    mountSingle('#ui-session-timer', SessionTimer);
    mount('.js-password-toggle', PasswordToggle);
    mountSingle('#kga-consent-banner', ConsentBanner);

    // 3. Komplexe UI-Elemente Lazy Loaden (Nur wenn sie im DOM existieren)
    lazyMount('.js-avatar-dropzone', () => import('./ui/DragDropZone.js'), 'DragDropZone');
    lazyMount('.js-sort-table', () => import('./ui/TableSorter.js'), 'TableSorter');

    // 4. Schwere Module Lazy Loaden (Spart hunderte KB beim initialen Seitenaufruf)
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

    // Mini-Logiken (Events & Pings) zentralisieren
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
