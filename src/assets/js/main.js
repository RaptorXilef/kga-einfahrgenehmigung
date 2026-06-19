/**
 * Globales Haupt-Einstiegs-Skript für Assets.
 * Loggt System-Zustände der Build-Infrastruktur (z.B. Vite Development Server Modus).
 *
 * Path: src/assets/js/main.js
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
if (import.meta.env.DEV) {
    // biome-ignore lint/suspicious/noConsole: Nur im Dev-Modus
    console.log('Vite läuft im Development-Modus');
}
