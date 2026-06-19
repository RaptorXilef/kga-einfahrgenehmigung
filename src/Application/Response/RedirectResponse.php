<?php

declare(strict_types=1);

namespace App\Application\Response;

/**
 * Repräsentiert eine HTTP-Weiterleitung.
 * Kapselt header() und exit() aus den Actions heraus, um Testbarkeit zu gewährleisten.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class RedirectResponse
{
    public function __construct(public string $url)
    {
    }

    public function send(): void
    {
        \header('Location: ' . $this->url);
        exit;
    }
}
