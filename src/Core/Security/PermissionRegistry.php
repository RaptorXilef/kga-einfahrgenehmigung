<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Array aller Permissions
 * permission anpassen
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class PermissionRegistry
{
    public static function getStructure(): array
    {
        return [
            'privacy' => [
                'label'    => 'Daten & Finanzen (Datenschutz)',
                'children' => [
                    'finance_reveal' => ['label' => 'Finanzen: Sensible Beträge und Umsätze enthüllen', 'key' => 'privacy.finance.reveal'],
                    'email_reveal'   => ['label' => 'Datenschutz: E-Mail-Adressen im Klartext zeigen', 'key' => 'privacy.email.reveal'],
                    'mark_paid'      => ['label' => 'Zahlung bestätigen', 'key' => 'dashboard.finance.mark_paid'],
                    'bank_import'    => ['label' => 'Bank-Kontoauszug importieren', 'key' => 'dashboard.finance.bank_import'],
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
                        'label'    => 'Filter-Leiste anzeigen',
                        'key'      => 'dashboard.control_bar.view',
                        'children' => [
                            'future' => ['label' => 'Datum-Filter nutzen', 'key' => 'dashboard.control_bar.future'],
                            'search' => ['label' => 'Schnellsuche nutzen', 'key' => 'dashboard.control_bar.search'],
                        ],
                    ],
                    'info_alert' => [
                        'label'    => 'Ereignisanzeige sehen',
                        'key'      => 'dashboard.info_alert.view',
                        'children' => [
                            'future' => ['label' => 'Drucken', 'key' => 'dashboard.info_alert.print'],
                            'search' => ['label' => 'Details', 'key' => 'dashboard.info_alert.details'],
                        ],
                    ],
                    'active' => [
                        'label'    => 'Tab: Aktive Genehmigungen',
                        'key'      => 'dashboard.active.view',
                        'children' => [
                            'print'   => ['label' => 'Drucken', 'key' => 'dashboard.active.print'],
                            'details' => ['label' => 'Details', 'key' => 'dashboard.active.details'],
                            'suspend' => ['label' => 'Sperren', 'key' => 'dashboard.active.suspend'],
                        ],
                    ],
                    'finance' => [
                        'label'    => 'Tab: Finanzen',
                        'key'      => 'dashboard.finance.view',
                        'children' => [
                            'details'   => ['label' => 'Details', 'key' => 'dashboard.finance.details'],
                            'mark_paid' => ['label' => 'Zahlung bestätigen', 'key' => 'dashboard.finance.mark_paid'],
                            'suspend'   => ['label' => 'Sperren', 'key' => 'dashboard.finance.suspend'],
                        ],
                    ],
                    'future' => [
                        'label'    => 'Tab: Zukünftige Genehmigungen',
                        'key'      => 'dashboard.future.view',
                        'children' => [
                            'print'   => ['label' => 'Drucken', 'key' => 'dashboard.future.print'],
                            'details' => ['label' => 'Details', 'key' => 'dashboard.future.details'],
                            'suspend' => ['label' => 'Sperren', 'key' => 'dashboard.future.suspend'],
                        ],
                    ],
                    'expired' => [
                        'label'    => 'Tab: Abgelaufene Genehmigungen',
                        'key'      => 'dashboard.expired.view',
                        'children' => [
                            'print'   => ['label' => 'Drucken', 'key' => 'dashboard.expired.print'],
                            'details' => ['label' => 'Details', 'key' => 'dashboard.expired.details'],
                        ],
                    ],
                    'stats' => [
                        'label'    => 'Tab: Statistiken',
                        'key'      => 'dashboard.stats.view',
                        'children' => [
                            'current' => ['label' => 'Aktuelles Jahr', 'key' => 'dashboard.stats.current'],
                            'charts'  => ['label' => 'Diagramme', 'key' => 'dashboard.stats.charts'],
                            'history' => ['label' => 'Historie', 'key' => 'dashboard.stats.history'],
                        ],
                    ],
                    'ranking' => ['label' => 'Tab: Ranking', 'key' => 'dashboard.ranking.view'],
                    'export'  => [
                        'label'    => 'Tab: Export',
                        'key'      => 'dashboard.export.view',
                        'children' => [
                            'export' => [
                                'label'    => 'Export erlauben',
                                'key'      => 'finance.export.execute',
                                'children' => [
                                    'csv'  => ['label' => 'Als CSV', 'key' => 'dashboard.export.csv'],
                                    'json' => ['label' => 'Als JSON', 'key' => 'dashboard.export.json'],
                                ],
                            ],
                        ],
                    ],
                    'vouchers' => [
                        'label'    => 'Tab: Gutscheine',
                        'key'      => 'dashboard.vouchers.view',
                        'children' => [
                            'open' => [
                                'label'    => 'Offene Codes',
                                'key'      => 'dashboard.vouchers.open',
                                'children' => [
                                    'suspend' => ['label' => 'Aktivieren/Deaktivieren', 'key' => 'dashboard.vouchers.suspend'],
                                    'remove'  => ['label' => 'Löschen', 'key' => 'dashboard.vouchers.remove'],
                                ],
                            ],
                            'archive' => ['label' => 'Archiv', 'key' => 'dashboard.vouchers.archive'],
                        ],
                    ],
                    'generator-tools' => [
                        'label'    => 'Tab: Werkzeuge',
                        'key'      => 'dashboard.generator-tools.view',
                        'children' => [
                            'direct_issue' => [
                                'label'    => 'Manuell ausstellen',
                                'key'      => 'dashboard.generator-tools.direct_issue.reveal',
                                'children' => [
                                    'execute' => ['label' => 'Erstellen', 'key' => 'dashboard.generator-tools.direct_issue.execute'],
                                ],
                            ],
                            'voucher_gen' => [
                                'label'    => 'Gutschein generieren',
                                'key'      => 'dashboard.generator-tools.voucher_gen.reveal',
                                'children' => [
                                    'execute' => ['label' => 'Erstellen', 'key' => 'dashboard.generator-tools.voucher_gen.execute'],
                                ],
                            ],
                        ],
                    ],
                    'logs' => ['label' => 'Tab: Mail-Logs', 'key' => 'dashboard.logs.view'],
                ],
            ],
            'templates' => [
                'label'    => 'Genehmigungs-Vorlagen',
                'key'      => 'template.manage',
                'children' => [
                    'std_7'       => ['label' => 'Ausnahme 7 Tage', 'key' => 'template.std.7'],
                    'std_14'      => ['label' => 'Ausnahme 14 Tage', 'key' => 'template.std.14'],
                    'std_30'      => ['label' => 'Ausnahme 30 Tage', 'key' => 'template.std.30'],
                    'perm_3'      => ['label' => 'Dauerkarte 1 Q.', 'key' => 'template.perm.3'],
                    'perm_6'      => ['label' => 'Dauerkarte 2 Q.', 'key' => 'template.perm.6'],
                    'perm_9'      => ['label' => 'Dauerkarte 3 Q.', 'key' => 'template.perm.9'],
                    'perm_12'     => ['label' => 'Dauerkarte 4 Q.', 'key' => 'template.perm.12'],
                    'custom_std'  => ['label' => 'Spezialzeitraum Standard', 'key' => 'template.custom.std'],
                    'custom_perm' => ['label' => 'Spezialzeitraum Dauerkarte', 'key' => 'template.custom.perm'],
                    'std_klause'  => ['label' => 'Spezialzeitraum Klause', 'key' => 'template.std.klause'],
                ],
            ],
            'system' => [
                'label'    => '🔴 System- & Benutzer-Verwaltung',
                'children' => [
                    'permissions' => [
                        'label'    => 'Rechte-Verwaltung',
                        'key'      => 'system.permissions.view',
                        'children' => [
                            'users'  => ['label' => 'Benutzerverwaltung', 'key' => 'system.permissions.users.manage'],
                            'groups' => ['label' => 'Gruppenverwaltung', 'key' => 'system.permissions.groups.manage'],
                        ],
                    ],
                    'update' => [
                        'label'    => 'Update-Installation',
                        'key'      => 'system.update.view',
                        'children' => [
                            'execute' => ['label' => 'Ausführen', 'key' => 'system.update.execute'],
                        ],
                    ],
                    'migration' => [
                        'label'    => 'Migration & Storage',
                        'key'      => 'dashboard.migration.view',
                        'children' => [
                            'sync' => [
                                'label'    => 'Synchronisation',
                                'key'      => 'dashboard.migration.sync.view',
                                'children' => [
                                    'users' => [
                                        'label'    => 'Aktionen Benutzer',
                                        'key'      => 'dashboard.migration.users.view',
                                        'children' => [
                                            'json_to_mysql' => ['label' => 'JSON->SQL', 'key' => 'dashboard.migration.users.json_to_mysql'],
                                            'mysql_to_json' => ['label' => 'SQL->JSON', 'key' => 'dashboard.migration.users.mysql_to_json'],
                                            'sync'          => ['label' => 'Sync', 'key' => 'dashboard.migration.users.sync'],
                                        ],
                                    ],
                                    'groups' => [
                                        'label'    => 'Aktionen Gruppen',
                                        'key'      => 'dashboard.migration.groups.view',
                                        'children' => [
                                            'json_to_mysql' => ['label' => 'JSON->SQL', 'key' => 'dashboard.migration.groups.json_to_mysql'],
                                            'mysql_to_json' => ['label' => 'SQL->JSON', 'key' => 'dashboard.migration.groups.mysql_to_json'],
                                            'sync'          => ['label' => 'Sync', 'key' => 'dashboard.migration.groups.sync'],
                                        ],
                                    ],
                                    'vouchers' => [
                                        'label'    => 'Aktionen Gutscheine',
                                        'key'      => 'dashboard.migration.vouchers.view',
                                        'children' => [
                                            'json_to_mysql' => ['label' => 'JSON->SQL', 'key' => 'dashboard.migration.vouchers.json_to_mysql'],
                                            'mysql_to_json' => ['label' => 'SQL->JSON', 'key' => 'dashboard.migration.vouchers.mysql_to_json'],
                                            'sync'          => ['label' => 'Sync', 'key' => 'dashboard.migration.vouchers.sync'],
                                        ],
                                    ],
                                    'vouchers_archive' => [
                                        'label'    => 'Aktionen Gutschein-Archiv',
                                        'key'      => 'dashboard.migration.vouchers_archive.view',
                                        'children' => [
                                            'json_to_mysql' => ['label' => 'JSON->SQL', 'key' => 'dashboard.migration.vouchers_archive.json_to_mysql'],
                                            'mysql_to_json' => ['label' => 'SQL->JSON', 'key' => 'dashboard.migration.vouchers_archive.mysql_to_json'],
                                            'sync'          => ['label' => 'Sync', 'key' => 'dashboard.migration.vouchers_archive.sync'],
                                        ],
                                    ],
                                    'permits' => [
                                        'label'    => 'Aktionen Genehmigungen',
                                        'key'      => 'dashboard.migration.permits.view',
                                        'children' => [
                                            'json_to_mysql' => ['label' => 'JSON->SQL', 'key' => 'dashboard.migration.permits.json_to_mysql'],
                                            'mysql_to_json' => ['label' => 'SQL->JSON', 'key' => 'dashboard.migration.permits.mysql_to_json'],
                                            'sync'          => ['label' => 'Sync', 'key' => 'dashboard.migration.permits.sync'],
                                        ],
                                    ],
                                    'permits_archive' => [
                                        'label'    => 'Aktionen Genehmigungs-Archiv',
                                        'key'      => 'dashboard.migration.permits_archive.view',
                                        'children' => [
                                            'json_to_mysql' => ['label' => 'JSON->SQL', 'key' => 'dashboard.migration.permits_archive.json_to_mysql'],
                                            'mysql_to_json' => ['label' => 'SQL->JSON', 'key' => 'dashboard.migration.permits_archive.mysql_to_json'],
                                            'sync'          => ['label' => 'Sync', 'key' => 'dashboard.migration.permits_archive.sync'],
                                        ],
                                    ],
                                    'mail_queue' => [
                                        'label'    => 'Aktionen Mail-Warteschlange',
                                        'key'      => 'dashboard.migration.mail_queue.view',
                                        'children' => [
                                            'json_to_mysql' => ['label' => 'JSON->SQL', 'key' => 'dashboard.migration.mail_queue.json_to_mysql'],
                                            'mysql_to_json' => ['label' => 'SQL->JSON', 'key' => 'dashboard.migration.mail_queue.mysql_to_json'],
                                            'sync'          => ['label' => 'Sync', 'key' => 'dashboard.migration.mail_queue.sync'],
                                        ],
                                    ],
                                    'mail_log' => [
                                        'label'    => 'Aktionen Mail-Logs',
                                        'key'      => 'dashboard.migration.mail_log.view',
                                        'children' => [
                                            'json_to_mysql' => ['label' => 'JSON->SQL', 'key' => 'dashboard.migration.mail_log.json_to_mysql'],
                                            'mysql_to_json' => ['label' => 'SQL->JSON', 'key' => 'dashboard.migration.mail_log.mysql_to_json'],
                                            'sync'          => ['label' => 'Sync', 'key' => 'dashboard.migration.mail_log.sync'],
                                        ],
                                    ],
                                    'pending_verification' => [
                                        'label'    => 'Aktionen Warteraum E-Mail',
                                        'key'      => 'dashboard.migration.pending_verification.view',
                                        'children' => [
                                            'json_to_mysql' => ['label' => 'JSON->SQL', 'key' => 'dashboard.migration.pending_verification.json_to_mysql'],
                                            'mysql_to_json' => ['label' => 'SQL->JSON', 'key' => 'dashboard.migration.pending_verification.mysql_to_json'],
                                            'sync'          => ['label' => 'Sync', 'key' => 'dashboard.migration.pending_verification.sync'],
                                        ],
                                    ],
                                    'verified_pending' => [
                                        'label'    => 'Aktionen Warteraum Zahlung',
                                        'key'      => 'dashboard.migration.verified_pending.view',
                                        'children' => [
                                            'json_to_mysql' => ['label' => 'JSON->SQL', 'key' => 'dashboard.migration.verified_pending.json_to_mysql'],
                                            'mysql_to_json' => ['label' => 'SQL->JSON', 'key' => 'dashboard.migration.verified_pending.mysql_to_json'],
                                            'sync'          => ['label' => 'Sync', 'key' => 'dashboard.migration.verified_pending.sync'],
                                        ],
                                    ],
                                    'magic_links' => [
                                        'label'    => 'Aktionen Tokens',
                                        'key'      => 'dashboard.migration.magic_links.view',
                                        'children' => [
                                            'json_to_mysql' => ['label' => 'JSON->SQL', 'key' => 'dashboard.migration.magic_links.json_to_mysql'],
                                            'mysql_to_json' => ['label' => 'SQL->JSON', 'key' => 'dashboard.migration.magic_links.mysql_to_json'],
                                            'sync'          => ['label' => 'Sync', 'key' => 'dashboard.migration.magic_links.sync'],
                                        ],
                                    ],
                                    'login_attempts' => [
                                        'label'    => 'Aktionen Logins',
                                        'key'      => 'dashboard.migration.login_attempts.view',
                                        'children' => [
                                            'json_to_mysql' => ['label' => 'JSON->SQL', 'key' => 'dashboard.migration.login_attempts.json_to_mysql'],
                                            'mysql_to_json' => ['label' => 'SQL->JSON', 'key' => 'dashboard.migration.login_attempts.mysql_to_json'],
                                            'sync'          => ['label' => 'Sync', 'key' => 'dashboard.migration.login_attempts.sync'],
                                        ],
                                    ],
                                    'update_migrations' => [
                                        'label'    => 'Aktionen Update-Verlauf',
                                        'children' => [
                                            'json_to_mysql' => ['label' => 'JSON->SQL', 'key' => 'dashboard.migration.update_migrations.json_to_mysql'],
                                            'mysql_to_json' => ['label' => 'SQL->JSON', 'key' => 'dashboard.migration.update_migrations.mysql_to_json'],
                                            'sync'          => ['label' => 'Sync', 'key' => 'dashboard.migration.update_migrations.sync'],
                                        ],
                                    ],
                                ],
                            ],
                            'backups' => [
                                'label'    => 'Verfügbare Backups',
                                'key'      => 'dashboard.migration.backups.view',
                                'children' => [
                                    'create'  => ['label' => 'Backup erstellen', 'key' => 'dashboard.migration.backup.execute'],
                                    'restore' => ['label' => 'Restore', 'key' => 'dashboard.migration.restore.execute'],
                                ],
                            ],
                            'admin-tools' => [
                                'label'    => 'Gefahrenzone / System-Werkzeuge',
                                'key'      => 'dashboard.admin-tools.view',
                                'children' => [
                                    'delete-cache'   => ['label' => 'Cache leeren', 'key' => 'dashboard.migration.delete-cache.execute'],
                                    'delete-data'    => ['label' => 'Daten leeren (Truncate)', 'key' => 'dashboard.migration.delete-data.execute'],
                                    'anonymize-data' => ['label' => 'DSGVO Anonymisierung', 'key' => 'dashboard.migration.anonymize.execute'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
