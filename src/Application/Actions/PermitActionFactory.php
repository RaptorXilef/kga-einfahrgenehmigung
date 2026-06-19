<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Bootstrap\Container;
use App\Contracts\Application\ViewActionInterface;

/**
 * Factory zur dynamischen Auflösung von Routen im Antragsformular.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitActionFactory
{
    public function __construct(private Container $container)
    {
    }

    // TODO DOCBLOCK
    public function create(array $get, array $post): ViewActionInterface
    {
        if (isset($get['edit'], $get['token'])) {
            return $this->container->get(PermitEditAction::class);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->container->get(PermitSubmitAction::class);
        }

        // Fallback: Normales Rendering des Formulars
        return $this->container->get(PermitRenderAction::class);
    }
}
