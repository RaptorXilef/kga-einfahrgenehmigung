<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\CreateManualPermit;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Domain\PermitStatus;
use App\Modules\System\Application\Services\AuditLoggerService;
use App\SharedKernel\Domain\ValueObject\EmailAddress;
use App\SharedKernel\Domain\ValueObject\LicensePlate;
use App\SharedKernel\Domain\ValueObject\PlotNumber;
use App\SharedKernel\Domain\ValueObject\Price;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use Override;

#[Route('GET', '/create_manual')]
#[Route('POST', '/create_manual')]
final readonly class PermitCreateManualAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SessionManager $sessionManager,
        private CreateManualPermitHandler $createHandler,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'permits.create';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        if ($request->getMethod() === 'GET') {
            return new RedirectResponse('admin?focus=tab-tools');
        }

        $dto = PermitCreateManualRequest::fromArray($request->post, $this->clock);

        $command = new CreateManualPermitCommand(
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
            manualPrice: new Price($dto->manualPrice),
            status: PermitStatus::tryFrom($dto->status) ?? PermitStatus::Offen,
            internerKommentar: null,
            agreements: [],
            sendEmail: $dto->sendEmail,
        );

        $this->createHandler->handle($command);

        $this->auditLogger->log('PERMIT_CREATE', "Manuelle Genehmigung erstellt für: {$dto->name} (Parzelle {$dto->parzelle})");
        $this->sessionManager->addFlash('success', 'Manuelle Genehmigung wurde erfolgreich erstellt.');

        return new RedirectResponse('admin?focus=tab-active');
    }
}
