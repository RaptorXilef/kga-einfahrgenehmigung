<?php

declare(strict_types=1);

namespace App\Application\Actions\Frontend;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\VerificationRenderRequest;
use App\Application\Http\ServerRequest;
use App\Application\View\TemplateRenderer;

/**
 * Action zum Rendern der Eingabemaske für den Verifizierungscode.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[Route('GET|POST', '/verify_render')]
final readonly class VerificationRenderAction implements ViewActionInterface
{
    public function __construct(
        private TemplateRenderer $renderer,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $dto = VerificationRenderRequest::fromArray($request->get);

        $this->renderer->render('frontend/verify_input', [
            'isError' => $dto->isError,
        ]);

        return null;
    }
}
