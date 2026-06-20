<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\PermitSubmitRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\PermitService;

/**
 * Action zur Verarbeitung des abgesendeten Antragsformulars (POST).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitSubmitAction implements ViewActionInterface
{
    public function __construct(
        private PermitService $permitService,
        private SessionManager $sessionManager,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = PermitSubmitRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            return new RedirectResponse('index.php?msg=' . \urlencode($e->getMessage()));
        }
        $this->sessionManager->setFormData($dto->toDomainDto());

        try {
            $verifiedEmail = $this->sessionManager->getVerifiedEmail();
            $editToken     = $this->sessionManager->getEditToken();
            if ($verifiedEmail !== null && $editToken !== null) {
                $result = $this->permitService->updateVerifiedRequest($editToken, $verifiedEmail, $dto->toDomainDto());
                $this->sessionManager->clearFormData();
                $this->sessionManager->clearEditState();
                if ($result === 'redirect_checkout') {
                    return new RedirectResponse('checkout.php?token=' . $editToken);
                }

                return new RedirectResponse('index.php?sent=1&msg=' . \urlencode(
                    'Sie haben die Vorlage oder den Fahrzeugtyp geändert. Bitte E-Mail erneut bestätigen.',
                ));
            }
            $this->permitService->createPendingVerification($dto->toDomainDto());
            $this->sessionManager->clearFormData();
            $this->sessionManager->clearEditState();

            return new RedirectResponse('index.php?sent=1');
        } catch (\Exception $exception) {
            \error_log('Permit Creation Error: ' . $exception->getMessage());

            return new RedirectResponse('index.php?msg=' . \urlencode('Ein Fehler ist aufgetreten.'));
        }
    }
}
