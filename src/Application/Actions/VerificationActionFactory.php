<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Bootstrap\Container;
use App\Contracts\Application\ViewActionInterface;

/**
 * Factory zur dynamischen Auflösung von Routen im Verifizierungsprozess.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VerificationActionFactory
{
    public function __construct(private Container $container)
    {
    }

    // TODO DOCBLOCK
    public function create(array $get, array $post): ViewActionInterface
    {
        if (isset($get['token']) || isset($post['submit_code'])) {
            return $this->container->get(VerificationSubmitAction::class);
        }

        return $this->container->get(VerificationRenderAction::class);
    }
}
