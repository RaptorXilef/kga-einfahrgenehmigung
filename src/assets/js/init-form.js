// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Path: src/assets/js/init-form.js
 * Path: public/assets/js/init-form.js
 *
 * Initialisiert den Form-Handler ohne PHP-Linter zu stören.
 */
import { PermitFormHandler } from './form-handler.js';

document.addEventListener('DOMContentLoaded', () => {
    if (!window.permitForm) {
        window.permitForm = new PermitFormHandler();
    }
});
