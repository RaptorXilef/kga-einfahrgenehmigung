/**
 * @file init-form.js
 * Initialisiert den Form-Handler ohne PHP-Linter zu stören.
 */
import { PermitFormHandler } from './form-handler.js';

document.addEventListener('DOMContentLoaded', () => {
    if (!window.permitForm) {
        window.permitForm = new PermitFormHandler();
    }
});
