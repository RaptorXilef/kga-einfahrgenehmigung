<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SubmitPermitRequest;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Core\Exception\PermitCollisionException;
use App\Core\Service\Security\EmailValidationService;
use App\Modules\Permit\Application\DTO\PermitFormData;
use App\Modules\System\Application\Services\BotProtectionService;
use InvalidArgumentException;
use Throwable;

/**
 * Action zur Verarbeitung des abgesendeten Antragsformulars (POST).
 */
#[Route('POST', '/')]
final readonly class SubmitPermitAction implements ViewActionInterface
{
    public function __construct(
        private SubmitPermitRequestHandler $submitHandler,
        private SessionManager $sessionManager,
        private BotProtectionService $botProtection,
        private EmailValidationService $emailValidation,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $ip = $request->getIp();

        try {
            // 1. SPAM-SCHUTZ: Rate Limit prüfen
            $this->botProtection->checkRateLimit($ip);

            // 2. SPAM-SCHUTZ: Bot-Protection (Time-Check & Honeypot)
            $this->botProtection->verifyTimeCheck($this->sessionManager->getFormStartTime(), 3);
            $this->botProtection->verifyHoneypot((string) ($request->post['hp_contact_website'] ?? ''));

            // 3. DTO Validierung (Slice-eigenes DTO)
            $dto = PermitSubmitRequest::fromArray($request->post);

            // 4. TIEFE E-MAIL-PRÜFUNG (DNS/MX & Trashmail)
            if ($dto->email !== '') {
                $this->emailValidation->validate($dto->email);
            }
        } catch (ValidationException|InvalidArgumentException $e) {
            // Strike registrieren: Auch bei fehlerhaften Spam-Versuchen das Limit belasten
            $this->botProtection->recordStrike($ip);

            $postData = $request->post;
            unset($postData['csrf_token']); // Sicherheits-Token nicht mitspeichern
            $this->sessionManager->setFormData($postData);
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('index');
        }

        try {
            $command = new SubmitPermitRequestCommand(
                PermitFormData::fromArray($dto->toDomainDto()),
                $this->sessionManager->getEditToken(),
                $this->sessionManager->getVerifiedEmail(),
            );

            $result = $this->submitHandler->handle($command);

            $this->sessionManager->clearFormData();
            $this->sessionManager->clearEditState();
            $this->sessionManager->clearFormStartTime();
            $this->botProtection->recordStrike($ip);

            if ($result->action === 'redirect_checkout') {
                return new RedirectResponse('checkout?token=' . $result->token);
            }

            if ($this->sessionManager->getVerifiedEmail() !== null) {
                $this->sessionManager->addFlash(
                    'success',
                    'Sie haben die Vorlage oder den Fahrzeugtyp geändert. Bitte E-Mail erneut bestätigen.',
                );
            }

            return new RedirectResponse('?sent=1');

        } catch (PermitCollisionException $exception) {
            \error_log('Permit Collision: ' . $exception->getMessage());
            $this->sessionManager->addFlash(
                'error',
                'Überschneidung: Für diese Parzelle liegt in dem gewählten Zeitraum bereits eine Anfrage oder Genehmigung vor.',
            );

            return new RedirectResponse('index');
        } catch (InvalidArgumentException $exception) {
            // Validerungsmeldungen aus der Domain-Schicht (Value Objects) dem Nutzer anzeigen
            $this->sessionManager->addFlash('error', $exception->getMessage());

            return new RedirectResponse('index');
        } catch (Throwable $exception) {
            \error_log('Permit Creation Error: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
            $this->sessionManager->addFlash(
                'error',
                'Ein unerwarteter Systemfehler ist aufgetreten. Bitte versuchen Sie es erneut.',
            );

            return new RedirectResponse('index');
        }
    }
}
