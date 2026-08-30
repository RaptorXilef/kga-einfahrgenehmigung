<?php

declare(strict_types=1);

namespace App\Application\Actions\Frontend;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\PermitSubmitRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Core\Exception\PermitCollisionException;
use App\Core\Service\PermitService;
use App\Core\Service\Security\BotProtectionService;
use App\Core\Service\Security\EmailValidationService;
use InvalidArgumentException;
use Throwable;

/**
 * Action zur Verarbeitung des abgesendeten Antragsformulars (POST).
 */
#[Route('POST', '/')]
final readonly class PermitSubmitAction implements ViewActionInterface
{
    public function __construct(
        private PermitService $permitService,
        private SessionManager $sessionManager,
        private BotProtectionService $botProtection,
        private EmailValidationService $emailValidation,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            // 1. SPAM-SCHUTZ: Bot-Protection (Time-Check & Honeypot)
            $this->botProtection->verifyTimeCheck($this->sessionManager->getFormStartTime(), 3);
            $this->botProtection->verifyHoneypot((string) ($request->post['hp_contact_website'] ?? ''));

            // 2. DTO Validierung (Basic Syntax Checks)
            $dto = PermitSubmitRequest::fromArray($request->post);

            // 3. TIEFE E-MAIL-PRÜFUNG (DNS/MX & Trashmail)
            if ($dto->email !== '') {
                $this->emailValidation->validate($dto->email);
            }
        } catch (ValidationException|InvalidArgumentException $e) {
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
            $editToken = $this->sessionManager->getEditToken();

            if ($verifiedEmail !== null && $editToken !== null) {
                $result = $this->permitService->updateVerifiedRequest($editToken, $verifiedEmail, $dto->toDomainDto());
                $this->sessionManager->clearFormData();
                $this->sessionManager->clearEditState();
                $this->sessionManager->clearFormStartTime(); // Timer zurücksetzen

                if ($result === 'redirect_checkout') {
                    return new RedirectResponse('checkout?token=' . $editToken);
                }

                $this->sessionManager->addFlash('success', 'Sie haben die Vorlage oder den Fahrzeugtyp geändert. Bitte E-Mail erneut bestätigen.');

                return new RedirectResponse('?sent=1');
            }

            $this->permitService->createPendingVerification($dto->toDomainDto());
            $this->sessionManager->clearFormData();
            $this->sessionManager->clearEditState();
            $this->sessionManager->clearFormStartTime(); // Timer zurücksetzen

            return new RedirectResponse('?sent=1');
        } catch (PermitCollisionException $exception) { // Zuerst die Kollision fangen
            // 1. Detaillierter Log für Admin im Hintergrund
            \error_log('Permit Collision: ' . $exception->getMessage());

            // 2. Datenschutzkonforme, vage UI-Meldung für den User
            $this->sessionManager->addFlash(
                'error',
                'Überschneidung: Für diese Parzelle liegt in dem gewählten Zeitraum bereits eine Anfrage oder ' .
                'Genehmigung vor. Falls Sie den Status prüfen möchten, nutzen Sie bitte den Genehmigungs-"Verlauf".',
            );

            return new RedirectResponse('index.php');
        } catch (InvalidArgumentException $exception) {
            // Validerungsmeldungen aus der Domain-Schicht (Value Objects) dem Nutzer anzeigen
            $this->sessionManager->addFlash('error', $exception->getMessage());

            return new RedirectResponse('index.php');
        } catch (Throwable $exception) {
            \error_log('Permit Creation Error: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
            $this->sessionManager->addFlash('error', 'Ein unerwarteter Systemfehler ist aufgetreten. Bitte versuchen Sie es erneut.');

            return new RedirectResponse('index.php');
        }
    }
}
