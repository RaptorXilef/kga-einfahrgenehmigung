<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Infrastructure\Auth\AuthService;

/**
 * TODO DocBlocks anlegen!
 */
final readonly class StorageBootstrapper
{
    public function __construct(
        private ?\PDO $pdo,
        private AuthService $authService,
        private ConfigInterface $config,
    ) {
    }

    public function bootstrap(): void
    {
        // 1. Wenn MySQL konfiguriert ist, Tabellen sicherstellen
        if ($this->pdo instanceof \PDO) {
            $schema = $this->config->get('db_schema', []);
            foreach ($schema as $tableName => $sql) {
                try {
                    $this->pdo->exec($sql);
                } catch (\PDOException $e) {
                    \error_log("Bootstrap: Fehler beim Erstellen der Tabelle '$tableName': " . $e->getMessage());
                }
            }
        }

        // 2. Nur Core-Strukturen (Users/Groups) initial mit Defaults befüllen, falls komplett leer
        $this->initDefaultGroupsAndUsers();
    }

    private function initDefaultGroupsAndUsers(): void
    {
        // Wir prüfen, ob die Benutzerverwaltung komplett leer ist (egal ob JSON oder SQL aktiv ist)
        // Nutzt die vorhandenen loadUsers/loadGroups Methoden aus deinem AuthService
        $currentUsers  = $this->authService->loadUsers();
        $currentGroups = $this->authService->loadGroups();

        if (empty($currentGroups)) {
            \error_log('Bootstrap: Initialisiere Standard-Gruppen.');
            $this->authService->saveGroups($this->getDefaultGroups());
        }

        if (empty($currentUsers)) {
            \error_log('Bootstrap: Initialisiere Standard-Admin.');
            $this->authService->saveUsers($this->getDefaultUsers());
        }
    }

    private function getDefaultGroups(): array
    {
        // phpcs:disable Generic.Files.LineLength.TooLong
        return [
            'grp_71cb1c0d' => ['name' => 'Administrator', 'permissions' => ['*']],
            'grp_180a3ec6' => ['name' => 'Finanzen', 'permissions' => ['privacy.finance.reveal', 'privacy.email.reveal', 'check.admin.print', 'dashboard.view', 'dashboard.control_bar.view', 'dashboard.control_bar.future', 'dashboard.control_bar.search', 'dashboard.info_alert.view', 'dashboard.info_alert.print', 'dashboard.info_alert.details', 'dashboard.active.view', 'dashboard.active.print', 'dashboard.active.details', 'dashboard.active.suspend', 'dashboard.finance.view', 'dashboard.finance.details', 'dashboard.finance.suspend', 'dashboard.finance.mark_paid', 'dashboard.future.view', 'dashboard.future.print', 'dashboard.future.details', 'dashboard.future.suspend', 'dashboard.expired.view', 'dashboard.expired.print', 'dashboard.expired.details', 'dashboard.stats.view', 'dashboard.stats.current', 'dashboard.stats.charts', 'dashboard.stats.history', 'dashboard.ranking.view', 'dashboard.export.view', 'finance.export.execute', 'dashboard.export.csv', 'dashboard.export.json', 'dashboard.vouchers.view', 'dashboard.vouchers.open', 'dashboard.vouchers.suspend', 'dashboard.vouchers.remove', 'dashboard.vouchers.archive', 'dashboard.generator-tools.view', 'dashboard.generator-tools.direct_issue.reveal', 'dashboard.generator-tools.direct_issue.execute', 'dashboard.generator-tools.voucher_gen.reveal', 'dashboard.generator-tools.voucher_gen.execute', 'template.manage', 'template.std.7', 'template.std.14', 'template.std.30', 'template.perm.3', 'template.perm.6', 'template.perm.9', 'template.perm.12', 'template.custom.std', 'template.custom.perm']],
            'grp_fd72d38c' => ['name' => 'Sachbearbeitung', 'permissions' => ['privacy.email.reveal', 'check.admin.print', 'dashboard.view', 'dashboard.control_bar.view', 'dashboard.control_bar.future', 'dashboard.control_bar.search', 'dashboard.info_alert.view', 'dashboard.info_alert.print', 'dashboard.info_alert.details', 'dashboard.active.view', 'dashboard.active.print', 'dashboard.active.details', 'dashboard.finance.view', 'dashboard.finance.details', 'dashboard.future.view', 'dashboard.future.print', 'dashboard.future.details', 'dashboard.expired.view', 'dashboard.vouchers.view', 'dashboard.vouchers.open', 'dashboard.vouchers.suspend', 'dashboard.generator-tools.view', 'dashboard.generator-tools.direct_issue.reveal', 'dashboard.generator-tools.direct_issue.execute', 'dashboard.generator-tools.voucher_gen.reveal', 'dashboard.generator-tools.voucher_gen.execute', 'dashboard.logs.view', 'template.manage', 'template.std.7', 'template.std.14', 'template.std.30', 'template.perm.3', 'template.perm.6', 'template.perm.9', 'template.perm.12', 'template.custom.std', 'template.custom.perm']],
            'grp_a53d6b56' => ['name' => 'Prüfer vor Ort', 'permissions' => ['dashboard.view', 'dashboard.control_bar.view', 'dashboard.control_bar.future', 'dashboard.control_bar.search', 'dashboard.active.view', 'dashboard.active.details']],
        ];
        // phpcs:enable Generic.Files.LineLength.TooLong
    }

    private function getDefaultUsers(): array
    {
        return [
            'usr_7c13b491' => [
                'username' => 'Admin',
                'group'    => 'grp_71cb1c0d',
                'pass'     => '$2y$12$DHelEqSuvcbbGPYWqnIrIOfs/PYaMVfyahWHkW.aRM43syMd5ASoW',
            ],
        ];
    }
}
