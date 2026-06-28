<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Bootstrap\Container;
use App\Contracts\Application\ViewActionInterface;

/**
 * Factory zur dynamischen Auflösung von Routen im History-Bereich.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class HistoryActionFactory
{
    public function __construct(
        private Container $container,
    ) {
    }

    public function create(string $actionKey): ViewActionInterface
    {
        $class = match ($actionKey) {
            'cancel_permit' => HistoryCancelPermitAction::class,
            'logout'        => HistoryLogoutAction::class,
            'print'         => HistoryPrintAction::class,
            'request_link'  => HistoryRequestLinkAction::class,
            'submit_code'   => HistorySubmitCodeAction::class,
            'verify_token'  => HistoryVerifyTokenAction::class,
            default         => HistoryRenderAction::class,
        };

        return $this->container->get($class);
    }
}
