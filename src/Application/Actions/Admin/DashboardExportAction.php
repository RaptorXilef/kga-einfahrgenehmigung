<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\ExportRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\EmptyResponse;
use App\Application\Response\FileDownloadResponse;
use App\Application\Session\SessionManager;
use App\Modules\Finance\Application\UseCases\ExportFinanceData\ExportFinanceDataHandler;
use App\Modules\Finance\Application\UseCases\ExportFinanceData\ExportFinanceDataQuery;
use Modules\System\Application\Services\AuditLoggerService;

#[Route('GET', '/dashboard_export')]
#[Route('POST', '/dashboard_export')]
final readonly class DashboardExportAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SessionManager $sessionManager,
        private ExportFinanceDataHandler $exportHandler, // CQRS
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'finance.export';
    }

    public function execute(ServerRequest $request): mixed
    {
        $sessionFilters = $this->sessionManager->getAdminFilters();
        $dto = ExportRequest::fromRequest($request, $sessionFilters);

        $query = new ExportFinanceDataQuery(
            $dto->format,
            $dto->start,
            $dto->end,
            $sessionFilters['type'] ?? 'all',
            $sessionFilters['q'] ?? '',
        );

        $result = $this->exportHandler->handle($query);

        $this->auditLogger->log('DATA_EXPORT', "Daten-Export ausgeführt. Format: {$dto->format}.");

        if ($result->content !== '') {
            return new FileDownloadResponse($result->content, $result->filename, $result->contentType);
        }

        return new EmptyResponse(400);
    }
}
