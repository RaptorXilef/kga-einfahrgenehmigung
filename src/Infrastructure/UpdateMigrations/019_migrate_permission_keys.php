<?php

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;

/**
 * Migration 019: Konvertiert die alten verschachtelten Permission-Keys in das flache TwoKinds-Format.
 * Führt mehrfache alte Keys sauber zusammen und verhindert Duplikate im JSON-Array.
 */
return static function (?\PDO $pdo, ConfigInterface $config): void {
    if ($pdo === null) {
        return;
    }

    $rolesTable = $config->get('storage_config')['roles']['table'] ?? 'roles';

    // 1. Prüfen, ob die Tabelle existiert
    try {
        $hasRoles = $pdo->query("SHOW TABLES LIKE '{$rolesTable}'")->rowCount() > 0;
        if (!$hasRoles) {
            return;
        }
    } catch (\PDOException $e) {
        return;
    }

    // 2. Das Mapping-Wörterbuch
    $mapping = [
        // Genehmigungen (Permits)
        'dashboard.active.view' => 'permits.view',
        'dashboard.future.view' => 'permits.view',
        'dashboard.expired.view' => 'permits.view',
        'dashboard.cancelled.view' => 'permits.view',
        'dashboard.control_bar.view' => 'permits.view',
        'dashboard.control_bar.future' => 'permits.view',
        'dashboard.control_bar.search' => 'permits.view',
        'dashboard.info_alert.view' => 'permits.view',
        'dashboard.info_alert.details' => 'permits.view',
        'dashboard.active.details' => 'permits.view',
        'dashboard.future.details' => 'permits.view',
        'dashboard.expired.details' => 'permits.view',
        'dashboard.active.print' => 'permits.print',
        'dashboard.future.print' => 'permits.print',
        'dashboard.expired.print' => 'permits.print',
        'dashboard.info_alert.print' => 'permits.print',
        'check.admin.print' => 'permits.print',
        'dashboard.active.suspend' => 'permits.suspend',
        'dashboard.future.suspend' => 'permits.suspend',
        'dashboard.generator-tools.direct_issue.reveal' => 'permits.create',
        'dashboard.generator-tools.direct_issue.execute' => 'permits.create',

        // Finanzen & Export
        'dashboard.finance.view' => 'finance.view',
        'dashboard.finance.details' => 'finance.view',
        'dashboard.finance.mark_paid' => 'finance.mark_paid',
        'dashboard.finance.suspend' => 'permits.suspend',
        'dashboard.finance.bank_import' => 'finance.bank_import',
        'dashboard.export.view' => 'finance.export',
        'dashboard.export.csv' => 'finance.export',
        'dashboard.export.json' => 'finance.export',
        'finance.export.execute' => 'finance.export',

        // Gutscheine
        'dashboard.vouchers.view' => 'vouchers.view',
        'dashboard.vouchers.open' => 'vouchers.view',
        'dashboard.vouchers.archive' => 'vouchers.view',
        'dashboard.vouchers.suspend' => 'vouchers.suspend',
        'dashboard.vouchers.remove' => 'vouchers.delete',
        'dashboard.generator-tools.voucher_gen.reveal' => 'vouchers.create',
        'dashboard.generator-tools.voucher_gen.execute' => 'vouchers.create',

        // Statistiken
        'dashboard.stats.view' => 'stats.view',
        'dashboard.stats.current' => 'stats.view',
        'dashboard.stats.history' => 'stats.view',
        'dashboard.stats.charts' => 'stats.charts',
        'dashboard.ranking.view' => 'stats.ranking',

        // System, Wartung & Logs
        'system.permissions.view' => 'system.manage',
        'system.permissions.users.manage' => 'system.users.manage',
        'system.permissions.roles.manage' => 'system.roles.manage',
        'system.update.view' => 'system.update.execute',
        'dashboard.logs.view' => 'system.logs.view',
        'dashboard.audit_log.view' => 'system.logs.view',
        'dashboard.migration.view' => 'system.maintenance.execute',
        'dashboard.migration.delete-cache.execute' => 'system.maintenance.execute',
        'dashboard.migration.delete-data.execute' => 'system.maintenance.execute',
        'dashboard.migration.anonymize.execute' => 'system.maintenance.execute',
        'dashboard.migration.backup.execute' => 'system.maintenance.execute',
        'dashboard.migration.restore.execute' => 'system.maintenance.execute',

        // Datenschutz
        'privacy.finance.reveal' => 'privacy.finance.view',
        'privacy.email.reveal' => 'privacy.emails.view',
    ];

    try {
        // 3. Alle Rollen abfragen
        $stmt = $pdo->query("SELECT `id`, `permissions` FROM `{$rolesTable}`");
        $roles = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $updateStmt = $pdo->prepare("UPDATE `{$rolesTable}` SET `permissions` = :perms WHERE `id` = :id");

        // 4. Für jede Rolle die Berechtigungen umschreiben
        foreach ($roles as $role) {
            $rawPerms = $role['permissions'];
            $perms = \is_string($rawPerms) ? \json_decode($rawPerms, true) : [];

            if (!\is_array($perms)) {
                $perms = [];
            }

            $newPerms = [];
            foreach ($perms as $perm) {
                // Prüfen ob es ein explizites Verbot ist (Minus davor)
                $isNegative = \str_starts_with($perm, '-');
                $basePerm = $isNegative ? \substr($perm, 1) : $perm;

                // Mappen oder den originalen Wert behalten (z.B. bei '*')
                $mappedBase = $mapping[$basePerm] ?? $basePerm;

                // Wieder zusammenfügen
                $newPerms[] = $isNegative ? '-' . $mappedBase : $mappedBase;
            }

            // 5. Duplikate entfernen (z.B. aus 4x 'permits.view' wird 1x 'permits.view')
            $uniquePerms = \array_unique($newPerms);

            // 6. Array-Schlüssel neu nummerieren, damit es ein sauberes [0, 1, 2] Array bleibt
            // Andernfalls würde json_encode bei Lücken ein Objekt {"0": "...", "3": "..."} daraus machen!
            $finalPermsArray = \array_values($uniquePerms);

            $updateStmt->execute([
                ':perms' => \json_encode($finalPermsArray, \JSON_UNESCAPED_UNICODE),
                ':id' => $role['id'],
            ]);
        }
    } catch (\PDOException $e) {
        \error_log('Migration 021 (MySQL Permission Keys): ' . $e->getMessage());
    }
};
