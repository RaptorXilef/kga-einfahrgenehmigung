<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ViewActionInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class HistoryLogoutAction implements ViewActionInterface
{
    /**
     * Verarbeitet den Logout-Prozess für die History-Sitzung.
     */
    public function execute(ServerRequest $request): mixed
    {
        unset($_SESSION['user_history_email']);

        return new RedirectResponse('history.php');
    }
}
