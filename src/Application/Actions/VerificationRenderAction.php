<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;

/**
 * Action zum Rendern der Eingabemaske für den Verifizierungscode.
 *
 * Path: src/Application/Actions/VerificationRenderAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class VerificationRenderAction implements ViewActionInterface
{
    public function __construct(private TemplateRenderer $renderer)
    {
    }

    // TODO DOCBLOCK
    public function execute(array $requestData): void
    {
        $get = $requestData['get'];

        $this->renderer->render('verify_input', [
            'isError' => isset($get['error']),
            'message' => (string) ($get['msg'] ?? ''),
        ]);
    }
}
