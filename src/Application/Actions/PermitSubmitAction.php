<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\PermitSubmitRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Core\Exception\PermitCollisionException;
use App\Core\Service\PermitService;

/**
 * Action zur Verarbeitung des abgesendeten Antragsformulars (POST).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('permit_submit')]
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
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('index.php');
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

                $this->sessionManager->addFlash('success', 'Sie haben die Vorlage oder den Fahrzeugtyp geändert. Bitte E-Mail erneut bestätigen.');

                return new RedirectResponse('index.php?sent=1');
            }

            $this->permitService->createPendingVerification($dto->toDomainDto());
            $this->sessionManager->clearFormData();
            $this->sessionManager->clearEditState();

            return new RedirectResponse('index.php?sent=1');
        } catch (PermitCollisionException $exception) { // <-- NEU: Zuerst die Kollision fangen
            // 1. Detaillierter Log für dich als Admin im Hintergrund
            \error_log('Permit Collision: ' . $exception->getMessage());

            // 2. Datenschutzkonforme, vage UI-Meldung für den User
            $this->sessionManager->addFlash(
                'error',
                'Überschneidung: Für diese Parzelle liegt in dem gewählten Zeitraum bereits eine Anfrage oder ' .
                    'Genehmigung vor. Falls Sie den Status prüfen möchten, nutzen Sie bitte den Genehmigungs-"Verlauf".',
            );

            return new RedirectResponse('index.php');

        } catch (\Exception $exception) { // <-- Bestehender Catch-All für echte Abstürze
            \error_log('Permit Creation Error: ' . $exception->getMessage());
            $this->sessionManager->addFlash('error', 'Ein unerwarteter Systemfehler ist aufgetreten.');

            return new RedirectResponse('index.php');
        }
    }
}
