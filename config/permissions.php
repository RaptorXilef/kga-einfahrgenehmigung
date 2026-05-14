<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Master-Liste aller Berechtigungen im System.
 *
 * Es werden
 * Prefix-Wildcards: dashboard.*
 * und
 * Suffix-Wildcards: *.print
 * sowie
 * Negationen/Verbote: -dashboard.active.details
 * unterstützt!
 *
 * kategorie.bereich.aktion
 * <?php if ($auth->hasPermission('')) { ?>
 * <?php } ?>
 * <?php if ($auth->hasPermission('') || $auth->hasPermission('')) { ?>
 * <?php if ($auth->hasPermission('') && $auth->hasPermission('')): ?>
 *
 * Path: config/permissions.php
 */

declare(strict_types=1);

return [
    // === DASHBOARD FILTER (VIEW) ===
    'dashboard.control_bar.view' => 'Filter: Datums-Filter und Schnellsuche',

    // === DASHBOARD INFO/ALERT LEISTE (VIEW) ===
    'dashboard.info_alert.view' => 'Info: Ereignisanzeige sehen',

    // === DASHBOARD TABS (VIEW) ===
    'dashboard.view' => 'Dashboard: sehen',

    'dashboard.active.view'    => 'Tab: "Aktiv" sehen',
    'dashboard.finance.view'   => 'Tab: "Finanzen" sehen',
    'dashboard.future.view'    => 'Tab: "Zukünftig" sehen',
    'dashboard.expired.view'   => 'Tab: "Abgelaufen" sehen',
    'dashboard.stats.view'     => 'Tab: "Statistiken" sehen',
    'dashboard.ranking.view'   => 'Tab: "Ranking" sehen',
    'dashboard.export.view'    => 'Tab: "Export" sehen',
    'dashboard.vouchers.view'  => 'Tab: "Gutscheine" sehen',
    'dashboard.tools.view'     => 'Tab: "Werkzeuge" sehen',
    'dashboard.logs.view'      => 'Tab: "Mail-Logs" sehen',
    'dashboard.migration.view' => 'Tab: "Migration des Speichers" sehen',

    // === DASHBOARD FILTER AKTIONEN ===
    'dashboard.control_bar.future' => 'Filter: Datums-Filter nutzen',
    'dashboard.control_bar.search' => 'Filter: Schnellsuche nutzen',

    // === DASHBOARD INFO/ALERT LEISTE (VIEW) ===
    'dashboard.info_alert.print'   => 'Info: Genehmigungen drucken',
    'dashboard.info_alert.details' => 'Info: Details einsehen',

    // === DASHBOARD AKTIONEN ===
    // --- TABS: AKTIV ---
    'dashboard.active.print'   => 'Aktiv: Genehmigungen drucken',
    'dashboard.active.details' => 'Aktiv: Details einsehen',
    'dashboard.active.suspend' => 'Aktiv: Genehmigungen sperren',

    // --- TABS: FINANZEN ---
    'dashboard.finance.details'   => 'Finanzen: Details eines Belegvorgangs einsehen',
    'dashboard.finance.mark_paid' => 'Finanzen: Zahlungseingänge manuell bestätigen',

    // --- TABS: ZUKÜNFTIG ---
    'dashboard.future.print'   => 'Zukünftig: Genehmigungen drucken',
    'dashboard.future.details' => 'Zukünftig: Details einsehen',

    // --- TABS: ABGELAUFEN ---
    'dashboard.expired.print'   => 'Abgelaufen: Genehmigungen drucken',
    'dashboard.expired.details' => 'Abgelaufen: Details einsehen',

    // --- TABS: STATISTIKEN ---
    'dashboard.stats.current' => 'Statistiken: Aktuelles Jahr einsehen',
    'dashboard.stats.charts'  => 'Statistiken: Diagramme & Auswertungen anzeigen',
    'dashboard.stats.history' => 'Statistiken: Historische Daten (Vorjahre) einsehen',

    // --- TABS: EXPORT ---
    'dashboard.export.csv'  => 'Export: Genehmigungen als CSV (Excel) herunterladen',
    'dashboard.export.json' => 'Export: Vollständiger Datensatz als JSON (Sicherung) laden',

    // --- TABS: GUTSCHEINE ---
    'dashboard.vouchers.open'    => 'Gutscheine: Liste der noch offenen Codes anzeigen',
    'dashboard.vouchers.archive' => 'Gutscheine: Archiv bereits eingelöster Codes einsehen',
    'dashboard.vouchers.suspend' => 'Gutscheine: Aktivieren & Deaktivieren',
    'dashboard.vouchers.remove'  => 'Gutscheine: Löschen',

    // --- TABS: WERKZEUGE (TOOLS) ---
    'dashboard.tools.direct_issue.reveal'  => 'Werkzeuge: Bereich für manuelle Sofort-Ausstellung aufrufen',
    'dashboard.tools.direct_issue.execute' => 'Werkzeuge: Manuelle Genehmigungen final erstellen und speichern',
    'dashboard.tools.voucher_gen.reveal'   => 'Werkzeuge: Bereich für den Gutschein-Generator aufrufen',
    'dashboard.tools.voucher_gen.execute'  => 'Werkzeuge: Neue Gutscheincodes final generieren und speichern',

    // --- TABS: LOGS ---

    // === DASHBOARD: MIGRATION (SYSTEM) ===

    // --- Benutzerkonten ---
    'dashboard.migration.users.json_to_mysql' => 'Migration: Benutzer -> SQL (Überschreibt DB!)',
    'dashboard.migration.users.mysql_to_json' => 'Migration: Benutzer -> JSON (Überschreibt Datei!)',
    'dashboard.migration.users.sync'          => 'Migration: Benutzer Bestände zusammenführen',

    // --- Genehmigungen ---
    'dashboard.migration.permits.json_to_mysql' => 'Migration: Genehmigungen -> SQL (Überschreibt DB!)',
    'dashboard.migration.permits.mysql_to_json' => 'Migration: Genehmigungen -> JSON (Überschreibt Datei!)',
    'dashboard.migration.permits.sync'          => 'Migration: Genehmigungen Bestände zusammenführen',

    // --- Gutscheine ---
    'dashboard.migration.vouchers.json_to_mysql' => 'Migration: Gutscheine -> SQL (Überschreibt DB!)',
    'dashboard.migration.vouchers.mysql_to_json' => 'Migration: Gutscheine -> JSON (Überschreibt Datei!)',
    'dashboard.migration.vouchers.sync'          => 'Migration: Gutscheine Bestände zusammenführen',

    // --- E-Mail Protokolle ---
    'dashboard.migration.mail_log.json_to_mysql' => 'Migration: Mail-Logs -> SQL schieben',
    'dashboard.migration.mail_log.mysql_to_json' => 'Migration: Mail-Logs -> JSON (Überschreibt Datei!)',
    'dashboard.migration.mail_log.sync'          => 'Migration: Mail-Logs Bestände zusammenführen',

    // --- Warteraum (E-Mail Bestätigung) ---
    'dashboard.migration.pending_verification.json_to_mysql' => 'Migration: Warteraum -> SQL schieben',
    'dashboard.migration.pending_verification.mysql_to_json' => 'Migration: Warteraum -> JSON (Überschreibt Datei!)',
    'dashboard.migration.pending_verification.sync'          => 'Migration: Warteraum Bestände zusammenführen',

    // --- Login-Tokens ---
    'dashboard.migration.magic_links.json_to_mysql' => 'Migration: Login-Tokens -> SQL schieben',
    'dashboard.migration.magic_links.mysql_to_json' => 'Migration: Login-Tokens -> JSON (Überschreibt Datei!)',
    'dashboard.migration.magic_links.sync'          => 'Migration: Login-Tokens Bestände zusammenführen',

    // Optional: Sammelrechte für Logs
    'dashboard.migration.users.*'                => 'Migration: Alle Benutzer Aktionen',
    'dashboard.migration.permits.*'              => 'Migration: Alle Genehmigungs Aktionen',
    'dashboard.migration.vouchers.*'             => 'Migration: Alle Gutschein Aktionen',
    'dashboard.migration.mail_log.*'             => 'Migration: Alle Mail-Log Aktionen',
    'dashboard.migration.pending_verification.*' => 'Migration: Alle Warteraum Aktionen',
    'dashboard.migration.magic_links.*'          => 'Migration: Alle Token Aktionen',

    // --- Seite: check admin ---
    'check.admin.print' => 'Check-Admin: Genehmigungen drucken',

    // === DATEN & FINANZEN ===
    'finance.revenue.reveal' => 'Finanzen: Sensible Beträge und Umsätze enthüllen',
    'finance.export.execute' => 'Export: CSV/JSON Downloads erlauben',

    'privacy.email.reveal' => 'Datenschutz: E-Mail-Adressen im Klartext zeigen',

    // --- SYSTEM & ADMIN ---
    'system.users.manage' => 'System: Benutzer & Gruppen verwalten',

    // --- VORLAGEN (TEMPLATES) ---
    'template.std.7'       => 'Genehmigung: 7 Tage ausstellen',
    'template.std.14'      => 'Genehmigung: 14 Tage ausstellen',
    'template.std.30'      => 'Genehmigung: 1 Monat ausstellen',
    'template.perm.3'      => 'Genehmigung: Dauerkarten 3 Monate ausstellen',
    'template.perm.6'      => 'Genehmigung: Dauerkarten 6 Monate ausstellen',
    'template.perm.9'      => 'Genehmigung: Dauerkarten 9 Monate ausstellen',
    'template.perm.12'     => 'Genehmigung: Dauerkarten 12 Monate ausstellen',
    'template.custom.std'  => 'Genehmigung: Spezialzeiträume ausstellen',
    'template.custom.perm' => 'Genehmigung: Dauerkarten mit Spezialzeiträume ausstellen',
    'template.klause.std'  => 'Genehmigung: Klause Belieferung Spezialzeiträume ausstellen',

    // --- GLOBALE SAMMLER (Nur zur Info für den Admin) ---
    '*.view'    => 'Global: Alle Ansichten freischalten',
    '*.print'   => 'Global: Alle Druck-Buttons freischalten',
    '*.details' => 'Global: Alle Detail-Ansichten freischalten',
    '*.suspend' => 'Global: Alle Sperr-Funktionen freischalten',
    '*.manage'  => 'Global: Alle Verwaltungs-Funktionen freischalten',
    '*.execute' => 'Global: Alle System-Aktionen ausführen',
    '*.reveal'  => 'Global: Alle sensiblen Daten (Geld/Mail) einblenden',
    '*.sync'    => 'Global: Alle Synchronisierungen erlauben',

    '*' => 'MASTER-ZUGRIFF: Gott-Modus (Alle Rechte)',
];
