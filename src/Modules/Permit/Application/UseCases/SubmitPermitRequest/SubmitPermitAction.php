<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SubmitPermitRequest;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Domain\Exceptions\PermitCollisionException;
use App\Modules\System\Application\Contracts\EmailValidationServiceInterface;
use App\Modules\System\Application\Services\BotProtectionService;
use App\SharedKernel\Domain\ValueObject\EmailAddress;
use App\SharedKernel\Domain\ValueObject\LicensePlate;
use App\SharedKernel\Domain\ValueObject\PlotNumber;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use App\SharedKernel\Domain\ValueObject\VoucherCode;
use DomainException;
use InvalidArgumentException;
use Override;
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
        private EmailValidationServiceInterface $emailValidation,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
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

            $editToken = $this->sessionManager->getEditToken();
            $sessionEmail = $this->sessionManager->getVerifiedEmail();

            // 5. Instanziierung des Domain-Commands
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
                editToken: $editToken,
                sessionEmail: $sessionEmail,
            );

            $token = $this->submitHandler->handle($command);

            $this->sessionManager->clearFormData();
            $this->sessionManager->clearEditState();
            $this->sessionManager->clearFormStartTime();

            // Strike registrieren: Begrenzt auch erfolgreiche Anträge auf x pro 15 Min
            $this->botProtection->recordStrike($ip);

            // 6. Routing Logik direkt im Controller
            if ($editToken !== null && $sessionEmail !== null) {
                // Wenn die E-Mail nicht geändert wurde, geht es direkt zurück zum Checkout
                if (\strtolower(\trim($dto->email)) === \strtolower(\trim($sessionEmail))) {
                    return new RedirectResponse('checkout?token=' . $token);
                }

                $this->sessionManager->addFlash(
                    'success',
                    'Sie haben die Vorlage oder den Fahrzeugtyp geändert. Bitte E-Mail erneut bestätigen.',
                );
            }

            return new RedirectResponse('?sent=1');
        } catch (DomainException|InvalidArgumentException $e) {
            $this->botProtection->recordStrike($ip);

            // Kollisionstexte abfangen und für die Middleware mit lesbarem Text weiterwerfen
            if ($e instanceof PermitCollisionException) {
                throw new DomainException('Überschneidung: Für diese Parzelle liegt in dem gewählten Zeitraum bereits eine Anfrage oder Genehmigung vor.', $e->getCode(), $e);
            }

            throw $e;
        } catch (Throwable $e) {
            \error_log('Permit Creation Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            throw new DomainException('Ein unerwarteter Systemfehler ist aufgetreten. Bitte versuchen Sie es erneut.', $e->getCode(), $e);
        }
    }
}
