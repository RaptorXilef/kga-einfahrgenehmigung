<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewDashboard;

/**
 * 100% logikfreies DTO für die View. Ersetzt alle $auth->hasPermission() Aufrufe im Dashboard.
 */
final readonly class DashboardPermissionsDto
{
    public function __construct(
        public bool $canViewPermits,
        public bool $canPrintPermits,
        public bool $canSuspendPermits,
        public bool $canViewFinance,
        public bool $canMarkPaid,
        public bool $canBankImport,
        public bool $canExport,
        public bool $canCreatePermits,
        public bool $canManageVouchers,
        public bool $canViewVouchers,
        public bool $canViewStats,
        public bool $canViewCharts,
        public bool $canViewRanking,
        public bool $canViewLogs,
        public bool $canManageSystem,
        public bool $canExecuteMaintenance,
        public bool $canExecuteUpdates,
        public bool $canManageBackups,
        public bool $showPrivacyEmails,
        public bool $showPrivacyFinance,
        // --- Granulare Export-Rechte ---
        public bool $canExportPermitsActive,
        public bool $canExportPermitsFuture,
        public bool $canExportPermitsExpired,
        public bool $canExportPermitsActiveFuture,
        public bool $canExportPermitsAll,
        public bool $hasAnyPermitExport,
    ) {
    }
}
