<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;

/**
 * Action für den sicheren Logout von Administratoren.
 *
 * Path: src/Application/Actions/LogoutAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class LogoutAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
    ) {
    }

    // TODO DOCBLOCK
    public function execute(array $post): string
    {
        $this->auth->logout();
        \header('Location: admin.php');
        exit; // Zwingendes Beenden des Scripts nach einem Redirect (PRG Pattern)
    }
}
