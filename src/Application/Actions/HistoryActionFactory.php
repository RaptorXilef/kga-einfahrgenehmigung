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
    public function __construct(private Container $container)
    {
    }

    // TODO DOCBLOCK
    public function create(array $get, array $post): ViewActionInterface
    {
        if (isset($post['action']) && $post['action'] === 'logout') {
            return $this->container->get(HistoryLogoutAction::class);
        }
        if (isset($post['request_link'])) {
            return $this->container->get(HistoryRequestLinkAction::class);
        }
        if (isset($post['submit_code'])) {
            return $this->container->get(HistorySubmitCodeAction::class);
        }
        if (isset($get['token'])) {
            return $this->container->get(HistoryVerifyTokenAction::class);
        }
        if (isset($get['action'], $get['code']) && $get['action'] === 'print') {
            return $this->container->get(HistoryPrintAction::class);
        }

        // Fallback: Normales Rendering der Liste oder des Login-Formulars
        return $this->container->get(HistoryRenderAction::class);
    }
}
