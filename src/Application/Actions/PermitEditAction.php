<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleTokenRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\PermitService;

/**
 * Action für den "Daten korrigieren" Einstieg aus dem Checkout.
 * Lädt die temporären Daten und bereitet die Formular-Session vor.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitEditAction implements ViewActionInterface
{
    public function __construct(
        private PermitService $permitService,
        private SessionManager $sessionManager,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $dto      = SimpleTokenRequest::fromArray($request->get);
        $tempData = $this->permitService->getVerifiedRequest($dto->token);
        if ($tempData !== null) {
            $this->sessionManager->setFormData($tempData);
            $this->sessionManager->setEditState($tempData['email'] ?? '', $dto->token);
        }

        return new RedirectResponse('index.php');
    }
}
