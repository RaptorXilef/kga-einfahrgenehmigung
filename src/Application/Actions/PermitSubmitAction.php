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
        } catch (ValidationException|\InvalidArgumentException $e) {
            // FIX: UX-Rettung! Bevor wir abbrechen, speichern wir die bereits eingetippten Daten
            // in der Session zwischen, damit das Formular nicht leer ist.
            $postData = $request->post;
            unset($postData['csrf_token']); // Sicherheits-Token nicht mitspeichern
            $this->sessionManager->setFormData($postData);

            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('index.php');
        }

        // Wenn die Validierung klappt, speichern wir das formal saubere DTO
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

        } catch (PermitCollisionException $exception) { // Zuerst die Kollision fangen
            // 1. Detaillierter Log für dich als Admin im Hintergrund
            \error_log('Permit Collision: ' . $exception->getMessage());

            // 2. Datenschutzkonforme, vage UI-Meldung für den User
            $this->sessionManager->addFlash(
                'error',
                'Überschneidung: Für diese Parzelle liegt in dem gewählten Zeitraum bereits eine Anfrage oder ' .
                'Genehmigung vor. Falls Sie den Status prüfen möchten, nutzen Sie bitte den Genehmigungs-"Verlauf".',
            );

            return new RedirectResponse('index.php');
        } catch (\InvalidArgumentException $exception) {
            // Validerungsmeldungen aus der Domain-Schicht (Value Objects) dem Nutzer anzeigen
            $this->sessionManager->addFlash('error', $exception->getMessage());

            return new RedirectResponse('index.php');
        } catch (\Throwable $exception) { // FIX: Throwable fängt auch TypeErrors und ParseErrors!
            \error_log('Permit Creation Error: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
            $this->sessionManager->addFlash('error', 'Ein unerwarteter Systemfehler ist aufgetreten. Bitte versuchen Sie es erneut.');

            return new RedirectResponse('index.php');
        }
    }
}
