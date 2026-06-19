<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;

/**
 * Action für den sicheren Logout von Administratoren.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class AdminLogoutAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
    ) {
    }

    public function execute(array $post): mixed
    {
        $this->auth->logout();

        return new RedirectResponse('admin.php');
    }
}
