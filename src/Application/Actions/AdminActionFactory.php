<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Bootstrap\Container;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\ViewActionInterface;

/**
 * Factory zur dynamischen (Lazy Loading) Erstellung von Admin-Actions.
 * Verhindert das "Fat Constructor" Problem im AdminController.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class AdminActionFactory
{
    public function __construct(private Container $container)
    {
    }

    /**
     * Erstellt die passende Action-Klasse anhand des POST-Action-Keys.
     *
     * @param string $actionKey Der Key aus dem Formular (z.B. 'clear_cache').
     *
     * @return ActionInterface|null Die instanziierte Action oder null, falls nicht gefunden.
     */
    public function create(string $actionKey): ActionInterface|ViewActionInterface|null
    {
        $actionClass = match ($actionKey) {
            'activate_voucher'      => VoucherToggleAction::class,
            'admin_print'           => AdminPrintAction::class,
            'anonymize_archive'     => SystemAnonymizeArchiveAction::class,
            'clear_cache'           => SystemClearCacheAction::class,
            'create_backup'         => SystemCreateBackupAction::class,
            'create_manual'         => PermitCreateManualAction::class,
            'create_voucher'        => VoucherCreateAction::class,
            'dashboard_export'      => DashboardExportAction::class,
            'deactivate_voucher'    => VoucherToggleAction::class,
            'delete_voucher'        => VoucherDeleteAction::class,
            'filter_dashboard'      => DashboardFilterAction::class,
            'force_update_check'    => SystemForceUpdateCheckAction::class,
            'login'                 => AdminLoginAction::class,
            'logout'                => AdminLogoutAction::class,
            'mark_as_paid'          => PermitMarkAsPaidAction::class,
            'migrate_data'          => SystemMigrateDataAction::class,
            'render_dashboard'      => DashboardRenderAction::class,
            'resend_mail'           => SystemResendMailAction::class,
            'restore_data'          => SystemRestoreDataAction::class,
            'run_update_migrations' => SystemRunUpdateMigrationsAction::class,
            'suspend_permit'        => PermitToggleSuspensionAction::class,
            'truncate_target'       => SystemTruncateTargetAction::class,
            'unsuspend_permit'      => PermitToggleSuspensionAction::class,
            default                 => null,
        };

        if ($actionClass === null) {
            return null;
        }

        // Wir holen die fertig konfigurierte Action direkt aus unserem Container!
        return $this->container->get($actionClass);
    }
}
