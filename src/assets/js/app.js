/**
 * Zentraler Einstiegspunkt für die KGA Frontend- und Admin-Architektur.
 * Registriert alle Komponenten und mountet sie sicher über den Bootstrapper.
 *
 * Path: src/assets/js/app.js
 */

import { mountSingle } from './core/Bootstrapper.js';
import { SessionTimer } from './ui/SessionTimer.js';

// Weitere Imports folgen in den nächsten Phasen (z.B. PermitForm, TableSorter, etc.)

document.addEventListener('DOMContentLoaded', () => {
    // 1. Session Timer mounten (falls das Element #ui-session-timer existiert)
    // Dieses Element gibt es in der history_list.phtml und im admin/header_nav.phtml
    mountSingle('#ui-session-timer', SessionTimer);

    // 2. Platz für künftige Mounts
    // mountSingle('#permitForm', PermitForm);
    // mount('.js-password-toggle', PasswordToggle);
    // ...

    console.info('[KGA App] Core Architektur erfolgreich hochgefahren.');
});
