<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\VerificationRenderRequest;
use App\Application\Http\ServerRequest;
use App\Application\View\TemplateRenderer;

/**
 * Action zum Rendern der Eingabemaske für den Verifizierungscode.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('verify_render')]
final readonly class VerificationRenderAction implements ViewActionInterface
{
    public function __construct(
        private TemplateRenderer $renderer,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $dto = VerificationRenderRequest::fromArray($request->get);

        $this->renderer->render('verify_input', [
            'isError' => $dto->isError,
        ]);

        return null;
    }
}
