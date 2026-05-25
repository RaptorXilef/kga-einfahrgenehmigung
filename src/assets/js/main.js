/**
 * Globales Haupt-Einstiegs-Skript für Assets.
 * Loggt System-Zustände der Build-Infrastruktur (z.B. Vite Development Server Modus).
 *
 * Path: src/assets/js/main.js
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
if (import.meta.env.DEV) {
    // biome-ignore lint/suspicious/noConsole: Nur im Dev-Modus
    console.log('Vite läuft im Development-Modus');
}
