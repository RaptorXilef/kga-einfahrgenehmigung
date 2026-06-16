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
     * @param  string               $actionKey Der Key aus dem Formular (z.B. 'clear_cache').
     * @return ActionInterface|null Die instanziierte Action oder null, falls nicht gefunden.
     */
    public function create(string $actionKey): ?ActionInterface
    {
        $actionClass = match ($actionKey) {
            'clear_cache'    => ClearCacheAction::class,
            'delete_voucher' => DeleteVoucherAction::class,
            // Hier fügen wir später Zeile für Zeile unsere neuen Actions hinzu!
            // 'delete_voucher' => DeleteVoucherAction::class,
            default => null,
        };

        if ($actionClass === null) {
            return null;
        }

        // Wir holen die fertig konfigurierte Action direkt aus unserem Container!
        return $this->container->get($actionClass);
    }
}
