<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Bootstrap\Container;
use App\Contracts\Application\ActionInterface;

/**
 * Factory zur dynamischen (Lazy Loading) Erstellung von Admin-Actions.
 * Verhindert das "Fat Constructor" Problem im AdminController.
 *
 * Path: src/Application/Actions/AdminActionFactory.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
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
    public function create(string $actionKey): ?ActionInterface
    {
        $actionClass = match ($actionKey) {
            'activate_voucher'   => ToggleVoucherAction::class,
            'anonymize_archive'  => AnonymizeArchiveAction::class,
            'clear_cache'        => ClearCacheAction::class,
            'create_manual'      => CreateManualAction::class,
            'create_voucher'     => CreateVoucherAction::class,
            'deactivate_voucher' => ToggleVoucherAction::class,
            'delete_voucher'     => DeleteVoucherAction::class,
            'filter_dashboard'   => FilterDashboardAction::class,
            'login'              => LoginAction::class,
            'logout'             => LogoutAction::class,
            'mark_as_paid'       => MarkAsPaidAction::class,
            'migrate_data'       => MigrateDataAction::class,
            'resend_mail'        => ResendMailAction::class,
            'restore_data'       => RestoreDataAction::class,
            'suspend_permit'     => ToggleSuspensionAction::class,
            'truncate_target'    => TruncateTargetAction::class,
            'unsuspend_permit'   => ToggleSuspensionAction::class,
            default              => null,
        };

        if ($actionClass === null) {
            return null;
        }

        // Wir holen die fertig konfigurierte Action direkt aus unserem Container!
        return $this->container->get($actionClass);
    }
}
