<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ViewActionInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class HistoryLogoutAction implements ViewActionInterface
{
    public function __construct(
        private SessionManager $sessionManager,
    ) {
    }

    /**
     * Verarbeitet den Logout-Prozess für die History-Sitzung.
     */
    public function execute(ServerRequest $request): mixed
    {
        $this->sessionManager->clearHistoryEmail();

        return new RedirectResponse('history.php');
    }
}
