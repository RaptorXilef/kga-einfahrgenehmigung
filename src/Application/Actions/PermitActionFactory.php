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
    public function __construct(
        private Container $container,
    ) {
    }

    public function create(string $actionKey): ViewActionInterface
    {
        $class = match ($actionKey) {
            'edit'   => PermitEditAction::class,
            'submit' => PermitSubmitAction::class,
            default  => PermitRenderAction::class,
        };

        return $this->container->get($class);
    }
}
