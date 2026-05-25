/**
 * Initialisierungs-Skript für den PermitFormHandler.
 * Stellt sicher, dass das Formular-Management-Modul sauber geladen wird,
 * ohne Namespacing-Konflikte oder PHP-Linter-Warnungen im Build-Prozess zu provozieren.
 * Kontext: Bootstrap-Skript für die Client-seitige Antrags-Validierung.
 *
 * Path: src/assets/js/init-form.js
 * Path: public/assets/js/init-form.min.js
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
import { PermitFormHandler } from './form-handler.js';

document.addEventListener('DOMContentLoaded', () => {
    if (!window.permitForm) {
        window.permitForm = new PermitFormHandler();
    }
});
