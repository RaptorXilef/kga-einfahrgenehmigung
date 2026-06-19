<?php

declare(strict_types=1);

namespace App\Contracts\Application;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface ResponseInterface
{
    public function send(): void;
}
