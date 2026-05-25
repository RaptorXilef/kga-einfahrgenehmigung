<?php

/**
 * Technische System-Konfiguration
 *
 * Path: config/config.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

return [
    // --- UMGEBUNGSSTEUERUNG ---
    /**
     * GLOBALER TEST-MODUS
     * true  => PayPal Sandbox & Kein echter Mailversand (wenn test_mail_active = false)
     * false => PayPal LIVE & Echter Mailversand
     */
    'test_mode' => false, // TRUE = Sandbox & Test-Mails | FALSE = Live & Echt-Mails

    /**
     * ADMIN DEV MODE
     * true  => Überspringt den Login in /admin.php (Vollzugriff für Entwicklung)
     * false => Login zwingend erforderlich
     */
    'admin_dev_mode' => false, // TRUE = Kein Admin-Login nötig

    // Wenn true, werden alle/bestimmte Seiten umgeleitet auf die maintenance.php
    'maintenance_mode'       => false, // Pächter-Seiten sperren
    'maintenance_mode_admin' => false, // AUCH Admin-Seiten sperren
];
