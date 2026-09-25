<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ExportFinanceData;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\EmptyResponse;
use App\Application\Response\FileDownloadResponse;
use App\Application\Session\SessionManager;
use App\Contracts\System\AuditLoggerInterface;
use App\Contracts\Utils\ClockInterface;
use Override;

#[Route('GET', '/dashboard_export')]
#[Route('POST', '/dashboard_export')]
#[RequiresAuth]
final readonly class ExportFinanceDataAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private SessionManager $sessionManager,
        private ExportFinanceDataHandler $exportHandler,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'finance.export';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $sessionFilters = $this->sessionManager->getAdminFilters();
        $dto = ExportRequest::fromRequest($request, $sessionFilters, $this->clock);

        $query = new ExportFinanceDataQuery(
            $dto->format,
            $dto->start,
            $dto->end,
            (string) ($sessionFilters['type'] ?? 'all'),
            (string) ($sessionFilters['q'] ?? ''),
        );

        $result = $this->exportHandler->handle($query);

        $this->auditLogger->log('DATA_EXPORT', "Daten-Export ausgeführt. Format: {$dto->format}.");

        if ($result->content !== '') {
            return new FileDownloadResponse($result->content, $result->filename, $result->contentType);
        }

        return new EmptyResponse(400);
    }
}
