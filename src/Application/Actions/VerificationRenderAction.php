<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;

/**
 * Action zum Rendern der Eingabemaske für den Verifizierungscode.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VerificationRenderAction implements ViewActionInterface
{
    public function __construct(private TemplateRenderer $renderer)
    {
    }

    // TODO DOCBLOCK
    public function execute(array $requestData): mixed
    {
        $get = $requestData['get'];

        $this->renderer->render('verify_input', [
            'isError' => isset($get['error']),
            'message' => (string) ($get['msg'] ?? ''),
        ]);

        return null;
    }
}
