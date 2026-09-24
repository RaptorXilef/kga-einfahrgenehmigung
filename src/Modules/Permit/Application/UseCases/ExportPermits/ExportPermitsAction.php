<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ExportPermits;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\EmptyResponse;
use App\Application\Response\FileDownloadResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Identity\Application\Services\AuthService;
use App\Modules\System\Application\Services\AuditLoggerService;
use Override;

#[Route('GET', '/export_permits')]
#[Route('POST', '/export_permits')]
final readonly class ExportPermitsAction implements ViewActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SessionManager $sessionManager,
        private ExportPermitsHandler $exportHandler,
        private ClockInterface $clock,
        private AuthService $auth,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $sessionFilters = $this->sessionManager->getAdminFilters();
        $dto = ExportPermitsRequest::fromRequest($request, $sessionFilters, $this->clock);

        // Dynamischer Rechte-Check basierend auf dem angefragten State
        if (!$this->auth->hasPermission('permits.export.' . $dto->state)) {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Berechtigung für diesen Export.');

            return new RedirectResponse('admin?focus=tab-export');
        }

        $query = new ExportPermitsQuery(
            $dto->state,
            $dto->start,
            $dto->end,
            $dto->type,
            $dto->searchQuery,
        );

        $result = $this->exportHandler->handle($query);

        $this->auditLogger->log('DATA_EXPORT', "Genehmigungs-Listen Export ausgeführt. Filter: {$dto->state}.");

        if ($result->content !== '') {
            return new FileDownloadResponse($result->content, $result->filename, $result->contentType);
        }

        return new EmptyResponse(400);
    }
}
