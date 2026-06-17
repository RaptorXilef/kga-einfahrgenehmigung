<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ViewActionInterface;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/HistoryLogoutAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class HistoryLogoutAction implements ViewActionInterface
{
    /**
     * Verarbeitet den Logout-Prozess für die History-Sitzung.
     */
    public function execute(array $requestData): void
    {
        unset($_SESSION['user_history_email']);
        \header('Location: history.php');
        exit;
    }
}
