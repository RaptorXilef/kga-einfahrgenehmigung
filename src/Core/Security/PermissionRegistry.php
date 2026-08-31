<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Array aller Permissions (Modular & Flach nach TwoKinds-Standard)
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class PermissionRegistry
{
    public static function getStructure(): array
    {
        return [
            'access' => [
                'label' => '🏠 Basis-Zugriff',
                'children' => [
                    'admin' => ['label' => 'Zugriff auf das Admin-Dashboard', 'key' => 'admin.access'],
                ],
            ],
            'permits' => [
                'label' => '📄 Genehmigungen',
                'key' => 'permits.manage',
                'children' => [
                    'view' => ['label' => 'Alle Genehmigungen ansehen', 'key' => 'permits.view'],
                    'create' => ['label' => 'Manuell ausstellen', 'key' => 'permits.create'],
                    'suspend' => ['label' => 'Sperren & Entsperren', 'key' => 'permits.suspend'],
                    'print' => ['label' => 'Drucken & PDF', 'key' => 'permits.print'],
                ],
            ],
            'finance' => [
                'label' => '💰 Finanzen & Abrechnung',
                'key' => 'finance.manage',
                'children' => [
                    'view' => ['label' => 'Zahlungsübersicht ansehen', 'key' => 'finance.view'],
                    'mark_paid' => ['label' => 'Zahlungen manuell bestätigen', 'key' => 'finance.mark_paid'],
                    'bank_import' => ['label' => 'CSV-Bankimport ausführen', 'key' => 'finance.bank_import'],
                    'export' => ['label' => 'Finanzdaten exportieren (CSV/JSON)', 'key' => 'finance.export'],
                ],
            ],
            'vouchers' => [
                'label' => '🎟️ Gutscheine',
                'key' => 'vouchers.manage',
                'children' => [
                    'view' => ['label' => 'Übersicht ansehen', 'key' => 'vouchers.view'],
                    'create' => ['label' => 'Gutscheine generieren', 'key' => 'vouchers.create'],
                    'suspend' => ['label' => 'Aktivieren / Deaktivieren', 'key' => 'vouchers.suspend'],
                    'delete' => ['label' => 'Unwiderruflich löschen', 'key' => 'vouchers.delete'],
                ],
            ],
            'stats' => [
                'label' => '📊 Statistiken',
                'key' => 'stats.view',
                'children' => [
                    'charts' => ['label' => 'Wachstums-Diagramme', 'key' => 'stats.charts'],
                    'ranking' => ['label' => 'Parzellen-Ranking', 'key' => 'stats.ranking'],
                ],
            ],
            'privacy' => [
                'label' => '🛡️ Datenschutz (Sichtbarkeit)',
                'children' => [
                    'finance' => ['label' => 'Sensible Umsätze/Preise einblenden', 'key' => 'privacy.finance.view'],
                    'emails' => ['label' => 'E-Mail-Adressen im Klartext zeigen', 'key' => 'privacy.emails.view'],
                ],
            ],
            'system' => [
                'label' => '⚙️ Systemverwaltung',
                'key' => 'system.manage',
                'children' => [
                    'users' => ['label' => 'Benutzer verwalten', 'key' => 'system.users.manage'],
                    'roles' => ['label' => 'Rechte-Rollen verwalten', 'key' => 'system.roles.manage'],
                    'update' => ['label' => 'System-Updates installieren', 'key' => 'system.update.execute'],
                    'maintenance' => ['label' => 'System-Wartung & Cronjobs', 'key' => 'system.maintenance.execute'],
                    'backup' => ['label' => 'Backups & Wiederherstellung', 'key' => 'system.backup.manage'],
                    'logs' => ['label' => 'Audit- & E-Mail-Logs einsehen', 'key' => 'system.logs.view'],
                ],
            ],
            'templates' => [
                'label' => '🎟️ Genehmigungs-Vorlagen',
                'key' => 'template.manage',
                'children' => [
                    'std_7' => ['label' => 'Ausnahme 7 Tage', 'key' => 'template.std_7'],
                    'std_14' => ['label' => 'Ausnahme 14 Tage', 'key' => 'template.std_14'],
                    'std_30' => ['label' => 'Ausnahme 30 Tage', 'key' => 'template.std_30'],
                    'perm_3' => ['label' => 'Dauerkarte 1 Q.', 'key' => 'template.perm_3'],
                    'perm_6' => ['label' => 'Dauerkarte 2 Q.', 'key' => 'template.perm_6'],
                    'perm_9' => ['label' => 'Dauerkarte 3 Q.', 'key' => 'template.perm_9'],
                    'perm_12' => ['label' => 'Dauerkarte 4 Q.', 'key' => 'template.perm_12'],
                    'custom_std' => ['label' => 'Spezialzeitraum Standard', 'key' => 'template.custom_std'],
                    'custom_perm' => ['label' => 'Spezialzeitraum Dauerkarte', 'key' => 'template.custom_perm'],
                    'std_klause' => ['label' => 'Spezialzeitraum Klause', 'key' => 'template.std_klause'],
                ],
            ],
        ];
    }
}
