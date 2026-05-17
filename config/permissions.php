<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Master-Struktur der Berechtigungen (Der Baum)
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
    // --- UI EINSTELLUNGEN ---
    'admin_ui' => [
        'permissions_desc_on_top' => true, // true = Beschreibung oben | false = Key oben
    ],
    // --- DIE RECHTE-LISTE ---
    'structure' => [
        'privacy' => [
            'label'    => 'Daten & Finanzen (Datenschutz)',
            'children' => [
                'finance_reveal' => ['label' => 'Finanzen: Sensible Beträge und Umsätze enthüllen', 'key' => 'privacy.finance.reveal'],
                'email_reveal'   => ['label' => 'Datenschutz: E-Mail-Adressen im Klartext zeigen', 'key' => 'privacy.email.reveal'],
            ],
        ],

        'check' => [
            'label'    => 'Seite: Check Admin',
            'children' => [
                'print' => ['label' => 'Drucken', 'key' => 'check.admin.print'],
            ],
        ],
        'dashboard' => [
            'label'    => 'Zugriff auf Dashboard & Statistiken',
            'key'      => 'dashboard.view',
            'children' => [
                'control_bar' => [
                    'label'    => 'Filter-Leiste (Suche/Datum) anzeigen',
                    'key'      => 'dashboard.control_bar.view',
                    'children' => [
                        'future' => ['label' => 'Datums-Filter nutzen', 'key' => 'dashboard.control_bar.future'],
                        'search' => ['label' => 'Schnellsuche nutzen', 'key' => 'dashboard.control_bar.search'],
                    ],
                ],
                'info_alert' => [
                    'label'    => 'Ereignisanzeige sehen',
                    'key'      => 'dashboard.info_alert.view',
                    'children' => [
                        'future' => ['label' => 'Drucken', 'key' => 'dashboard.info_alert.print'],
                        'search' => ['label' => 'Details einsehen', 'key' => 'dashboard.info_alert.details'],
                    ],
                ],
                'active' => [
                    'label'    => 'Tab: Aktive Genehmigungen sehen',
                    'key'      => 'dashboard.active.view',
                    'children' => [
                        'print'   => ['label' => 'Drucken', 'key' => 'dashboard.active.print'],
                        'details' => ['label' => 'Details einsehen', 'key' => 'dashboard.active.details'],
                        'suspend' => ['label' => 'Sperren', 'key' => 'dashboard.active.suspend'],
                    ],
                ],
                'finance' => [
                    'label'    => 'Tab: Finanzen',
                    'key'      => 'dashboard.finance.view',
                    'children' => [
                        'details'   => ['label' => 'Beleg-Details', 'key' => 'dashboard.finance.details'],
                        'mark_paid' => ['label' => 'Zahlung bestätigen', 'key' => 'dashboard.finance.mark_paid'],
                    ],
                ],
                'future' => [
                    'label'    => 'Tab: Zukünftige Genehmigungen sehen',
                    'key'      => 'dashboard.future.view',
                    'children' => [
                        'print'   => ['label' => 'Drucken', 'key' => 'dashboard.future.print'],
                        'details' => ['label' => 'Details einsehen', 'key' => 'dashboard.future.details'],
                    ],
                ],
                'expired' => [
                    'label'    => 'Tab: Abgelaufene Genehmigungen sehen',
                    'key'      => 'dashboard.expired.view',
                    'children' => [
                        'print'   => ['label' => 'Drucken', 'key' => 'dashboard.expired.print'],
                        'details' => ['label' => 'Details einsehen', 'key' => 'dashboard.expired.details'],
                    ],
                ],
                'stats' => [
                    'label'    => 'Tab: Statistiken sehen',
                    'key'      => 'dashboard.stats.view',
                    'children' => [
                        'current' => ['label' => 'Aktuelles Jahr einsehen', 'key' => 'dashboard.stats.current'],
                        'charts'  => ['label' => 'Diagramme & Auswertungen anzeigen', 'key' => 'dashboard.stats.charts'],
                        'history' => ['label' => 'Historische Daten (Vorjahre) einsehen', 'key' => 'dashboard.stats.history'],
                    ],
                ],
                'ranking' => [
                    'label' => 'Tab: Ranking sehen',
                    'key'   => 'dashboard.ranking.view',
                ],
                'export' => [
                    'label'    => 'Tab: "Export" sehen',
                    'key'      => 'dashboard.export.view',
                    'children' => [
                        'export' => ['label' => 'Export: Downloads erlauben', 'key' => 'finance.export.execute'],
                        'csv'    => ['label' => 'Genehmigungen als CSV (Excel) herunterladen', 'key' => 'dashboard.export.csv'],
                        'json'   => ['label' => 'Vollständiger Datensatz als JSON (Sicherung) laden', 'key' => 'dashboard.export.json'],
                    ],
                ],
            ],
            'vouchers' => [
                'label'    => 'Tab: Gutscheine sehen',
                'key'      => 'dashboard.vouchers.view',
                'children' => [
                    /* 'manage' => ['label' => 'Gutscheine verwalten', 'key' => 'dashboard.vouchers.manage'], */
                    'open' => [
                        'label'    => 'Ungenutzte Codes einsehen',
                        'key'      => 'dashboard.vouchers.open',
                        'children' => [
                            'suspend' => ['label' => 'Gutscheine: Aktivieren & Deaktivieren', 'key' => 'dashboard.vouchers.suspend'],
                            'remove'  => ['label' => 'Gutscheine: Löschen', 'key' => 'dashboard.vouchers.remove'],
                        ],
                    ],
                    'archive' => ['label' => 'Archiv einsehen', 'key' => 'dashboard.vouchers.archive'],
                ],
            ],
            'tools' => [
                'label'    => 'Tab: Werkzeuge sehen',
                'key'      => 'dashboard.tools.view',
                'children' => [
                    'direct_issue' => [
                        'label'    => 'Bereich für manuelle Sofort-Ausstellung aufrufen',
                        'key'      => 'dashboard.tools.direct_issue.reveal',
                        'children' => [
                            'execute' => ['label' => 'Manuelle Genehmigungen final erstellen und speichern', 'key' => 'dashboard.tools.direct_issue.execute'],
                        ],
                    ],
                    'voucher_gen' => [
                        'label'    => 'Bereich für den Gutschein-Generator aufrufen',
                        'key'      => 'dashboard.tools.voucher_gen.reveal',
                        'children' => [
                            'execute' => ['label' => 'Neue Gutscheincodes final generieren und speichern', 'key' => 'dashboard.tools.voucher_gen.execute'],
                        ],
                    ],
                ],
            ],
            'logs' => [
                'label' => 'Tab: Mail-Logs sehen',
                'key'   => 'dashboard.logs.view',
            ],
            'migration' => [
                'label'    => 'Tab: Migration des Speichers sehen (ADMINISTRATIV!)',
                'key'      => 'dashboard.migration.view',
                'children' => [
                    'sync' => [
                        'label'    => 'Migration: Tabelle für JSON/SQL-Transfer und Synchronisierung anzeigen',
                        'key'      => 'dashboard.migration.sync.view',
                        'children' => [
                            'users' => [
                                'label'    => 'Migration: Alle Benutzer Aktionen',
                                'key'      => 'dashboard.migration.users.view',
                                'children' => [
                                    'json_to_mysql' => ['label' => 'Migration: Benutzer -> SQL (Überschreibt DB!)', 'key' => 'dashboard.migration.users.json_to_mysql'],
                                    'mysql_to_json' => ['label' => 'Migration: Benutzer -> JSON (Überschreibt Datei!)', 'key' => 'dashboard.migration.users.mysql_to_json'],
                                    'sync'          => ['label' => 'Migration: Benutzer Bestände zusammenführen', 'key' => 'dashboard.migration.users.sync'],
                                ],
                            ],
                            'vouchers' => [
                                'label'    => 'Migration: Alle Gutschein Aktionen',
                                'key'      => 'dashboard.migration.vouchers.view',
                                'children' => [
                                    'json_to_mysql' => ['label' => 'Migration: Gutscheine -> SQL (Überschreibt DB!)', 'key' => 'dashboard.migration.vouchers.json_to_mysql'],
                                    'mysql_to_json' => ['label' => 'Migration: Gutscheine -> JSON (Überschreibt Datei!)', 'key' => 'dashboard.migration.vouchers.mysql_to_json'],
                                    'sync'          => ['label' => 'Migration: Gutscheine Bestände zusammenführen', 'key' => 'dashboard.migration.vouchers.sync'],
                                ],
                            ],
                            'permits' => [
                                'label'    => 'Migration: Alle Genehmigungs Aktionen',
                                'key'      => 'dashboard.migration.permits.view',
                                'children' => [
                                    'json_to_mysql' => ['label' => 'Migration: Genehmigungen -> SQL (Überschreibt DB!)', 'key' => 'dashboard.migration.permits.json_to_mysql'],
                                    'mysql_to_json' => ['label' => 'Migration: Genehmigungen -> JSON (Überschreibt Datei!)', 'key' => 'dashboard.migration.permits.mysql_to_json'],
                                    'sync'          => ['label' => 'Migration: Genehmigungen Bestände zusammenführen', 'key' => 'dashboard.migration.permits.sync'],
                                ],
                            ],
                            'mail_log' => [
                                'label'    => 'Migration: Alle Mail-Log Aktionen',
                                'key'      => 'dashboard.migration.mail_log.view',
                                'children' => [
                                    'json_to_mysql' => ['label' => 'Migration: Mail-Logs -> SQL schieben', 'key' => 'dashboard.migration.mail_log.json_to_mysql'],
                                    'mysql_to_json' => ['label' => 'Migration: Mail-Logs -> JSON (Überschreibt Datei!)', 'key' => 'dashboard.migration.mail_log.mysql_to_json'],
                                    'sync'          => ['label' => 'Migration: Mail-Logs Bestände zusammenführen', 'key' => 'dashboard.migration.mail_log.sync'],
                                ],
                            ],
                            'pending_verification' => [
                                'label'    => 'Migration: Alle Warteraum Aktionen',
                                'key'      => 'dashboard.migration.pending_verification.view',
                                'children' => [
                                    'json_to_mysql' => ['label' => 'Migration: Warteraum -> SQL schieben', 'key' => 'dashboard.migration.pending_verification.json_to_mysql'],
                                    'mysql_to_json' => ['label' => 'Migration: Warteraum -> JSON (Überschreibt Datei!)', 'key' => 'dashboard.migration.pending_verification.mysql_to_json'],
                                    'sync'          => ['label' => 'Migration: Warteraum Bestände zusammenführen', 'key' => 'dashboard.migration.pending_verification.sync'],
                                ],
                            ],
                            'magic_links' => [
                                'label'    => 'Migration: Alle Token Aktionen',
                                'key'      => 'dashboard.migration.magic_links.view',
                                'children' => [
                                    'json_to_mysql' => ['label' => 'Migration: Login-Tokens -> SQL schieben', 'key' => 'dashboard.migration.magic_links.json_to_mysql'],
                                    'mysql_to_json' => ['label' => 'Migration: Login-Tokens -> JSON (Überschreibt Datei!)', 'key' => 'dashboard.migration.magic_links.mysql_to_json'],
                                    'sync'          => ['label' => 'Migration: Login-Tokens Bestände zusammenführen', 'key' => 'dashboard.migration.magic_links.sync'],
                                ],
                            ],
                        ],
                    ],
                    'backups' => [
                        'label'    => 'Migration: Liste der verfügbaren Backup-Ordner (Archiv) einsehen',
                        'key'      => 'dashboard.migration.backups.view',
                        'children' => [

                            'restore' => [
                                'label' => 'Migration: System-Wiederherstellung aus einem alten Backup-Ordner ausführen',
                                'key'   => 'dashboard.migration.restore.execute',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'system' => [
        'label' => 'System- & Benutzer-Verwaltung',
        /* 'key'      => 'system.manage', */
        'children' => [
            'users' => [
                'label'    => 'Benutzerverwaltung',
                'key'      => 'system.users.manage',
                'children' => [
                    'groups' => ['label' => 'Gruppen & Rechte', 'key' => 'system.groups.manage'],
                ],
            ],
        ],
    ],
    'templates' => [
        'label'    => 'Genehmigungs-Vorlagen (Ausstellung)',
        'key'      => 'template.manage',
        'children' => [
            'std_7'       => ['label' => 'Genehmigung: 7 Tage ausstellen', 'key' => 'template.std.7'],
            'std_14'      => ['label' => 'Genehmigung: 14 Tage ausstellen', 'key' => 'template.std.14'],
            'std_30'      => ['label' => 'Genehmigung: 1 Monat ausstellen', 'key' => 'template.std.30'],
            'perm_3'      => ['label' => 'Genehmigung: Dauerkarten 3 Monate ausstellen', 'key' => 'template.perm.3'],
            'perm_6'      => ['label' => 'Genehmigung: Dauerkarten 6 Monate ausstellen', 'key' => 'template.perm.6'],
            'perm_9'      => ['label' => 'Genehmigung: Dauerkarten 9 Monate ausstellen', 'key' => 'template.perm.9'],
            'perm_12'     => ['label' => 'Genehmigung: Dauerkarten 12 Monate ausstellen', 'key' => 'template.perm.12'],
            'custom_std'  => ['label' => 'Genehmigung: Standard mit Spezialzeiträume ausstellen', 'key' => 'template.custom.std'],
            'custom_perm' => ['label' => 'Genehmigung: Dauerkarten mit Spezialzeiträume ausstellen', 'key' => 'template.custom.perm'],
            'std_klause'  => ['label' => 'Genehmigung: Klause Belieferung Spezialzeiträume ausstellen', 'key' => 'template.std.klause'],
        ],
    ],
];
