<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SubmitPermitRequest;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Domain\Exceptions\PermitCollisionException;
use App\Modules\System\Application\Services\BotProtectionService;
use App\Modules\System\Application\Services\EmailValidationService;
use App\SharedKernel\Domain\ValueObject\EmailAddress;
use App\SharedKernel\Domain\ValueObject\LicensePlate;
use App\SharedKernel\Domain\ValueObject\PlotNumber;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use App\SharedKernel\Domain\ValueObject\VoucherCode;
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
        private ClockInterface $clock,
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
            $dto = PermitSubmitRequest::fromArray($request->post, $this->clock);

            // 4. TIEFE E-MAIL-PRÜFUNG (DNS/MX & Trashmail)
            if ($dto->email !== '') {
                $this->emailValidation->validate($dto->email);
            }
        } catch (ValidationException|InvalidArgumentException $e) {
            // Strike registrieren: Auch bei fehlerhaften Spam-Versuchen das Limit belasten
            $this->botProtection->recordStrike($ip);

            // Formulardaten für die Korrektur retten
            $this->rescueFormData($request);
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('index');
        }

        try {
            // 5. Instanziierung des Domain-Commands mit sicheren Value Objects
            $command = new SubmitPermitRequestCommand(
                name: $dto->name,
                email: $dto->email !== '' ? new EmailAddress($dto->email) : null,
                parzelle: new PlotNumber($dto->parzelle),
                typ: $dto->typ,
                kennzeichen: new LicensePlate($dto->kennzeichen),
                firma: $dto->firma !== '' ? $dto->firma : null,
                zweck: $dto->zweck,
                templateKey: new TemplateKey($dto->templateKey),
                datumVon: $dto->datumVon,
                datumBis: $dto->datumBis,
                agreements: $dto->agreements,
                voucher: $dto->voucher !== '' ? new VoucherCode($dto->voucher) : null,
                editToken: $this->sessionManager->getEditToken(),
                sessionEmail: $this->sessionManager->getVerifiedEmail(),
            );

            $result = $this->submitHandler->handle($command);

            $this->sessionManager->clearFormData();
            $this->sessionManager->clearEditState();
            $this->sessionManager->clearFormStartTime();
            $this->botProtection->recordStrike($ip); // Begrenzt auch erfolgreiche Anträge auf x pro 15 Min

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

            $this->rescueFormData($request);
            $this->sessionManager->addFlash(
                'error',
                'Überschneidung: Für diese Parzelle liegt in dem gewählten Zeitraum bereits eine Anfrage oder Genehmigung vor.',
            );

            return new RedirectResponse('index');
        } catch (InvalidArgumentException $exception) {
            // Validierungsmeldungen aus der Domain-Schicht (Value Objects) dem Nutzer anzeigen
            $this->rescueFormData($request);
            $this->sessionManager->addFlash('error', $exception->getMessage());

            return new RedirectResponse('index');
        } catch (Throwable $exception) {
            \error_log('Permit Creation Error: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());

            $this->rescueFormData($request);
            $this->sessionManager->addFlash(
                'error',
                'Ein unerwarteter Systemfehler ist aufgetreten. Bitte versuchen Sie es erneut.',
            );

            return new RedirectResponse('index');
        }
    }

    /**
     * Sichert die POST-Daten für das Sticky-Form, bereinigt aber sicherheitsrelevante Felder.
     */
    private function rescueFormData(ServerRequest $request): void
    {
        $postData = $request->post;
        unset($postData['csrf_token'], $postData['hp_contact_website']);
        $this->sessionManager->setFormData($postData);
    }
}
