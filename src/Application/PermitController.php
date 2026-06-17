<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\PermitActionFactory;

/**
 * Front Controller für den öffentlichen Genehmigungs-Beantragungsprozess.
 *
 * Sichert die Session-Initialisierung und delegiert das Routing an
 * spezialisierte Action-Klassen über die PermitActionFactory.
 *
 * Path: src/Application/PermitController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class PermitController
{
    public function __construct(
        private PermitActionFactory $actionFactory,
    ) {
    }

    /**
     * Haupt-Request-Handler.
     *
     * @param array<string, mixed> $post Entspricht $_POST
     * @param array<string, mixed> $get  Entspricht $_GET
     */
    public function handleRequest(array $post, array $get): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }

        $action = $this->actionFactory->create($get, $post);

        $action->execute([
            'post' => $post,
            'get'  => $get,
        ]);
    }
}
